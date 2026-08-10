<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__newsletter_subscribers')) {
            return;
        }

        Schema::create('dashed__newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('newsletter_list_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email');
            $table->string('status')->default('active')->index();
            $table->string('source')->nullable()->index();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('unsubscribe_reason')->nullable();
            $table->timestamps();

            // Korte naam, anders overschrijdt de index de 64 tekens van MySQL.
            $table->unique(['newsletter_list_id', 'email'], 'nl_subscriber_list_email_unique');

            // Een verwijderde lijst laat anders contacten achter die nergens
            // meer bij horen: onzichtbaar in het beheer, wel meegeteld door elke
            // query die niet op lijst filtert. Naam handmatig kort gehouden,
            // want de automatische naam komt tegen de 64 tekens van MySQL aan.
            // Bewust géén sleutel op user_id: die kolom is een gevolg van een
            // adresvergelijking, geen bezit, en een contact hoort te blijven
            // bestaan als het bijbehorende account wordt verwijderd.
            $table->foreign('newsletter_list_id', 'nl_subscriber_list_fk')
                ->references('id')
                ->on('dashed__newsletter_lists')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__newsletter_subscribers');
    }
};
