<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * De cijfers van een campagne wonen hier en niet in dashed__sent_emails,
     * want die tabel wordt na negentig dagen opgeruimd
     * (PruneSentEmailsCommand). Statistieken die daaruit gejoind worden staan
     * na drie maanden stilzwijgend op nul.
     */
    public function up(): void
    {
        Schema::table('dashed__newsletter_campaign_recipients', function (Blueprint $table): void {
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_reason')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('unsubscribed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dashed__newsletter_campaign_recipients', function (Blueprint $table): void {
            $table->dropColumn([
                'delivered_at', 'bounced_at', 'bounce_reason', 'complained_at',
                'opened_at', 'open_count', 'clicked_at', 'click_count', 'unsubscribed_at',
            ]);
        });
    }
};
