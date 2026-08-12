<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__newsletter_campaigns')) {
            return;
        }

        Schema::create('dashed__newsletter_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id')->nullable()->index();
            $table->unsignedBigInteger('newsletter_list_id')->index();
            $table->unsignedBigInteger('newsletter_segment_id')->nullable()->index();
            $table->string('name');
            $table->string('subject')->nullable();
            $table->string('preheader')->nullable();
            $table->longText('content')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('reply_to_email')->nullable();
            $table->string('status')->default('concept')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();

            $table->foreign('newsletter_list_id', 'nl_campaign_list_fk')
                ->references('id')->on('dashed__newsletter_lists')->cascadeOnDelete();
            // Een verwijderd segment mag de campagne niet meenemen: de
            // verzendgeschiedenis moet blijven staan.
            $table->foreign('newsletter_segment_id', 'nl_campaign_segment_fk')
                ->references('id')->on('dashed__newsletter_segments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__newsletter_campaigns');
    }
};
