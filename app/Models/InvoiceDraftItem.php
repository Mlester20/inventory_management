<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One saved-but-not-yet-posted line of a draft Sales Invoice. Nothing here
 * has touched stock; the real `sales` rows (with FEFO batches) are only
 * created when the draft is posted.
 */
class InvoiceDraftItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'desc',
        'unit',
        'batch_no',
        'exp',
        'qty',
        'price',
        'dis',
        'tax_override',
        'wt_type',
    ];

    protected $casts = [
        'exp' => 'date',
        'qty' => 'integer',
        'price' => 'decimal:2',
        'dis' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withoutGlobalScope('notDraft');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
