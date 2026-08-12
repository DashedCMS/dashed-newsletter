<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__newsletter_campaign_recipients')) {
            return;
        }

        Schema::create('dashed__newsletter_campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('newsletter_campaign_id');
            $table->unsignedBigInteger('newsletter_subscriber_id');
            // Het adres waar deze mail werkelijk heen ging. Blijft kloppen ook
            // als het contact later wordt aangepast.
            $table->string('email');
            $table->string('status')->default('pending')->index();
            $table->string('skip_reason')->nullable();
            // Elke bounce- en klacht-webhook (SuppressBouncedAddress) zoekt
            // hierop om van een mail terug naar zijn campagne te vinden, dus
            // een index is geen 'misschien later' meer.
            $table->unsignedBigInteger('sent_email_id')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['newsletter_campaign_id', 'newsletter_subscriber_id'],
                'nl_recipient_campaign_subscriber_unique'
            );
            $table->index(['newsletter_campaign_id', 'status'], 'nl_recipient_campaign_status_index');

            $table->foreign('newsletter_campaign_id', 'nl_recipient_campaign_fk')
                ->references('id')->on('dashed__newsletter_campaigns')->cascadeOnDelete();
            $table->foreign('newsletter_subscriber_id', 'nl_recipient_subscriber_fk')
                ->references('id')->on('dashed__newsletter_subscribers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__newsletter_campaign_recipients');
    }
};
