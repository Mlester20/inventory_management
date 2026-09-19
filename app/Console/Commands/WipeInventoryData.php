<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WipeInventoryData extends Command
{
    protected $signature = 'inventory:wipe
        {--products : Also delete the product catalog (products table)}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe stock and all stock-related transactions (optionally the product catalog) before going live with actual inventory';

    // Order does not matter (FK checks are off), grouped for readability.
    private const TRANSACTION_TABLES = [
        'sales_quote_items', 'sales_quotes',
        'sales_order_items', 'sales_orders',
        'delivery_receipt_items', 'delivery_receipts',
        'sales', 'invoices', 'purchases', 'customer_payments',
        'purchase_order_items', 'purchase_orders',
        'goods_receipt_items', 'goods_receipts',
        'purchase_invoices', 'supplier_payments',
        'inventory_adjustment_lines', 'inventory_adjustments',
        'stock_transfer_lines', 'stock_transfers',
        'stock_disposal_lines', 'stock_disposals', 'return_items',
        'stock_movements', 'location_stocks', 'product_batches',
    ];

    public function handle(): int
    {
        $tables = self::TRANSACTION_TABLES;
        if ($this->option('products')) {
            $tables[] = 'products';
        }

        $this->warn('This permanently deletes ALL rows from:');
        foreach ($tables as $table) {
            $this->line(sprintf('  %-28s %d rows', $table, DB::table($table)->count()));
        }
        $this->info('Kept: users, locations, categories, customers, suppliers, generic_names, taxes, expenses, activity_logs' . ($this->option('products') ? '' : ', products'));

        if (! $this->option('force') && ! $this->confirm('Database: ' . DB::getDatabaseName() . ' — have you taken a backup and want to proceed?')) {
            $this->line('Aborted, nothing deleted.');
            return self::FAILURE;
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $table) {
                DB::table($table)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info('Done. Wiped ' . count($tables) . ' tables.');
        return self::SUCCESS;
    }
}
