<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * De index op sent_email_id staat ook in de create-migratie, maar die begint
 * met een hasTable-controle en slaat zichzelf dus over waar de tabel al
 * bestaat. Dat is precies elke omgeving waar iemand deze module al eens
 * gedraaid heeft voordat de index erbij kwam, en daar zou hij er dan nooit
 * komen. Elke bounce- en klachtwebhook zoekt op deze kolom om van een
 * verzonden mail terug naar zijn campagne te vinden, dus dat wil je niet als
 * volledige tabelscan.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__newsletter_campaign_recipients')) {
            return;
        }

        if ($this->heeftIndex()) {
            return;
        }

        Schema::table('dashed__newsletter_campaign_recipients', function (Blueprint $table): void {
            $table->index('sent_email_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__newsletter_campaign_recipients')) {
            return;
        }

        if (! $this->heeftIndex()) {
            return;
        }

        Schema::table('dashed__newsletter_campaign_recipients', function (Blueprint $table): void {
            $table->dropIndex(['sent_email_id']);
        });
    }

    private function heeftIndex(): bool
    {
        // Zonder deze controle klapt de migratie eruit op een verse installatie,
        // want daar heeft de create-migratie de index al gezet en weigert MySQL
        // een tweede met dezelfde naam.
        return collect(Schema::getIndexes('dashed__newsletter_campaign_recipients'))
            ->contains(fn (array $index): bool => $index['columns'] === ['sent_email_id']);
    }
};
