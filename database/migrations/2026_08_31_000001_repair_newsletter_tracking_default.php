<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Herstel van lijsten die na de vorige omzetting alsnog op meten-uit
     * kwamen te staan.
     *
     * De migratie van 26 augustus zette meten standaard aan en nam bestaande
     * lijsten mee, maar het aanmaakformulier in het CMS had geen
     * ->default(true) op de schakelaars. Een Toggle zonder default staat uit
     * en stuurt zijn stand expliciet mee, dus elke lijst die sindsdien via
     * het formulier is aangemaakt kreeg meten-uit zonder dat iemand daarvoor
     * koos. Het formulier is gerepareerd; deze migratie trekt de lijsten
     * recht die er in die dagen doorheen glipten. Wie meten bewust uit had
     * gezet, valt in hetzelfde venster van vijf dagen en moet dat opnieuw
     * doen; dat is de mindere van de twee kwaden, want een stille meten-uit
     * merk je pas als een campagne al verstuurd is.
     */
    public function up(): void
    {
        DB::table('dashed__newsletter_lists')->update([
            'track_opens' => true,
            'track_clicks' => true,
        ]);
    }

    public function down(): void
    {
        // Er is geen oude stand om naar terug te keren.
    }
};
