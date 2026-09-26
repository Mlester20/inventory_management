<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_name',
        'customer_id',
        'po_no',
        'osca_no',
        'sales_no',
        'is_draft',
        'prepared_by',
        'approved_by',
        'vat_sales',
        'vatex_sales',
        'zero_sales',
        'vat_amount',
        'total_sales',
        'less_vat',
        'amount_net',
        'less_sc',
        'less_wt',
        'is_personal_use',
        'amount_due',
        'amount_paid',
        'add_vat',
        'archived_at',
        'cancelled_at',
    ];

    protected $casts = [
        'is_draft' => 'boolean',
        'is_personal_use' => 'boolean',
        'vat_sales' => 'decimal:2',
        'vatex_sales' => 'decimal:2',
        'zero_sales' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'less_vat' => 'decimal:2',
        'amount_net' => 'decimal:2',
        'less_sc' => 'decimal:2',
        'less_wt' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'add_vat' => 'decimal:2',
        'archived_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * A draft Sales Invoice (saved but not yet posted) must never count toward
     * any total, receivable, customer balance or report — and none of those
     * queries know about drafts. Hiding them behind a global scope means every
     * existing Invoice query (dashboard, sales report, customer receivables/
     * credit application, relation counts) excludes them automatically, the
     * same way SoftDeletes already does. The few places that genuinely need
     * drafts (the Invoices list, opening/editing/deleting a draft, and the
     * sales_no generators, which must not reuse a draft's number) opt back in
     * with ->withoutGlobalScope('notDraft').
     */
    protected static function booted(): void
    {
        static::addGlobalScope('notDraft', function (Builder $query) {
            $query->where($query->getModel()->getTable() . '.is_draft', false);
        });
    }

    /**
     * Route model binding has to resolve drafts too (show/edit/update/destroy
     * a draft by URL) — the scope above only hides them from queries.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScope('notDraft')
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    /**
     * Next sequential Invoice number for the current year, e.g. INV-2026-00001.
     * The single source of truth for the direct Invoice form, Delivery Receipt
     * -> Invoice conversion and Save Draft.
     *
     * lockForUpdate() blocks a concurrent caller until its transaction
     * commits, preventing two requests from generating the same number (a
     * display-only call outside a transaction just previews it; the real
     * number is always regenerated when saved). withTrashed() is required:
     * Invoice is soft-deletable but sales_no stays unique at the DB level even
     * for trashed rows — and so is withoutGlobalScope('notDraft'), since a
     * draft already holds its number too and must not have it reused.
     */
    public static function nextSalesNo(): string
    {
        $prefix = 'INV-' . now()->year . '-';

        $lastSalesNo = static::withTrashed()
            ->withoutGlobalScope('notDraft')
            ->where('sales_no', 'like', "{$prefix}%")
            ->orderByDesc('sales_no')
            ->lockForUpdate()
            ->value('sales_no');

        $nextSequence = $lastSalesNo ? (int) substr($lastSalesNo, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }

    public function isDraft(): bool
    {
        return (bool) $this->is_draft;
    }

    public function draftItems(): HasMany
    {
        return $this->hasMany(InvoiceDraftItem::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
