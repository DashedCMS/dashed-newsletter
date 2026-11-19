<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\PersonalData;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Dashed\DashedCore\PersonalData\Anonymized;
use Dashed\DashedCore\PersonalData\PersonalDataExport;
use Dashed\DashedCore\PersonalData\PersonalDataFinding;
use Dashed\DashedCore\PersonalData\PersonalDataSubject;
use Dashed\DashedCore\PersonalData\PersonalDataProvider;

/**
 * Nieuwsbriefgegevens bij een AVG-verzoek.
 *
 * Het abonnee-record zelf wordt geanonimiseerd en niet verwijderd: de
 * ontvangerregels van verzonden campagnes hangen er met een verplichte
 * foreign key en cascadeOnDelete aan, en de cijfers van een campagne wonen
 * op die regels. Verwijderen zou die cijfers achteraf laten zakken. De
 * ontvangerregels worden daarom ook geanonimiseerd; de kliks hangen alleen
 * aan id's en tijdstippen en blijven dus vanzelf mee kloppen.
 *
 * De blokkadelijst blijft staan: zonder die rij kan het adres opnieuw op een
 * lijst komen (import, Bol, formulier) en mailen we iemand die juist bezwaar
 * maakte of wiens adres bounced. De rij bevat alleen site, adres en reden.
 */
class NewsletterPersonalDataProvider extends PersonalDataProvider
{
    public function key(): string
    {
        return 'dashed-newsletter';
    }

    public function label(): string
    {
        return __('Nieuwsbrief');
    }

    public function find(PersonalDataSubject $subject): array
    {
        $findings = [];
        $subscriberIds = $this->subscriberIds($subject);

        if ($subscriberIds !== []) {
            $findings[] = PersonalDataFinding::anonymize('subscribers', __('Abonnementen'), count($subscriberIds), __('Het abonnement blijft als lege rij staan zodat de cijfers van verzonden campagnes kloppen. Er wordt nooit meer naar gemaild.'));

            foreach ($this->childSources() as $key => [$table, $label]) {
                if ($this->hasTable($table)) {
                    $count = DB::table($table)->whereIn('newsletter_subscriber_id', $subscriberIds)->count();
                    if ($count > 0) {
                        $findings[] = PersonalDataFinding::delete($key, $label, $count);
                    }
                }
            }
        }

        $recipients = $this->recipients($subject, $subscriberIds);
        if ($recipients && ($count = (clone $recipients)->count()) > 0) {
            $findings[] = PersonalDataFinding::anonymize('campaign_recipients', __('Ontvangen campagnes'), $count, __('Adres en toelichting worden gewist; opens, kliks en bezorging blijven meetellen in de campagnecijfers.'));
        }

        $suppressions = $this->suppressions($subject);
        if ($suppressions && ($count = (clone $suppressions)->count()) > 0) {
            $findings[] = PersonalDataFinding::keep('suppressions', __('Blokkadelijst'), $count, __('Blijft staan, anders kan het adres opnieuw ingeschreven worden en mailen we iemand die bezwaar maakte of wiens adres niet werkt.'));
        }

        return $findings;
    }

    public function export(PersonalDataSubject $subject, PersonalDataExport $export): void
    {
        $subscriberIds = $this->subscriberIds($subject);

        if ($subscriberIds !== []) {
            $query = DB::table('dashed__newsletter_subscribers as s')
                ->whereIn('s.id', $subscriberIds)
                ->select('s.*');
            if ($this->hasTable('dashed__newsletter_lists')) {
                $query->leftJoin('dashed__newsletter_lists as l', 'l.id', '=', 's.newsletter_list_id')
                    ->addSelect('l.name as list_name');
            }
            $export->add('subscribers', $this->rows($query));

            if ($this->hasTable('dashed__newsletter_field_values')) {
                $values = DB::table('dashed__newsletter_field_values as v')
                    ->whereIn('v.newsletter_subscriber_id', $subscriberIds)
                    ->select('v.newsletter_subscriber_id', 'v.value', 'v.value_number', 'v.value_date', 'v.created_at', 'v.updated_at');
                if ($this->hasTable('dashed__newsletter_fields')) {
                    $values->leftJoin('dashed__newsletter_fields as f', 'f.id', '=', 'v.newsletter_field_id')
                        ->addSelect('f.key as field_key', 'f.label as field_label');
                }
                $export->add('field_values', $this->rows($values));
            }

            foreach (['events' => 'dashed__newsletter_subscriber_events', 'consents' => 'dashed__newsletter_consents'] as $key => $table) {
                if ($this->hasTable($table)) {
                    $export->add($key, $this->rows(DB::table($table)->whereIn('newsletter_subscriber_id', $subscriberIds)));
                }
            }
        }

        if ($recipients = $this->recipients($subject, $subscriberIds)) {
            $query = (clone $recipients)->select('r.*');
            if ($this->hasTable('dashed__newsletter_campaigns')) {
                $query->leftJoin('dashed__newsletter_campaigns as c', 'c.id', '=', 'r.newsletter_campaign_id')
                    ->addSelect('c.name as campaign_name', 'c.subject as campaign_subject');
            }
            $export->add('campaign_recipients', $this->rows($query, ['sent_email_id']));

            if ($this->hasTable('dashed__newsletter_campaign_clicks')) {
                $clicks = DB::table('dashed__newsletter_campaign_clicks as k')
                    ->whereIn('k.newsletter_campaign_recipient_id', (clone $recipients)->select('r.id'))
                    ->select('k.newsletter_campaign_id', 'k.clicked_at');
                if ($this->hasTable('dashed__newsletter_campaign_links')) {
                    $clicks->leftJoin('dashed__newsletter_campaign_links as u', 'u.id', '=', 'k.newsletter_campaign_link_id')
                        ->addSelect('u.url');
                }
                $export->add('campaign_clicks', $this->rows($clicks));
            }
        }

        if ($suppressions = $this->suppressions($subject)) {
            $export->add('suppressions', $this->rows($suppressions));
        }
    }

