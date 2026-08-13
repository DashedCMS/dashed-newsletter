<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__newsletter_campaigns')) {
            return;
        }

        if (Schema::hasColumn('dashed__newsletter_campaigns', 'blocks')) {
            return;
        }

        Schema::table('dashed__newsletter_campaigns', function (Blueprint $table): void {
            // De oude content-kolom blijft staan: een campagne die al
            // ingepland is met rich-editor-inhoud moet gewoon door kunnen
            // verzenden. CampaignRenderer valt daarop terug als blocks leeg is.
            $table->json('blocks')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dashed__newsletter_campaigns', 'blocks')) {
            return;
        }

        Schema::table('dashed__newsletter_campaigns', function (Blueprint $table): void {
            $table->dropColumn('blocks');
        });
    }
};
