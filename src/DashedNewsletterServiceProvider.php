<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter;

use Dashed\DashedCore\Models\User;
use Spatie\LaravelPackageTools\Package;
use Dashed\DashedNewsletter\Facades\Newsletter;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedNewsletter\Listeners\LinkSubscriberToUser;
use Dashed\DashedNewsletter\Segments\SegmentConditionRegistry;
use Dashed\DashedNewsletter\Classes\FormApis\NewsletterListAPI;
use Dashed\DashedNewsletter\Segments\Conditions\FieldCondition;
use Dashed\DashedNewsletter\Segments\Conditions\SourceCondition;
use Dashed\DashedNewsletter\Segments\Conditions\StatusCondition;
use Dashed\DashedNewsletter\Segments\Conditions\SubscribedAtCondition;
use Dashed\DashedNewsletter\Filament\Pages\Settings\DashedNewsletterSettingsPage;

class DashedNewsletterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('dashed-newsletter')
            // De tijdstempels in deze namen zijn geen sier en de volgorde van
            // deze lijst is niet wat telt. Laravel's migrator sorteert alle
            // geregistreerde migraties op bestandsnaam, dus zonder voorvoegsel
            // draait consents vóór subscribers en klapt de foreign key eruit
            // met "Failed to open the referenced table". Op SQLite valt dat niet
            // op: die accepteert een verwijzing naar een tabel die nog niet
            // bestaat. Nummers dus in afhankelijkheidsvolgorde laten staan.
            ->hasMigrations([
                '2026_08_10_000001_create_newsletter_lists_table',
                '2026_08_10_000002_create_newsletter_subscribers_table',
                '2026_08_10_000003_create_newsletter_fields_table',
                '2026_08_10_000004_create_newsletter_field_values_table',
                '2026_08_10_000005_create_newsletter_subscriber_events_table',
                '2026_08_10_000006_create_newsletter_consents_table',
                '2026_08_10_000007_create_newsletter_segments_table',
                '2026_08_11_000001_make_newsletter_list_from_email_nullable',
            ])
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SegmentConditionRegistry::class);
        $this->app->singleton('newsletter', fn () => new NewsletterManager());
    }

    public function packageBooted(): void
    {
        User::created(fn (User $user) => app(LinkSubscriberToUser::class)->handle($user));
        User::updated(function (User $user): void {
            if ($user->wasChanged('email')) {
                app(LinkSubscriberToUser::class)->handle($user);
            }
        });

        cms()->builder('plugins', array_merge(cms()->builder('plugins') ?: [], [
            new DashedNewsletterPlugin(),
        ]));

        cms()->registerSettingsPage(
            DashedNewsletterSettingsPage::class,
            'Nieuwsbrief',
            'envelope-open',
            'Beheer de standaardlijst en het overnemen van contacten'
        );

        cms()->registerSettingsDocs(
            page: DashedNewsletterSettingsPage::class,
            title: 'Nieuwsbrief instellingen',
            intro: 'De nieuwsbrief werkt met lijsten. Elke lijst heeft zijn eigen afzender, velden, segmenten en contacten, en die stel je bij de lijst zelf in onder Communicatie. Op deze pagina staat alleen wat over de lijsten heen gaat.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => 'De standaardlijst van een site. Die staat vooraf gekozen als je een formulier aan de nieuwsbrief koppelt, en aanmeldingen die geen lijst meegeven komen daarop uit. Laat je hem leeg, dan kies je per keer.',
                ],
                [
                    'heading' => 'Contacten overnemen',
                    'body' => 'Heb je een koppeling met een aanbieder waar al contacten staan, dan verschijnt daar bovenaan een knop voor. Uitgeschreven contacten blijven daarbij uitgeschreven, en bij de aanbieder zelf verandert er niets.',
                ],
            ],
            fields: [
                'Standaardlijst' => 'De lijst waar aanmeldingen op uitkomen als er geen lijst is meegegeven.',
            ],
        );

        Newsletter::registerSegmentCondition(new FieldCondition());
        Newsletter::registerSegmentCondition(new StatusCondition());
        Newsletter::registerSegmentCondition(new SourceCondition());
        Newsletter::registerSegmentCondition(new SubscribedAtCondition());

        forms()->builder(
            'apiClasses',
            array_merge(forms()->builder('apiClasses'), [
                'newsletter_list' => [
                    'name' => 'Nieuwsbrieflijst in het CMS',
                    'class' => NewsletterListAPI::class,
                ],
            ])
        );
    }
}
