<?php

namespace App\Services;

use App\Models\ProductBatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ProductExpirationReportService
{
    /**
     * Base query for in-stock batches that have an expiration date set.
     * One row per batch (not per product) since each batch can expire
     * independently of its siblings.
     */
    protected function baseQuery(?int $categoryId = null, ?int $supplierId = null): Builder
    {
        // "In stock" here means anywhere (Warehouse or POS) — an expiring
        // batch is a concern regardless of which location it's sitting in.
        $query = ProductBatch::with(['product.category', 'product.supplier', 'locationStocks'])
            ->whereNotNull('expiration_date')
            ->whereHas('locationStocks', fn ($q) => $q->where('qty', '>', 0));

        if ($categoryId) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $categoryId));
        }

        if ($supplierId) {
            $query->whereHas('product', fn ($q) => $q->where('supplier_id', $supplierId));
        }

        return $query;
    }

    /**
     * Batches expiring within the given number of days (including already expired).
     */
    public function getExpiringItems(int $daysThreshold = 30, ?int $categoryId = null, ?int $supplierId = null, int $perPage = 15): LengthAwarePaginator
    {
        $batches = $this->baseQuery($categoryId, $supplierId)
            ->whereDate('expiration_date', '<=', Carbon::today()->addDays($daysThreshold))
            ->orderBy('expiration_date')
            ->paginate($perPage)
            ->withQueryString();

        $this->withExpirationMeta($batches->getCollection());

        return $batches;
    }

    /**
     * Batches already past their expiration date.
     */
    public function getExpiredItems(?int $categoryId = null, ?int $supplierId = null): Collection
    {
        $batches = $this->baseQuery($categoryId, $supplierId)
            ->whereDate('expiration_date', '<', Carbon::today())
            ->orderBy('expiration_date')
            ->get();

        return $this->withExpirationMeta($batches);
    }

    /**
     * Attach computed `status` and `days_remaining` attributes to each batch.
     */
    protected function withExpirationMeta(Collection $batches): Collection
    {
        $today = Carbon::today();

        return $batches->each(function (ProductBatch $batch) use ($today) {
            $batch->days_remaining = $today->diffInDays($batch->expiration_date, false);
            $batch->status = $this->statusFor($batch->expiration_date, $today);
        });
    }

    /**
     * Counts of in-stock, dated batches grouped by expiration status.
     *
     * @return array{expired: int, critical: int, warning: int, ok: int}
     */
    public function getExpirationSummary(?int $categoryId = null, ?int $supplierId = null): array
    {
        $today = Carbon::today();
        $batches = $this->baseQuery($categoryId, $supplierId)->get(['id', 'expiration_date', 'product_id']);

        $summary = ['expired' => 0, 'critical' => 0, 'warning' => 0, 'ok' => 0];

        foreach ($batches as $batch) {
            $summary[$this->statusFor($batch->expiration_date, $today)]++;
        }

        return $summary;
    }

    /**
     * Resolve the expiration status bucket for a given date.
     */
    public function statusFor(Carbon $expirationDate, ?Carbon $today = null): string
    {
        $today ??= Carbon::today();
        $daysRemaining = $today->diffInDays($expirationDate, false);

        return match (true) {
            $daysRemaining < 0 => 'expired',
            $daysRemaining <= 7 => 'critical',
            $daysRemaining <= 30 => 'warning',
            default => 'ok',
        };
    }
}
