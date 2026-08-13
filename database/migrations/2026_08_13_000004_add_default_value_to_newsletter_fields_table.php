<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__newsletter_fields')) {
            return;
        }

        if (Schema::hasColumn('dashed__newsletter_fields', 'default_value')) {
            return;
        }

        Schema::table('dashed__newsletter_fields', function (Blueprint $table): void {
            // Zonder terugval wordt "Hallo :voornaam:," bij een contact zonder
            // voornaam "Hallo ,". Met een terugval van "daar" staat er iets
            // fatsoenlijks.
            $table->string('default_value')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dashed__newsletter_fields', 'default_value')) {
            return;
        }

        Schema::table('dashed__newsletter_fields', function (Blueprint $table): void {
            $table->dropColumn('default_value');
        });
    }
};
