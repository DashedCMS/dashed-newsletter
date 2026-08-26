<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Een rij per klik. Zo zijn totaal, uniek en per link alle drie te
     * beantwoorden, en is later een tijdlijn te maken zonder iets om te
     * gooien. Groeit hard bij grote lijsten, vandaar PruneCampaignClicks.
     *
     * Elke foreign key krijgt een eigen naam. MySQL weigert een identifier
     * boven de 64 tekens, en de naam die Laravel zelf zou verzinnen
     * (tabel + kolom + _foreign) komt hier op 65, 70 en 75 uit. SQLite kent
     * die grens niet, dus zonder eigen namen draait de testsuite gewoon door
     * en klapt de migratie pas op een echte installatie eruit, halverwege,
     * met een tabel die al wel bestaat maar zonder sleutels.
     * MigrationIdentifierLengthTest bewaakt dit.
     */
    public function up(): void
    {
        Schema::create('dashed__newsletter_campaign_clicks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('newsletter_campaign_id');
            $table->unsignedBigInteger('newsletter_campaign_link_id');
            // Nullable: een klik uit een testmail heeft geen opgeslagen
            // ontvangerregel. Die wordt niet vastgelegd, maar de kolom hoeft
            // daar niet hard op te vertrouwen.
            $table->unsignedBigInteger('newsletter_campaign_recipient_id')->nullable();
            $table->timestamp('clicked_at');
            $table->timestamps();

            $table->foreign('newsletter_campaign_id', 'nl_clicks_campagne_fk')
                ->references('id')->on('dashed__newsletter_campaigns')
                ->cascadeOnDelete();
            $table->foreign('newsletter_campaign_link_id', 'nl_clicks_link_fk')
                ->references('id')->on('dashed__newsletter_campaign_links')
                ->cascadeOnDelete();
            $table->foreign('newsletter_campaign_recipient_id', 'nl_clicks_ontvanger_fk')
                ->references('id')->on('dashed__newsletter_campaign_recipients')
                ->cascadeOnDelete();

            // Namen ook hier expliciet, om dezelfde reden.
            $table->index(['newsletter_campaign_id', 'newsletter_campaign_link_id'], 'nl_clicks_campagne_link_index');
            $table->index(['newsletter_campaign_id', 'clicked_at'], 'nl_clicks_campagne_tijd_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__newsletter_campaign_clicks');
    }
};
