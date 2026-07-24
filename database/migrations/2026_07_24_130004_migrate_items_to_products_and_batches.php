<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Splits every legacy `items` row (product + single batch, flattened)
     * into one `products` row and one `product_batches` row, then repoints
     * every table that referenced `items.id` at the new tables. The `items`
     * table itself is left in place, untouched and unreferenced, so no data
     * is destroyed by this migration.
     */
    public function up(): void
    {
        $this->backfillGenericNameCodes();

        $itemToProduct = [];
        $itemToBatch = [];

        $items = DB::table('items')->orderBy('id')->get();
        $sequence = 1;

        foreach ($items as $item) {
            $code = str_pad((string) $sequence++, 5, '0', STR_PAD_LEFT);

            $productId = DB::table('products')->insertGetId([
                'code' => $code,
                'generic_name_id' => $item->generic_name_id,
                'brand_name' => $item->brand_name,
                'item_name' => $item->item_name,
                'category_id' => $item->category_id,
                'supplier_id' => $item->supplier_id,
                'description' => $item->description,
                'barcode' => $item->serial_number,
                'unit_cost' => $item->unit_cost,
                'unit_price_percent' => null,
                'unit_price' => $item->unit_price,
                'wholesale_percent' => $item->wholesale_percent,
                'wholesale_price' => $item->wholesale_price,
                'price_1_percent' => $item->price_1_percent,
                'price_1' => $item->price_1,
                'price_2_percent' => $item->price_2_percent,
                'price_2' => $item->price_2,
                'price_3_percent' => $item->price_3_percent,
                'price_3' => $item->price_3,
                'location' => $item->location,
                'low_stock_threshold' => $item->low_stock_threshold,
                'image' => $item->image,
                'tax_id' => $item->tax_id,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ]);

            $batchId = DB::table('product_batches')->insertGetId([
                'product_id' => $productId,
                'batch_no' => $item->batch_no,
                'expiration_date' => $item->expiration_date,
                'qty' => $item->quantity,
                'reserved_qty' => 0,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ]);

            $itemToProduct[$item->id] = $productId;
            $itemToBatch[$item->id] = $batchId;
        }

        $this->repointToBatch('stock_movements', $itemToBatch);
        $this->repointToBatch('delivery_receipt_items', $itemToBatch);
        $this->repointToBatch('goods_receipt_items', $itemToBatch);
        $this->repointToBatch('purchases', $itemToBatch);
        $this->repointToBatch('sales', $itemToBatch);
        $this->repointToBatch('return_items', $itemToBatch);

        $this->repointToProduct('purchase_order_items', $itemToProduct);
    }

    protected function backfillGenericNameCodes(): void
    {
        $genericNames = DB::table('generic_names')->whereNull('code')->orderBy('id')->get();
        $sequence = 1;

        foreach ($genericNames as $genericName) {
            DB::table('generic_names')->where('id', $genericName->id)->update([
                'code' => str_pad((string) $sequence++, 5, '0', STR_PAD_LEFT),
            ]);
        }
    }

    /**
     * Add a nullable product_batch_id column to $table, backfill it from
     * the item_id -> product_batch_id map, then drop the old item_id FK/column.
     */
    protected function repointToBatch(string $table, array $itemToBatch): void
    {
        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->foreignId('product_batch_id')->nullable()->after('item_id')
                ->constrained()->nullOnDelete();
        });

        foreach ($itemToBatch as $itemId => $batchId) {
            DB::table($table)->where('item_id', $itemId)->update(['product_batch_id' => $batchId]);
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropForeign(['item_id']);
            $blueprint->dropColumn('item_id');
        });
    }

    /**
     * Add a nullable product_id column to $table, backfill it from the
     * item_id -> product_id map, then drop the old item_id FK/column.
     */
    protected function repointToProduct(string $table, array $itemToProduct): void
    {
        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->foreignId('product_id')->nullable()->after('item_id')
                ->constrained()->nullOnDelete();
        });

        foreach ($itemToProduct as $itemId => $productId) {
            DB::table($table)->where('item_id', $itemId)->update(['product_id' => $productId]);
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropForeign(['item_id']);
            $blueprint->dropColumn('item_id');
        });
    }

    public function down(): void
    {
        // Irreversible: items/product split cannot be cleanly un-flattened
        // once independent batches or products have diverged post-migration.
        throw new \RuntimeException('This migration cannot be reversed.');
    }
};
