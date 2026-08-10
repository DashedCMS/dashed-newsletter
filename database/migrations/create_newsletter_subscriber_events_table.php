<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__newsletter_subscriber_events')) {
            return;
        }

        Schema::create('dashed__newsletter_subscriber_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('newsletter_subscriber_id');
            // MySQL index name limit is 64 chars; auto-generated name is too long.
            $table->index('newsletter_subscriber_id', 'nl_event_subscriber_id_index');
            $table->string('type')->index();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('newsletter_subscriber_id', 'nl_event_subscriber_fk')
                ->references('id')
                ->on('dashed__newsletter_subscribers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__newsletter_subscriber_events');
    }
};
