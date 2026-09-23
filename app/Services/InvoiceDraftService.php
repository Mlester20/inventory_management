<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Save Draft for Sales Invoice — same idea as the Sales Order/Sales Quote/
 * Stock Transfer drafts. Nothing here ever touches stock, `sales` rows or any
 * VAT/amount column: those only come into existence when InvoiceController
 * posts the draft, so a half-filled invoice can be saved and resumed later
 * without counting toward any total or deducting anything.
 */
class InvoiceDraftService
{
    /**
     * Save (or re-save) a draft. Lines without a picked item are skipped —
     * a product is the only thing that gives a line any meaning.
     *
     * @param array $data ['customer_name', 'customer_id', 'po_no', 'osca_no', 'less_wt', 'prepared_by',
     *                     'approved_by', 'items' => [['item_id','desc','unit','batch_no','exp','qty','price','dis','tax_override'], ...]]
     */
    public function saveDraft(array $data, ?int $userId = null, ?Invoice $existing = null): Invoice
    {
        return DB::transaction(function () use ($data, $userId, $existing) {
            if ($existing) {
                // Re-fetch under a row lock rather than trusting the route-bound
                // model's already-loaded flag (same reason the Delivery Receipt
                // draft does): a draft that got posted in another tab must not
                // be silently turned back into one.
                $invoice = Invoice::withoutGlobalScope('notDraft')->lockForUpdate()->findOrFail($existing->id);

                if (! $invoice->isDraft()) {
                    throw ValidationException::withMessages([
                        'status' => 'This Invoice has already been posted.',
                    ]);
                }
            } else {
                $invoice = new Invoice(['sales_no' => Invoice::nextSalesNo()]);
            }

            $invoice->fill([
                // customer_name is NOT NULL on invoices (free text, historically
                // required) — a draft with no customer yet stores an empty string.
                'customer_name' => $data['customer_name'] ?? '',
                'customer_id' => $data['customer_id'] ?? null,
                'po_no' => $data['po_no'] ?? null,
                'osca_no' => $data['osca_no'] ?? null,
                'less_wt' => $data['less_wt'] ?? 0,
                'prepared_by' => $data['prepared_by'] ?? $userId,
                'approved_by' => $data['approved_by'] ?? null,
                'is_draft' => true,
            ]);
            $invoice->save();

            // Replace whatever lines existed before — safe, since a draft's
            // lines have never been read by anything that moves stock.
            $invoice->draftItems()->delete();
            foreach ($data['items'] ?? [] as $line) {
                if (empty($line['item_id'])) {
                    continue;
                }

                $invoice->draftItems()->create([
                    'product_id' => $line['item_id'],
                    'desc' => $line['desc'] ?? null,
                    'unit' => $line['unit'] ?? null,
                    'batch_no' => $line['batch_no'] ?? null,
                    'exp' => $line['exp'] ?? null,
                    'qty' => $line['qty'] ?? null,
                    'price' => $line['price'] ?? null,
                    'dis' => $line['dis'] ?? null,
                    'tax_override' => $line['tax_override'] ?? null,
                ]);
            }

            return $invoice;
        });
    }
}
