<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__newsletter_consents')) {
            return;
        }

        Schema::create('dashed__newsletter_consents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('newsletter_subscriber_id')->index();
            $table->timestamp('given_at');
            $table->string('ip')->nullable();
            $table->string('source')->nullable();
            // De letterlijke tekst waar iemand mee akkoord ging. Bewust een kopie
            // en geen verwijzing, zodat een latere wijziging van die tekst het
            // bewijs niet met terugwerkende kracht verandert.
            $table->text('consent_text')->nullable();
            $table->timestamps();

            // Meeverwijderen met het contact. Het bewijs bestaat om te kunnen
            // laten zien waarom iemand post van je krijgt; is het contact weg,
            // dan krijgt niemand meer post en heeft het bewijs geen onderwerp
            // meer. Los bewaren zou juist persoonsgegevens overhouden van
            // iemand die uit het systeem verwijderd is.
            $table->foreign('newsletter_subscriber_id', 'nl_consent_subscriber_fk')
                ->references('id')
                ->on('dashed__newsletter_subscribers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__newsletter_consents');
    }
};
