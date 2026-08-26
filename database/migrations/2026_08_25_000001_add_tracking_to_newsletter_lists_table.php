<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__newsletter_lists', function (Blueprint $table): void {
            // Standaard uit. Wie zijn contacten wil volgen zet dat zelf aan;
            // dat hoort een besluit van een beheerder te zijn en niet iets
            // wat een update stilzwijgend voor hem regelt.
            $table->boolean('track_opens')->default(false);
            $table->boolean('track_clicks')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('dashed__newsletter_lists', function (Blueprint $table): void {
            $table->dropColumn(['track_opens', 'track_clicks']);
        });
    }
};
