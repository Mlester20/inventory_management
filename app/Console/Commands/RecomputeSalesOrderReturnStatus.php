<?php

namespace App\Console\Commands;

use App\Models\SalesOrder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RecomputeSalesOrderReturnStatus extends Command
{
    protected $signature = 'so:recompute-return-status {--dry-run} {--so=}';

    protected $description = 'Recompute Sales Order statuses for orders with returns linked through Delivery Receipts';

    public function handle(): int
    {
        $query = $this->eligibleSalesOrders();

        if ($soNo = $this->option('so')) {
            $query->where('so_no', $soNo);
        }

        $updated = 0;
        $dryRun = (bool) $this->option('dry-run');

        $query->select(['id', 'so_no', 'status'])
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$updated, $dryRun) {
                foreach ($orders as $candidate) {
                    $result = $dryRun
                        ? $this->calculateStatus($candidate->id)
                        : DB::transaction(fn () => $this->calculateStatus($candidate->id, true));

                    if (! $result || $result['old'] === $result['new']) {
                        continue;
                    }

                    $this->line("{$result['so_no']}: {$result['old']} -> {$result['new']}");
                    $updated++;
                }
            });

        if ($updated === 0) {
            $this->info('No Sales Order statuses need updating.');
        }

        if ($dryRun) {
            $this->comment('Dry run complete. No Sales Order statuses were changed.');
        }

        return self::SUCCESS;
    }

    /** Orders with an approved Return Item or a posted customer-return adjustment linked through a DR. */
    private function eligibleSalesOrders(): Builder
    {
        return SalesOrder::query()
            ->whereHas('deliveryReceipts', function (Builder $receiptQuery) {
                $receiptQuery->where(function (Builder $returnQuery) {
                    $returnQuery
                        ->whereHas('returnItems', fn (Builder $q) => $q
                            ->where('is_draft', false)
                            ->where('status', 'approved'))
                        ->orWhereHas('customerReturns', fn (Builder $q) => $q
                            ->where('status', 'posted'));
                });
            });
    }

    /** @return array{so_no:string,old:string,new:string}|null */
    private function calculateStatus(int $salesOrderId, bool $write = false): ?array
    {
        $query = SalesOrder::query();
        if ($write) {
            $query->lockForUpdate();
        }

        $salesOrder = $query->find($salesOrderId);

        if (! $salesOrder || $salesOrder->isClosed() || $salesOrder->isCancelled()) {
            return null;
        }

        $oldStatus = $salesOrder->status;
        $newStatus = $salesOrder->deliveryStatus();

        if ($write && $oldStatus !== $newStatus) {
            $salesOrder->update(['status' => $newStatus]);
        }

        return [
            'so_no' => $salesOrder->so_no,
            'old' => $oldStatus,
            'new' => $newStatus,
        ];
    }
}
