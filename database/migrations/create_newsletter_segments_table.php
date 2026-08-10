<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__newsletter_segments')) {
            return;
        }

        Schema::create('dashed__newsletter_segments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('newsletter_list_id')->index();
            $table->string('name');
            $table->json('rules')->nullable();
            $table->timestamps();

            $table->foreign('newsletter_list_id', 'nl_segment_list_fk')
                ->references('id')
                ->on('dashed__newsletter_lists')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__newsletter_segments');
    }
};
