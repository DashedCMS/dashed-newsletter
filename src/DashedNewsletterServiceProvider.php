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

class DashedNewsletterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('dashed-newsletter')
            ->hasMigrations([
                'create_newsletter_lists_table',
                'create_newsletter_subscribers_table',
                'create_newsletter_fields_table',
                'create_newsletter_field_values_table',
                'create_newsletter_subscriber_events_table',
                'create_newsletter_consents_table',
                'create_newsletter_segments_table',
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
