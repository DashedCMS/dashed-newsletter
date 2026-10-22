<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__newsletter_lists') || Schema::hasColumn('dashed__newsletter_lists', 'recognize_visitors')) {
            return;
        }

        Schema::table('dashed__newsletter_lists', function (Blueprint $table) {
            // Standaard uit: een bezoeker herkennen na een klik is volgen,
            // en dat hoort een bewuste keuze te zijn met een vermelding in de
            // privacyverklaring. Zie VisitorRecognition.
            $table->boolean('recognize_visitors')->default(false);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dashed__newsletter_lists', 'recognize_visitors')) {
            return;
        }

        Schema::table('dashed__newsletter_lists', function (Blueprint $table) {
            $table->dropColumn('recognize_visitors');
        });
    }
};
