<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Meten staat voortaan standaard aan.
     *
     * Het stond uit, met het argument dat je je contacten niet ongemerkt hoort
     * te gaan volgen. Dat argument sneuvelde in de praktijk: er gingen 3000
     * mails uit en er werd niets gemeten, en dat merk je pas als de campagne al
     * weg is. Meten kost de ontvanger niets zichtbaars en levert de redacteur
     * alles op; wie het niet wil, zet het per lijst uit en dat blijft staan.
     *
     * Bestaande lijsten gaan mee. Ze staan allemaal nog op de oude standaard,
     * dus er is niemand die hier bewust voor uit heeft gekozen; zou dat later
     * wel zo zijn, dan is deze migratie allang gedraaid.
     */
    public function up(): void
    {
        Schema::table('dashed__newsletter_lists', function (Blueprint $table): void {
            $table->boolean('track_opens')->default(true)->change();
            $table->boolean('track_clicks')->default(true)->change();
        });

        DB::table('dashed__newsletter_lists')->update([
            'track_opens' => true,
            'track_clicks' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('dashed__newsletter_lists', function (Blueprint $table): void {
            $table->boolean('track_opens')->default(false)->change();
            $table->boolean('track_clicks')->default(false)->change();
        });
    }
};
