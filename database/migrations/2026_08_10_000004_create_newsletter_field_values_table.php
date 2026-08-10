<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__newsletter_field_values')) {
            return;
        }

        Schema::create('dashed__newsletter_field_values', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('newsletter_subscriber_id')->index();
            $table->unsignedBigInteger('newsletter_field_id');
            $table->text('value')->nullable();
            // Getypeerde kolommen, zodat een segment numeriek en op datum kan
            // vergelijken. Met alleen `value` is '90' groter dan '250'.
            $table->decimal('value_number', 20, 4)->nullable();
            $table->date('value_date')->nullable();
            $table->timestamps();

            $table->unique(
                ['newsletter_subscriber_id', 'newsletter_field_id'],
                'nl_value_subscriber_field_unique'
            );
            $table->index(['newsletter_field_id', 'value_number'], 'nl_value_field_number_index');
            $table->index(['newsletter_field_id', 'value_date'], 'nl_value_field_date_index');

            $table->foreign('newsletter_subscriber_id', 'nl_value_subscriber_fk')
                ->references('id')
                ->on('dashed__newsletter_subscribers')
                ->cascadeOnDelete();
            $table->foreign('newsletter_field_id', 'nl_value_field_fk')
                ->references('id')
                ->on('dashed__newsletter_fields')
                ->cascadeOnDelete();
        });

        // De tekstkolom apart, met lengte, want een volledige TEXT-index mag niet
        // in MySQL. Op SQLite (o.a. de testsuite) wordt deze index overgeslagen.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX nl_value_field_value_index ON dashed__newsletter_field_values (newsletter_field_id, value(191))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__newsletter_field_values');
    }
};
