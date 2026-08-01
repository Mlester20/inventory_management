<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // The Warehouse is where bulk stock lives and where Goods
            // Receipt/Delivery Receipt/Inventory Adjustment default to;
            // every other location (POS, future branches) starts empty and
            // only gets stock via a Stock Transfer.
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        DB::table('locations')->insert([
            ['name' => 'Warehouse', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'POS', 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
