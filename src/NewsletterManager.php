<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter;

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedNewsletter\Import\ImportResult;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedNewsletter\Import\ImportedContact;
use Dashed\DashedNewsletter\Exceptions\InvalidEmailException;
use Dashed\DashedNewsletter\Models\NewsletterConsent;
use Dashed\DashedNewsletter\Models\NewsletterFieldValue;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Events\NewsletterSubscribedEvent;
use Dashed\DashedNewsletter\Models\NewsletterSubscriberEvent;
use Dashed\DashedNewsletter\Segments\SegmentConditionRegistry;
use Dashed\DashedNewsletter\Events\NewsletterUnsubscribedEvent;
use Dashed\DashedNewsletter\Segments\Contracts\SegmentCondition;

class NewsletterManager
{
    public function registerSegmentCondition(SegmentCondition $condition): self
    {
        app(SegmentConditionRegistry::class)->register($condition);

        return $this;
    }

    /** @return array<string, SegmentCondition> */
    public function segmentConditions(): array
    {
        return app(SegmentConditionRegistry::class)->all();
    }

    /** @var array<int, callable> */
    private array $settingsActions = [];

    /**
     * Een knop aanmelden voor de instellingenpagina van de nieuwsbrief.
     *
     * Zelfde gedachte als registerSegmentCondition(): het nieuwsbriefpakket
     * hoort niet te weten welke koppelingen er bestaan. Het overnemen van
     * contacten uit Laposta staat hier omdat dashed-laposta zich aanmeldt, niet
     * omdat deze pagina van Laposta weet.
     *
     * De fabriek krijgt het site-id, want een knop hoort bij een site en de
     * naam van een Filament-actie moet uniek zijn.
     *
     * @param callable(string): \Filament\Actions\Action $factory
     */
    public function registerSettingsAction(callable $factory): self
    {
        $this->settingsActions[] = $factory;

        return $this;
    }

    /** @return array<int, \Filament\Actions\Action> */
    public function settingsActions(string $siteId): array
    {
        return array_map(fn (callable $factory) => $factory($siteId), $this->settingsActions);
    }

    /**
     * De lijst die is ingesteld als standaard voor deze site, of null.
     *
     * Bewust ook op site gefilterd en niet alleen op id: blijft er een instelling
     * achter die naar een lijst van een andere site wijst, dan hoort daar geen
     * lijst uit te komen in plaats van de verkeerde.
     */
    public function defaultList(?string $siteId = null): ?NewsletterList
    {
        $siteId = $siteId ?: Sites::getActive();
        $listId = Customsetting::get('newsletter_default_list_id', $siteId);

        if (! $listId) {
            return null;
        }

        return NewsletterList::forSite($siteId)->find($listId);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function subscribe(
        string $email,
        NewsletterList $list,
        array $fields = [],
        ?string $source = null,
        ?string $consentText = null,
        ?string $ip = null,
    ): NewsletterSubscriber {
        $email = mb_strtolower(trim($email));

        // Vóór de transactie, niet erin: een ongeldig adres mag nooit een
        // half weggeschreven contact achterlaten.
        //
        // Wel stoppen, niet melden: zie InvalidEmailException. Een bezoeker
        // die zich vertypt is geen storing, en zulke gevallen hoorden hier de
        // foutmelder te vullen alsof er iets stuk was.
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException('Ongeldig e-mailadres.');
        }

        $isNieuweAanmelding = false;

        // Contact, veldwaarden, event en toestemming horen bij elkaar: gaat er
        // halverwege iets mis, dan mag er geen actief contact zonder
        // toestemmingsbewijs blijven staan.
        $subscriber = DB::transaction(function () use ($email, $list, $fields, $source, $consentText, $ip, &$isNieuweAanmelding): NewsletterSubscriber {
            $subscriber = NewsletterSubscriber::firstOrNew([
                'newsletter_list_id' => $list->id,
                'email' => $email,
            ]);

            // Een bestaand actief contact dat zich nog eens aanmeldt is geen
            // nieuwe aanmelding; daar hoort geen melding bij.
            $isNieuweAanmelding = ! $subscriber->exists
                || $subscriber->status !== NewsletterSubscriber::STATUS_ACTIVE;

            $subscriber->status = NewsletterSubscriber::STATUS_ACTIVE;
            $subscriber->unsubscribed_at = null;
            $subscriber->source = $source !== null ? $source : $subscriber->source;
            $subscriber->save();

            $definitions = $list->fields()->get()->keyBy('key');

            foreach ($fields as $key => $value) {
                $definition = $definitions->get($key);

                if (! $definition) {
                    continue;
                }

                NewsletterFieldValue::writeValue($subscriber, $definition, $value);
            }

            $this->recordSubscribed(
                subscriber: $subscriber,
                payload: ['source' => $source, 'fields' => array_keys($fields)],
                source: $source,
                consentText: $consentText,
                ip: $ip,
            );

            return $subscriber;
        });

        // Na de transactie, niet erin: een luisteraar die een melding verstuurt
        // hoort niet mee te draaien in de transactie die het contact wegschrijft.
        if ($isNieuweAanmelding) {
            NewsletterSubscribedEvent::dispatch($subscriber);
        }

        return $subscriber;
    }

