<?php

namespace App\Console\Commands;

use App\Models\DeliveryReceipt;
use App\Services\DeliveryReceiptService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Repair for a Delivery Receipt that was cancelled back when Cancel only changed the status: its goods are put back
 * in the Warehouse and its quantities come off the Sales Order. Shows what it would do unless --apply is given.
 */
class ReverseCancelledDeliveryReceipt extends Command
{
    protected $signature = 'dr:reverse-cancelled
        {dr_no : The cancelled Delivery Receipt, e.g. DR-2026-00005}
        {--apply : Do it (without this, only shows what would change)}';

    protected $description = 'Put a cancelled Delivery Receipt\'s goods back in the Warehouse and lower the Sales Order\'s Delivered quantities';

    public function handle(DeliveryReceiptService $service): int
    {
        $dr = DeliveryReceipt::with('items.productBatch.product', 'items.salesOrderItem.genericName', 'salesOrder')
            ->where('dr_no', $this->argument('dr_no'))->first();

        if (! $dr) {
            $this->error('No Delivery Receipt with that number.');

            return self::FAILURE;
        }

        if (! $dr->isCancelled()) {
            $this->error("{$dr->dr_no} is not cancelled (status: {$dr->status}). Cancel it from the screen instead.");

            return self::FAILURE;
        }

        if ($dr->stock_reversed_at) {
            $this->warn("{$dr->dr_no} was already put back on {$dr->stock_reversed_at}. Nothing to do.");

            return self::SUCCESS;
        }

        $this->info("{$dr->dr_no}" . ($dr->salesOrder ? " ({$dr->salesOrder->so_no})" : ' (no Sales Order)') . ' — ' . $dr->items->count() . ' line(s):');
        $this->table(
            ['Item', 'Lot', 'Qty back to Warehouse', 'Sales Order line Delivered now'],
            $dr->items->map(fn ($line) => [
                $line->productBatch?->product?->display_description ?? '—',
                $line->batch_no ?? '—',
                $line->qty,
                $line->salesOrderItem ? $line->salesOrderItem->delivered_qty : '—',
            ])->all()
        );

        if (! $this->option('apply')) {
            $this->comment('Nothing changed. Run again with --apply to do it.');

            return self::SUCCESS;
        }

        try {
            $result = $service->reverseCancelledDeliveryReceipt($dr);
        } catch (ValidationException $e) {
            $this->error($e->validator->errors()->first());

            return self::FAILURE;
        }

        $this->info("Done: {$result['units']} unit(s) back in the Warehouse"
            . ($result['orders'] ? '; Delivered lowered on ' . implode(', ', $result['orders']) : '') . '.');

        return self::SUCCESS;
    }
}
