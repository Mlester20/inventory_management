<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Types become a managed list (add / edit / delete, like Categories) instead of free
 * text — Sir's ask, so the Customer Type drop-down always offers the same clean choices.
 * customers.customer_type stays a plain string holding the type's name; this table is the list.
 *
 * Seeded with "Walk-In" (personal use: never any withholding tax) and every type the existing
 * customers already use, so nobody's type goes missing from the drop-down.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $now = now();
        $seen = [];

        $names = array_merge(
            ['Walk-In'],
            DB::table('customers')->whereNotNull('customer_type')->where('customer_type', '!=', '')
                ->distinct()->orderBy('customer_type')->pluck('customer_type')->all(),
        );

        foreach ($names as $name) {
            $name = trim((string) $name);
            $key = mb_strtolower($name);
            // "Walk in" / "WALK-IN" in the old free text all mean the one Walk-In type.
            if (preg_replace('/[^a-z]/', '', $key) === 'walkin') {
                $name = 'Walk-In';
                $key = 'walk-in';
            }
            if ($name === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            DB::table('customer_types')->insert(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_types');
    }
};