    /**
     * Neemt een contact over dat al ergens anders op een lijst stond.
     *
     * Bewust een tweede weg naast subscribe(). Die betekent "iemand meldt zich
     * nu aan" en zet daarom altijd op actief; hier komt de status uit de bron.
     * Zou een overname door subscribe() lopen, dan wordt iedereen die zich ooit
     * heeft uitgeschreven stilzwijgend weer actief, en dat merk je pas als er
     * post uitgaat.
     */
    public function import(NewsletterList $list, ImportedContact $contact): NewsletterSubscriber
    {
        $email = mb_strtolower(trim($contact->email));

        // Vóór de transactie, net als bij subscribe(): een ongeldig adres mag
        // geen half weggeschreven contact achterlaten.
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException('Ongeldig e-mailadres: ' . $contact->email);
        }

        $statuses = [
            NewsletterSubscriber::STATUS_ACTIVE,
            NewsletterSubscriber::STATUS_UNSUBSCRIBED,
            NewsletterSubscriber::STATUS_CLEANED,
        ];

        // Een onbekende status hard weigeren. Stil terugvallen op actief is
        // precies de fout die deze hele ingang moet voorkomen.
        if (! in_array($contact->status, $statuses, true)) {
            throw new \InvalidArgumentException('Onbekende status: ' . $contact->status);
        }

