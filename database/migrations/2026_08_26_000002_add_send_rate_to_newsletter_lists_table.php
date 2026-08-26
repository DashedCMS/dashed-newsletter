<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Het verzendtempo van een lijst, in mails per minuut.
     *
     * Nullable en niet nul als standaard: leeg betekent "volg de instelling
     * van de site", nul betekent "uitdrukkelijk geen begrenzing". Dat verschil
     * is er een die een beheerder moet kunnen maken, en met een nul als
     * standaardwaarde is hij niet te maken.
     */
    public function up(): void
    {
        Schema::table('dashed__newsletter_lists', function (Blueprint $table): void {
            $table->unsignedInteger('send_rate_per_minute')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dashed__newsletter_lists', function (Blueprint $table): void {
            $table->dropColumn('send_rate_per_minute');
        });
    }
};
