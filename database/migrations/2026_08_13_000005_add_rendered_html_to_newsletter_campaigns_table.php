<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__newsletter_campaigns')) {
            return;
        }

        if (Schema::hasColumn('dashed__newsletter_campaigns', 'rendered_html')) {
            return;
        }

        Schema::table('dashed__newsletter_campaigns', function (Blueprint $table): void {
            // Het sjabloon van deze verzendronde, met de plaatshouders er nog
            // in. Hier en niet in de payload van de portiejobs: bij tweehonderd
            // ontvangers per portie zou dezelfde HTML tientallen keren in de
            // wachtrij staan.
            $table->longText('rendered_html')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dashed__newsletter_campaigns', 'rendered_html')) {
            return;
        }

        Schema::table('dashed__newsletter_campaigns', function (Blueprint $table): void {
            $table->dropColumn('rendered_html');
        });
    }
};
