<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__newsletter_fields')) {
            return;
        }

        Schema::create('dashed__newsletter_fields', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('newsletter_list_id')->index();
            // De relatievariabele waarmee het veld later in een campagne gebruikt
            // wordt, geschreven als :voornaam: en niet als {{ voornaam }}.
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('text');
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->string('default_value')->nullable();
            $table->boolean('show_in_signup_form')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['newsletter_list_id', 'key'], 'nl_field_list_key_unique');

            $table->foreign('newsletter_list_id', 'nl_field_list_fk')
                ->references('id')
                ->on('dashed__newsletter_lists')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__newsletter_fields');
    }
};
