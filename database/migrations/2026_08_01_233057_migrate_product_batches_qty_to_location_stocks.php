<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Move every existing product_batches.qty/reserved_qty into a
     * location_stocks row against the Warehouse location — this is where
     * all pre-existing stock effectively already "was" before locations
     * existed. POS intentionally gets no row (read as 0 everywhere sums are
     * done) until a Stock Transfer actually moves something there, per the
     * "POS starts at 0 for every product" requirement.
     */
    public function up(): void
    {
        $warehouseId = DB::table('locations')->where('name', 'Warehouse')->value('id');

        $now = now();

        DB::table('product_batches')->orderBy('id')->chunkById(500, function ($batches) use ($warehouseId, $now) {
            $rows = $batches->map(fn ($batch) => [
                'location_id' => $warehouseId,
                'product_batch_id' => $batch->id,
                'qty' => $batch->qty,
                'reserved_qty' => $batch->reserved_qty,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if (! empty($rows)) {
                DB::table('location_stocks')->insert($rows);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('location_stocks')->truncate();
    }
};
