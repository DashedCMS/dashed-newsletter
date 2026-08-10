<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__newsletter_lists')) {
            return;
        }

        Schema::create('dashed__newsletter_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id')->nullable()->index();
            $table->string('name');
            $table->string('locale')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email');
            $table->string('reply_to_email')->nullable();
            // single of double. De dubbele variant is nog niet te kiezen in de UI,
            // want er is nog niets dat de bevestigingsmail kan versturen.
            $table->string('opt_in_type')->default('single');
            $table->boolean('notify_on_subscribe')->default(false);
            $table->boolean('notify_on_unsubscribe')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__newsletter_lists');
    }
};
