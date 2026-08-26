<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter;

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Dashed\DashedCore\Retention\Termijn;
use Dashed\DashedCore\Retention\Retention;
use Dashed\DashedNewsletter\Facades\Newsletter;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedNewsletter\Mail\EmailBlocks\SocialBlock;
use Dashed\DashedNewsletter\Listeners\LinkSubscriberToUser;
use Dashed\DashedNewsletter\Listeners\SuppressBouncedAddress;
use Dashed\DashedNewsletter\Mail\EmailBlocks\WebVersionBlock;
use Dashed\DashedNewsletter\Mail\EmailBlocks\UnsubscribeBlock;
use Dashed\DashedNewsletter\Segments\SegmentConditionRegistry;
use Dashed\DashedNewsletter\Classes\FormApis\NewsletterListAPI;
use Dashed\DashedNewsletter\Segments\Conditions\FieldCondition;
use Dashed\DashedNewsletter\Listeners\MirrorDeliveryToRecipient;
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
                '2026_08_12_000001_create_newsletter_suppressions_table',
                '2026_08_12_000002_create_newsletter_campaigns_table',
                '2026_08_12_000003_create_newsletter_campaign_recipients_table',
                '2026_08_12_000004_add_sent_email_index_to_newsletter_campaign_recipients_table',
                '2026_08_13_000001_add_failure_reason_to_newsletter_campaigns_table',
                '2026_08_13_000002_add_blocks_to_newsletter_campaigns_table',
                '2026_08_13_000003_add_branding_to_newsletter_lists_table',
                '2026_08_13_000005_add_rendered_html_to_newsletter_campaigns_table',
                '2026_08_25_000001_add_tracking_to_newsletter_lists_table',
                '2026_08_25_000002_add_engagement_to_newsletter_campaign_recipients_table',
                '2026_08_25_000003_create_newsletter_campaign_links_table',
                '2026_08_25_000004_create_newsletter_campaign_clicks_table',
                '2026_08_26_000001_add_unsubscribe_reason_to_newsletter_campaign_recipients_table',
                '2026_08_26_000002_add_send_rate_to_newsletter_lists_table',
                '2026_08_26_000003_default_newsletter_tracking_to_on',
            ])
            ->runsMigrations()
            ->hasConfigFile()
            ->hasViews('dashed-newsletter')
            ->hasRoute('frontend')
            ->hasRoute('mobile-api')
            ->hasCommand(\Dashed\DashedNewsletter\Commands\SendScheduledCampaigns::class)
            ->hasCommand(\Dashed\DashedNewsletter\Commands\PruneCampaignClicks::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SegmentConditionRegistry::class);
        $this->app->singleton('newsletter', fn () => new NewsletterManager());
        $this->app->singleton(\Dashed\DashedNewsletter\Ai\SearchToolRegistry::class);
    }

    public function packageBooted(): void
    {
        $this->app->booted(function (): void {
            app(\Illuminate\Console\Scheduling\Schedule::class)
                ->command('dashed:send-scheduled-campaigns')
                ->everyMinute()
                ->withoutOverlapping();
        });

        // Op klassenaam luisteren zodat dit pakket geen harde afhankelijkheid
        // op de gebeurtenisklassen krijgt als dashed-core ouder is.
        Event::listen('Dashed\\DashedCore\\Events\\SentEmailBouncedEvent', [SuppressBouncedAddress::class, 'bounced']);
        Event::listen('Dashed\\DashedCore\\Events\\SentEmailComplainedEvent', [SuppressBouncedAddress::class, 'complained']);

        // Zelfde vorm, op klassenaam als string: SentEmailDeliveredEvent is
        // nieuw, dus dit pakket kan tegen een oudere dashed-core draaien die
        // hem nog niet kent.
        Event::listen('Dashed\\DashedCore\\Events\\SentEmailDeliveredEvent', [MirrorDeliveryToRecipient::class, 'delivered']);
        Event::listen('Dashed\\DashedCore\\Events\\SentEmailBouncedEvent', [MirrorDeliveryToRecipient::class, 'bounced']);
        Event::listen('Dashed\\DashedCore\\Events\\SentEmailComplainedEvent', [MirrorDeliveryToRecipient::class, 'complained']);

        User::created(fn (User $user) => app(LinkSubscriberToUser::class)->handle($user));
        User::updated(function (User $user): void {
            if ($user->wasChanged('email')) {
                app(LinkSubscriberToUser::class)->handle($user);
            }
        });

        cms()->builder('plugins', array_merge(cms()->builder('plugins') ?: [], [
            new DashedNewsletterPlugin(),
        ]));

        // Guard voor het geval dit pakket ooit tegen een oudere dashed-core
        // draait die emailBlock() nog niet kent. Zonder guard crasht de hele
        // boot van de applicatie, en dat is erger dan een niet-geregistreerd
        // afmeldblok.
        if (method_exists(cms(), 'emailBlock')) {
            cms()->emailBlock('unsubscribe', UnsubscribeBlock::class);
            cms()->emailBlock('social', SocialBlock::class);
            cms()->emailBlock('web-version', WebVersionBlock::class);
        }

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

        if (class_exists(\Dashed\DashedMobileApi\MobileApiRegistry::class)) {
            /** @var \Dashed\DashedMobileApi\MobileApiRegistry $mobileApi */
            $mobileApi = $this->app->make(\Dashed\DashedMobileApi\MobileApiRegistry::class);

            $version = \Composer\InstalledVersions::isInstalled('dashed/dashed-newsletter')
                ? \Composer\InstalledVersions::getPrettyVersion('dashed/dashed-newsletter')
                : null;
            $mobileApi->registerCapability('newsletter', ['version' => $version]);

            $mobileApi->registerAbilities(['newsletter.read', 'newsletter.write']);
            $mobileApi->registerRoleAbilities([
                'eigenaar' => ['newsletter.read', 'newsletter.write'],
                'admin' => ['newsletter.read', 'newsletter.write'],
                'shopbeheerder' => ['newsletter.read', 'newsletter.write'],
                'read-only' => ['newsletter.read'],
            ]);
        }

        self::registreerBewaartermijnen();
    }

    /**
     * De campagnekliks aanmelden bij het bewaartermijnenregister.
     *
     * Statisch en apart van packageBooted(), zodat een test hem opnieuw kan
     * aanroepen na app(RetentionRegistry::class)->flush().
     *
     * Kale Nederlandse tekst, geen __(): dit pakket staat niet in
     * tools/i18n/migrated.txt.
     */
    public static function registreerBewaartermijnen(): void
    {
        cms()->registerRetention(
            Retention::make('campaign_clicks')
                ->label('Kliks in nieuwsbrieven')
                ->pakket('dashed-newsletter', 'Nieuwsbrief')
                ->tabel('dashed__newsletter_campaign_clicks')
                ->termijn(
                    // Gemeten vanaf clicked_at en niet vanaf created_at: dat is
                    // de kolom waar het oude command op stond en de enige die
                    // zegt wanneer er echt geklikt is.
                    //
                    // Een lege env-regel geeft hier een standaard van nul.
                    // Termijn::waarde() laat dat niet door en gooit dan, zodat
                    // het opruimen faalt in plaats van elke klik te wissen.
                    Termijn::make('campaign_clicks', fn () => (int) config('dashed-newsletter.clicks.retention_days', 365), 'clicked_at')
                        ->label('Kliks bewaren (dagen)')
                        ->uitleg('De losse kliks per ontvanger. De totalen per campagne blijven staan. Standaard: 365 dagen.')
                )
        );
    }
}
