<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__newsletter_lists')) {
            return;
        }

        Schema::table('dashed__newsletter_lists', function (Blueprint $table): void {
            // Header en footer zijn zelf bloklijsten, zodat je er hetzelfde
            // mee kunt als met de campagne zelf. Ze staan op de lijst en niet
            // op de campagne omdat een redacteur ze één keer inricht en daarna
            // met rust laat.
            if (! Schema::hasColumn('dashed__newsletter_lists', 'header_blocks')) {
                $table->json('header_blocks')->nullable();
            }

            if (! Schema::hasColumn('dashed__newsletter_lists', 'footer_blocks')) {
                $table->json('footer_blocks')->nullable();
            }

            // Leeg betekent: pak wat er bij E-mail instellingen staat. Zo kan
            // een B2B-lijst een eigen kleur voeren zonder de instellingen van
            // de hele site om te gooien.
            foreach (['mail_logo', 'mail_primary_color', 'mail_text_color', 'mail_background_color'] as $kolom) {
                if (! Schema::hasColumn('dashed__newsletter_lists', $kolom)) {
                    $table->string($kolom)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__newsletter_lists', function (Blueprint $table): void {
            $table->dropColumn(['header_blocks', 'footer_blocks', 'mail_logo', 'mail_primary_color', 'mail_text_color', 'mail_background_color']);
        });
    }
};