    public function erase(PersonalDataSubject $subject): array
    {
        $findings = [];
        $subscriberIds = $this->subscriberIds($subject);

        // Ontvangerregels eerst, want daarna is het adres op de abonnee weg.
        if ($recipients = $this->recipients($subject, $subscriberIds)) {
            $ids = (clone $recipients)->pluck('r.id')->all();
            if ($ids !== []) {
                $columns = array_flip(\Illuminate\Support\Facades\Schema::getColumnListing('dashed__newsletter_campaign_recipients'));
                DB::table('dashed__newsletter_campaign_recipients')->whereIn('id', $ids)->update(array_intersect_key([
                    'email' => Anonymized::email(),
                    'unsubscribe_comment' => null,
                    'bounce_reason' => null,
                    'updated_at' => now(),
                ], $columns));
                $findings[] = PersonalDataFinding::anonymize('campaign_recipients', __('Ontvangen campagnes'), count($ids));
            }
        }

        if ($subscriberIds !== []) {
            foreach ($this->childSources() as $key => [$table, $label]) {
                if ($this->hasTable($table)) {
                    $count = DB::table($table)->whereIn('newsletter_subscriber_id', $subscriberIds)->delete();
                    if ($count > 0) {
                        $findings[] = PersonalDataFinding::delete($key, $label, $count);
                    }
                }
            }

            foreach ($subscriberIds as $id) {
                // Uniek per lijst, dus een eigen anoniem adres per rij.
                DB::table('dashed__newsletter_subscribers')->where('id', $id)->update([
                    'email' => Anonymized::email($id),
                    'user_id' => null,
                    'status' => 'cleaned',
                    'source' => null,
                    'unsubscribe_reason' => null,
                    'updated_at' => now(),
                ]);
            }
            $findings[] = PersonalDataFinding::anonymize('subscribers', __('Abonnementen'), count($subscriberIds));
        }

        $suppressions = $this->suppressions($subject);
        if ($suppressions && ($count = (clone $suppressions)->count()) > 0) {
            $findings[] = PersonalDataFinding::keep('suppressions', __('Blokkadelijst'), $count, __('Blijft staan, anders kan het adres opnieuw ingeschreven worden en mailen we iemand die bezwaar maakte of wiens adres niet werkt.'));
        }

        return $findings;
    }

    /** @return array<string, array{0: string, 1: string}> */
    protected function childSources(): array
    {
        return [
            'field_values' => ['dashed__newsletter_field_values', __('Extra velden')],
            'events' => ['dashed__newsletter_subscriber_events', __('Geschiedenis van het abonnement')],
            // Toestemming is het bewijs voor de verwerking, maar na een
            // verwijderverzoek is er geen verwerking meer om te bewijzen. Het
            // AVG-verzoek zelf legt vast dat en wanneer er gewist is.
            'consents' => ['dashed__newsletter_consents', __('Vastgelegde toestemmingen')],
        ];
    }

    /** @return array<int, int> */
    protected function subscriberIds(PersonalDataSubject $subject): array
    {
        if (! $this->hasTable('dashed__newsletter_subscribers')) {
            return [];
        }

        return $subject->whereEmailOrUser(DB::table('dashed__newsletter_subscribers'), 'email', 'user_id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Ontvangerregels op het adres zelf (dat is waar de mail heen ging) of
     * via de abonnee.
     *
     * @param  array<int, int>  $subscriberIds
     */
    protected function recipients(PersonalDataSubject $subject, array $subscriberIds): ?Builder
    {
        if (! $this->hasTable('dashed__newsletter_campaign_recipients')) {
            return null;
        }

        return DB::table('dashed__newsletter_campaign_recipients as r')
            ->where(function ($query) use ($subject, $subscriberIds) {
                $subject->whereEmail($query, 'r.email');
                if ($subscriberIds !== []) {
                    $query->orWhereIn('r.newsletter_subscriber_id', $subscriberIds);
                }
            });
    }

    protected function suppressions(PersonalDataSubject $subject): ?Builder
    {
        if (! $this->hasTable('dashed__newsletter_suppressions')) {
            return null;
        }

        return $subject->whereEmail(DB::table('dashed__newsletter_suppressions'), 'email');
    }
}
