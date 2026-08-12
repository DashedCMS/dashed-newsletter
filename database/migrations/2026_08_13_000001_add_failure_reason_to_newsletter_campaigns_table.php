<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Waarom een campagne op 'failed' staat, bewaard bij de campagne zelf. Tot
     * nu toe belandde die reden alleen in de uitvoer van schedule:run, dus in
     * het beheer stond er niets dan de statusnaam "Mislukt" en moest een
     * beheerder in de serverlogs zoeken om te weten wat hij moest repareren.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dashed__newsletter_campaigns')) {
            return;
        }

        Schema::table('dashed__newsletter_campaigns', function (Blueprint $table): void {
            $table->text('failure_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__newsletter_campaigns')) {
            return;
        }

        Schema::table('dashed__newsletter_campaigns', function (Blueprint $table): void {
            $table->dropColumn('failure_reason');
        });
    }
};