        return DB::transaction(function () use ($list, $contact, $email): NewsletterSubscriber {
            $subscriber = NewsletterSubscriber::firstOrNew([
                'newsletter_list_id' => $list->id,
                'email' => $email,
            ]);

            $isNieuw = ! $subscriber->exists;
            $vorigeStatus = $subscriber->status;

            // Een overname die van niet-actief naar actief gaat is een echte
            // heractivering, net als changeStatus() dat behandelt. Een nieuw
            // contact telt niet mee: dat is geen heractivering maar een eerste
            // overname.
            $wordtGeheractiveerd = ! $isNieuw
                && $vorigeStatus !== NewsletterSubscriber::STATUS_ACTIVE
                && $contact->status === NewsletterSubscriber::STATUS_ACTIVE;

            $subscriber->status = $contact->status;
            $subscriber->source = $contact->source ?? $subscriber->source;

            if ($contact->subscribedAt) {
                $subscriber->subscribed_at = $contact->subscribedAt;
            }

            if ($contact->confirmedAt) {
                $subscriber->confirmed_at = $contact->confirmedAt;
            }

            // Anders staat er een actief contact met een gevulde
            // unsubscribed_at ernaast, precies wat changeStatus() ook voorkomt.
            if ($contact->status === NewsletterSubscriber::STATUS_ACTIVE) {
                $subscriber->unsubscribed_at = null;
            }

            $subscriber->save();

            $definitions = $list->fields()->get()->keyBy('key');

            foreach ($contact->fields as $key => $value) {
                $definition = $definitions->get($key);

                if (! $definition) {
                    continue;
                }

                NewsletterFieldValue::writeValue($subscriber, $definition, $value);
            }

            $this->recordImportedConsent($subscriber, $contact);

            if ($wordtGeheractiveerd) {
                $this->recordReactivatedConsent($subscriber, $contact);
            }

            // Alleen een gebeurtenis als er werkelijk iets veranderde. Bij een
            // herhaalde overname van 2445 contacten zou de tijdlijn anders elke
            // ronde volstromen met regels die niets vertellen.
            if ($isNieuw || $vorigeStatus !== $subscriber->status) {
                NewsletterSubscriberEvent::create([
                    'newsletter_subscriber_id' => $subscriber->id,
                    'type' => NewsletterSubscriberEvent::TYPE_IMPORTED,
                    'payload' => [
                        'source' => $contact->source,
                        'origin' => $contact->origin,
                        'from' => $vorigeStatus,
                        'to' => $subscriber->status,
                    ],
                ]);
            }

            return $subscriber;
        });
    }

    /**
     * Neemt een reeks contacten over. Eén transactie per contact, niet per
     * ronde: bij duizenden contacten mag één rot adres de hele overname niet
     * omgooien, en een half afgebroken ronde is beter dan geen ronde.
     *
     * @param iterable<ImportedContact> $contacts
     */
    public function importMany(NewsletterList $list, iterable $contacts): ImportResult
    {
        $result = new ImportResult();

        foreach ($contacts as $contact) {
            $bestond = NewsletterSubscriber::where('newsletter_list_id', $list->id)
                ->where('email', mb_strtolower(trim($contact->email)))
                ->exists();

            try {
                $this->import($list, $contact);
            } catch (\InvalidArgumentException $e) {
                $result->skip($contact->email, $e->getMessage());

                continue;
            }

            $bestond ? $result->updated++ : $result->created++;
        }

        return $result;
    }

    /**
     * Het bewijs draagt de oorspronkelijke aanmelddatum en niet vandaag, want
     * dat is wat er werkelijk gebeurd is. Een tweede overname van hetzelfde
     * feit voegt niets toe, dus schrijven we alleen als er nog geen regel met
     * diezelfde datum staat.
     *
     * Geeft de bron geen datum, dan valt dit terug op subscribed_at van het
     * contact zelf (altijd gevuld door het model) en pas als laatste redmiddel
     * op now(). Zonder die tussenstap levert elke ronde een nieuwe datum en
     * dus een nieuwe bewijsregel op, terwijl er die dag niemand toestemming gaf.
     */
    private function recordImportedConsent(NewsletterSubscriber $subscriber, ImportedContact $contact): void
    {
        $givenAt = $contact->subscribedAt ?? $subscriber->subscribed_at ?? now();

        if ($subscriber->consents()->where('given_at', $givenAt)->exists()) {
            return;
        }

        NewsletterConsent::create([
            'newsletter_subscriber_id' => $subscriber->id,
            'given_at' => $givenAt,
            'ip' => $contact->ip,
            'source' => $contact->source,
            'consent_text' => $contact->consentText,
        ]);
    }

    /**
     * Extra bewijsregel bij een heractivering via een overname. Het
     * oorspronkelijke bewijs (recordImportedConsent) dekt de heractivering
     * niet, dus komt dit erbij in plaats van in de plaats daarvan: bewijs
     * wordt aangevuld, nooit vervangen.
     */
    private function recordReactivatedConsent(NewsletterSubscriber $subscriber, ImportedContact $contact): void
    {
        $bron = $contact->source ?? 'de bron';

        NewsletterConsent::create([
            'newsletter_subscriber_id' => $subscriber->id,
            'given_at' => now(),
            'ip' => $contact->ip,
            'source' => $contact->source,
            'consent_text' => 'Op grond van ' . $bron . ' opnieuw actief gezet op ' . now()->format('d-m-Y') . '.',
        ]);
    }

    /**
     * De enige plek waar een statusovergang van een contact wordt afgehandeld.
     * Beide beheerschermen gaan hier doorheen: stond deze logica per scherm
     * overgeschreven, dan lopen ze uit elkaar zodra er één wordt bijgewerkt,
     * en dat is precies wat er gebeurd was.
     *
     * Geeft terug of er daadwerkelijk iets veranderd is.
     */
    public function changeStatus(
        NewsletterSubscriber $subscriber,
        string $status,
        ?string $consentText = null,
        ?string $source = null,
        ?string $ip = null,
    ): bool {
        $previousStatus = $subscriber->status;

        // Geen overgang, dus ook geen gebeurtenis: de tijdlijn mag niet
        // vollopen met regels die niets vertellen.
        if ($previousStatus === $status) {
            return false;
        }

        // Statuswijziging, gebeurtenis en toestemmingsbewijs horen bij elkaar:
        // een heractivering die halverwege afbreekt mag geen actief contact
        // achterlaten waarvan het nieuwste bewijs van vóór de uitschrijving is.
        DB::transaction(function () use ($subscriber, $status, $previousStatus, $consentText, $source, $ip): void {
            $subscriber->status = $status;

            if ($status === NewsletterSubscriber::STATUS_UNSUBSCRIBED) {
                $subscriber->unsubscribed_at = now();
            }

            // Heractivering: de uitschrijfdatum moet mee terug. Anders staat er
            // een actief contact met een gevulde unsubscribed_at ernaast en is
            // niet meer te zien welke van de twee waar is.
            if ($status === NewsletterSubscriber::STATUS_ACTIVE) {
                $subscriber->unsubscribed_at = null;
            }

            $subscriber->save();

            if ($status === NewsletterSubscriber::STATUS_ACTIVE) {
                // Activeren is niet hetzelfde als aanmaken, maar de invariant is
                // dezelfde: een actief contact heeft altijd een geldig
                // toestemmingsbewijs. Het bewijs van vóór de uitschrijving dekt
                // deze heractivering niet, dus schrijven we een nieuwe regel.
                $this->recordSubscribed(
                    subscriber: $subscriber,
                    payload: ['from' => $previousStatus, 'to' => $status, 'source' => $source],
                    source: $source,
                    consentText: $consentText,
                    ip: $ip,
                );

                return;
            }

            NewsletterSubscriberEvent::create([
                'newsletter_subscriber_id' => $subscriber->id,
                'type' => match ($status) {
                    NewsletterSubscriber::STATUS_UNSUBSCRIBED => NewsletterSubscriberEvent::TYPE_UNSUBSCRIBED,
                    NewsletterSubscriber::STATUS_CLEANED => NewsletterSubscriberEvent::TYPE_CLEANED,
                    default => NewsletterSubscriberEvent::TYPE_UPDATED,
                },
                'payload' => ['from' => $previousStatus, 'to' => $status],
            ]);
        });

        // Na de transactie, en alleen bij een echte overgang: hierboven is al
        // afgevangen dat een gelijkblijvende status niets oplevert.
        if ($status === NewsletterSubscriber::STATUS_ACTIVE) {
            NewsletterSubscribedEvent::dispatch($subscriber);
        }

        if ($status === NewsletterSubscriber::STATUS_UNSUBSCRIBED) {
            NewsletterUnsubscribedEvent::dispatch($subscriber);
        }

        return true;
    }

    /**
     * Verwerkt een bewerking vanuit een beheerscherm. Beide schermen
     * (SubscribersRelationManager en EditNewsletterSubscriber) gaan hierdoorheen,
     * zodat het e-mailslot, de bron-gebeurtenis en de statusovergang niet per
     * scherm anders kunnen uitpakken.
     *
     * @param array<string, mixed> $data
     */
    public function updateFromAdmin(NewsletterSubscriber $subscriber, array $data): NewsletterSubscriber
    {
        // Het adres staat in beide schermen op ->disabled() en wordt daarom niet
        // gedehydreerd, maar we wissen het voor de zekerheid ook hier: een
        // gemanipuleerde client mag nooit stilletjes het adres achter het
        // toestemmingsbewijs laten verschuiven. Bovendien hangt de user_id-hook
        // alleen aan 'creating', dus een adreswijziging zou hier ook de
        // accountkoppeling laten verlopen.
        unset($data['email']);

        $status = $data['status'] ?? $subscriber->status;
        unset($data['status']);

        // Niet-gedehydreerde hulpvelden horen niet op het model thuis.
        $consentText = $data['reactivation_consent_text'] ?? null;
        unset($data['reactivation_consent_text']);

        // De zelf gedefinieerde velden komen als field_<sleutel> uit het
        // formulier. Ze zijn geen kolom op het contact, dus ze moeten er hier
        // uit voordat fill() ze als attribuut probeert te zetten.
        $fieldValues = [];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'field_')) {
                $fieldValues[substr($key, 6)] = $value;
                unset($data[$key]);
            }
        }

        // Een leeggemaakt tekstveld levert '' op, geen null. Zonder deze
        // normalisatie krijgt een contact met source=null een gebeurtenis
        // "gewijzigd van niets naar niets", en komt er '' in de kolom te staan
        // waar SourceCondition anders op matcht dan op null.
        if (array_key_exists('source', $data)) {
            $data['source'] = filled($data['source']) ? $data['source'] : null;
        }

        $previousSource = filled($subscriber->source) ? $subscriber->source : null;

        $subscriber->fill($data);

        // De bron is een segmentatie-invoer (zie SourceCondition): wie hem stil
        // wijzigt, verplaatst contacten tussen segmenten zonder spoor in de
        // tijdlijn.
        $sourceChanged = array_key_exists('source', $data)
            && (filled($subscriber->source) ? $subscriber->source : null) !== $previousSource;

        $subscriber->save();

        if ($fieldValues) {
            $definitions = $subscriber->list?->fields()->get()->keyBy('key');

            foreach ($fieldValues as $key => $value) {
                $definition = $definitions?->get($key);

                if ($definition) {
                    NewsletterFieldValue::writeValue($subscriber, $definition, $value);
                }
            }
        }

        if ($sourceChanged) {
            NewsletterSubscriberEvent::create([
                'newsletter_subscriber_id' => $subscriber->id,
                'type' => NewsletterSubscriberEvent::TYPE_UPDATED,
                'payload' => ['field' => 'source', 'from' => $previousSource, 'to' => $subscriber->source],
            ]);
        }

        $this->changeStatus(
            subscriber: $subscriber,
            status: $status,
            consentText: $consentText,
            source: $subscriber->source ?? 'handmatig',
        );

        return $subscriber;
    }

    /**
     * Legt vast dat een contact op dit moment op de lijst hoort te staan: de
     * gebeurtenis én het toestemmingsbewijs. Toestemming leggen we vast bij elke
     * aanmelding, niet alleen de eerste. Dit is wat je bij een klacht moet
     * kunnen laten zien, dus het staat op één plek.
     *
     * @param array<string, mixed> $payload
     */
    private function recordSubscribed(
        NewsletterSubscriber $subscriber,
        array $payload,
        ?string $source,
        ?string $consentText,
        ?string $ip,
    ): void {
        NewsletterSubscriberEvent::create([
            'newsletter_subscriber_id' => $subscriber->id,
            'type' => NewsletterSubscriberEvent::TYPE_SUBSCRIBED,
            'payload' => $payload,
        ]);

        NewsletterConsent::create([
            'newsletter_subscriber_id' => $subscriber->id,
            'given_at' => now(),
            'ip' => $ip,
            'source' => $source,
            'consent_text' => $consentText,
        ]);
    }
}
