<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The same Generic Description can legitimately come from the manufacturer in
 * more than one packaging (Sir's real case: "ABG CONTROL SOLUTION" (MISSION)
 * as both a BX and a PC) — each packaging needs its own generic_names row so
 * its own Unit is actually stored (Unit lives on generic_names, shared by every
 * brand under it; Product itself has no unit column). The old unique index
 * blocked a second row with the same name outright, regardless of Unit.
 *
 * Uniqueness moves from "generic_name alone" to "generic_name + unit" — a
 * true duplicate (same name AND same unit) is still rejected exactly as
 * before; only a same-name-different-unit combination is now allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generic_names', function (Blueprint $table) {
            $table->dropUnique('generic_names_generic_name_unique');
            $table->unique(['generic_name', 'unit'], 'generic_names_generic_name_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::table('generic_names', function (Blueprint $table) {
            $table->dropUnique('generic_names_generic_name_unit_unique');
            $table->unique('generic_name', 'generic_names_generic_name_unique');
        });
    }
};
