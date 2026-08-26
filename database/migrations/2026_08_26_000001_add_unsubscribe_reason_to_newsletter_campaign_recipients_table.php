<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * De reden van een afmelding hoort bij de ontvangerregel en niet bij het
     * contact. Die regel weet al bij welke campagne en welke lijst hij hoort,
     * dus "per campagne" en "per lijst" vallen er zonder extra tabel uit. Een
     * contact dat zich later opnieuw aanmeldt en nog eens afmeldt, houdt zo
     * bovendien twee losse redenen in plaats van er een te overschrijven.
     */
    public function up(): void
    {
        Schema::table('dashed__newsletter_campaign_recipients', function (Blueprint $table): void {
            $table->string('unsubscribe_reason')->nullable();
            // Vrije tekst van een bezoeker. Begrensd bij het opslaan, want een
            // tekstveld op een publieke pagina is een uitnodiging.
            $table->text('unsubscribe_comment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dashed__newsletter_campaign_recipients', function (Blueprint $table): void {
            $table->dropColumn(['unsubscribe_reason', 'unsubscribe_comment']);
        });
    }
};
