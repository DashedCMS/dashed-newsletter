<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Een lijst hoeft geen eigen afzenderadres meer te hebben. Laat je het leeg,
     * dan valt hij terug op het adres uit de algemene instellingen van de site
     * en anders op de mailconfiguratie. Zie NewsletterList::effectiveFromEmail().
     */
    public function up(): void
    {
        if (! Schema::hasTable('dashed__newsletter_lists')) {
            return;
        }

        Schema::table('dashed__newsletter_lists', function (Blueprint $table): void {
            $table->string('from_email')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__newsletter_lists')) {
            return;
        }

        Schema::table('dashed__newsletter_lists', function (Blueprint $table): void {
            $table->string('from_email')->nullable(false)->change();
        });
    }
};
