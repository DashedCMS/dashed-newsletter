<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

/**
 * Tot november 2026 had de nieuwsbrief geen eigen rechten: elke beheerder kon
 * alles. Nu de toegang alleen nog uit rollen komt, krijgt elke bestaande rol
 * de nieuwe rechten erbij, zodat niemand bij het uitrollen toegang verliest.
 * Een beheerder haalt ze daarna per rol weg waar dat moet.
 */
return new class () extends Migration {
    private const PERMISSIONS = ['view_newsletter', 'edit_newsletter', 'delete_newsletter'];

    public function up(): void
    {
        if (! Schema::hasTable('dashed__roles')) {
            return;
        }

        foreach (DB::table('dashed__roles')->get(['id', 'extra_permissions']) as $role) {
            $huidig = json_decode((string) $role->extra_permissions, true);
            $huidig = is_array($huidig) ? $huidig : [];

            $nieuw = array_values(array_unique(array_merge($huidig, self::PERMISSIONS)));

            if ($nieuw !== $huidig) {
                DB::table('dashed__roles')->where('id', $role->id)->update(['extra_permissions' => json_encode($nieuw)]);
            }
        }
    }

    public function down(): void
    {
        // Bewust niets: na up() is niet meer te zien welke rol deze rechten al
        // had, en wegnemen zou toegang afpakken die iemand bewust gaf.
    }
};
