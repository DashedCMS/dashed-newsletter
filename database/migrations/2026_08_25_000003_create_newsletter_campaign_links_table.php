<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * De links van een campagne. Dit is de bron van waarheid voor de doelURL
     * van de klikroute: die mag nooit uit het verzoek komen, anders is de
     * klikroute een open redirect.
     */
    public function up(): void
    {
        Schema::create('dashed__newsletter_campaign_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('newsletter_campaign_id')
                ->constrained('dashed__newsletter_campaigns')
                ->cascadeOnDelete();
            // Een URL kan lang zijn, en een index op een text-kolom kan niet
            // in MySQL. Vandaar string met een lengte die nog indexeerbaar is.
            $table->string('url', 500);
            $table->timestamps();

            $table->index(['newsletter_campaign_id'], 'nl_links_campagne_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__newsletter_campaign_links');
    }
};
