<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__newsletter_suppressions')) {
            return;
        }

        Schema::create('dashed__newsletter_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id')->index();
            $table->string('email');
            // bounce, complaint of manual
            $table->string('reason');
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Korte naam, anders overschrijdt de index de 64 tekens van MySQL.
            $table->unique(['site_id', 'email'], 'nl_suppression_site_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__newsletter_suppressions');
    }
};
