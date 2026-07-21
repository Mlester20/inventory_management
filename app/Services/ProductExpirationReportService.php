<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ProductExpirationReportService
{
    /**
     * Base query for in-stock items that have an expiration date set.
     */
    protected function baseQuery(?int $categoryId = null, ?int $supplierId = null): Builder
    {
        $query = Item::with(['category', 'supplier'])
            ->whereNotNull('expiration_date')
            ->where('quantity', '>', 0);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return $query;
    }

    /**
     * Items expiring within the given number of days (including already expired).
     */
    public function getExpiringItems(int $daysThreshold = 30, ?int $categoryId = null, ?int $supplierId = null): Collection
    {
        $items = $this->baseQuery($categoryId, $supplierId)
            ->whereDate('expiration_date', '<=', Carbon::today()->addDays($daysThreshold))
            ->orderBy('expiration_date')
            ->get();

        return $this->withExpirationMeta($items);
    }

    /**
     * Items already past their expiration date.
     */
    public function getExpiredItems(?int $categoryId = null, ?int $supplierId = null): Collection
    {
        $items = $this->baseQuery($categoryId, $supplierId)
            ->whereDate('expiration_date', '<', Carbon::today())
            ->orderBy('expiration_date')
            ->get();

        return $this->withExpirationMeta($items);
    }

    /**
     * Attach computed `status` and `days_remaining` attributes to each item.
     */
    protected function withExpirationMeta(Collection $items): Collection
    {
        $today = Carbon::today();

        return $items->each(function (Item $item) use ($today) {
            $item->days_remaining = $today->diffInDays($item->expiration_date, false);
            $item->status = $this->statusFor($item->expiration_date, $today);
        });
    }

    /**
     * Counts of in-stock, dated items grouped by expiration status.
     *
     * @return array{expired: int, critical: int, warning: int, ok: int}
     */
    public function getExpirationSummary(?int $categoryId = null, ?int $supplierId = null): array
    {
        $today = Carbon::today();
        $items = $this->baseQuery($categoryId, $supplierId)->get(['id', 'expiration_date']);

        $summary = ['expired' => 0, 'critical' => 0, 'warning' => 0, 'ok' => 0];

        foreach ($items as $item) {
            $summary[$this->statusFor($item->expiration_date, $today)]++;
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
