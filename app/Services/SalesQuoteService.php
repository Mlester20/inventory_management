<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\SalesQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesQuoteService
{
    public function __construct(protected SalesOrderService $salesOrderService) {}

    /**
     * Create a Sales Quote with its line items.
     *
     * @param array $data ['customer_id', 'quote_date', 'valid_until', 'prepared_by', 'items' => [['generic_name_id','qty','price'], ...]]
     */
    public function createSalesQuote(array $data): SalesQuote
    {
        return DB::transaction(function () use ($data) {
            $salesQuote = SalesQuote::create([
                'customer_id' => $data['customer_id'],
                'quote_no' => $this->generateQuoteNo(),
                'status' => 'open',
                'is_draft' => false,
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'prepared_by' => $data['prepared_by'] ?? null,
            ]);

            $this->applyItems($salesQuote, $data['items']);

            return $salesQuote;
        });
    }

    /**
     * Save (or re-save) a draft — nothing here ever touches quote status or
     * conversion, so the encoder can leave customer/items blank/incomplete
     * and resume later.
     *
     * @param array $data ['customer_id', 'quote_date', 'valid_until', 'prepared_by', 'items' => [['generic_name_id','qty','price'], ...]]
     */
    public function saveDraft(array $data, ?SalesQuote $existing = null): SalesQuote
    {
        return DB::transaction(function () use ($data, $existing) {
            $salesQuote = $existing ?? new SalesQuote([
                'quote_no' => $this->generateQuoteNo(),
            ]);

            $salesQuote->fill([
                'customer_id' => $data['customer_id'] ?? null,
                'quote_date' => $data['quote_date'] ?? now()->toDateString(),
                'valid_until' => $data['valid_until'] ?? null,
                'prepared_by' => $data['prepared_by'] ?? null,
                'is_draft' => true,
            ]);
            $salesQuote->save();

            // Replace whatever items existed before — safe, since a draft's
            // items have never been converted to a Sales Order.
            $salesQuote->items()->delete();
            foreach ($data['items'] ?? [] as $line) {
                if (empty($line['generic_name_id'])) {
                    continue;
                }

                $salesQuote->items()->create([
                    'generic_name_id' => $line['generic_name_id'],
                    'qty' => $line['qty'] ?? null,
                    'price' => $line['price'] ?? null,
                ]);
            }

            return $salesQuote;
        });
    }

    /**
     * Turn a draft into a real, open Sales Quote. Replaces the draft's
     * items with the final values, then runs the same item-creation logic
     * createSalesQuote() uses.
     */
    public function finalizeDraft(SalesQuote $draft, array $data): SalesQuote
    {
        return DB::transaction(function () use ($draft, $data) {
            // Re-fetch under a row lock instead of trusting the route-bound
            // $draft's already-loaded status: two near-simultaneous "Save"
            // submissions of the same draft (e.g. a double-click) would
            // otherwise both see is_draft=true before either commits, and
            // both finalize it.
            $draft = SalesQuote::lockForUpdate()->findOrFail($draft->id);

            if (! $draft->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'This Sales Quote has already been finalized.',
                ]);
            }

            $draft->fill([
                'customer_id' => $data['customer_id'],
                'status' => 'open',
                'is_draft' => false,
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'prepared_by' => $data['prepared_by'] ?? null,
            ]);
            $draft->save();

            $draft->items()->delete();
            $this->applyItems($draft, $data['items']);

            return $draft;
        });
    }

    /**
     * Create each line item — shared by createSalesQuote() and
     * finalizeDraft() so this logic exists in exactly one place.
     */
    protected function applyItems(SalesQuote $salesQuote, array $items): void
    {
        foreach ($items as $line) {
            $salesQuote->items()->create([
                'generic_name_id' => $line['generic_name_id'],
                'qty' => $line['qty'],
                'price' => $line['price'],
            ]);
        }
    }

    /**
     * Convert an open Sales Quote into a Sales Order, copying its lines.
     *
     * @param array $data ['order_date', 'prepared_by']
     */
    public function convertToSalesOrder(SalesQuote $salesQuote, array $data): SalesOrder
    {
        return DB::transaction(function () use ($salesQuote, $data) {
            // Re-fetch under a row lock instead of trusting the route-bound
            // $salesQuote's already-loaded status: two near-simultaneous
            // convert requests (e.g. a double-click) would otherwise both
            // read status='open' before either commits, and both create a
            // Sales Order from the same Quote.
            $salesQuote = SalesQuote::lockForUpdate()->findOrFail($salesQuote->id);

            if ($salesQuote->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'This Sales Quote is still a draft and must be finalized before it can be converted.',
                ]);
            }

            if ($salesQuote->status !== 'open') {
                throw ValidationException::withMessages([
                    'status' => 'This Sales Quote has already been ' . ($salesQuote->status === 'converted' ? 'converted to a Sales Order.' : 'cancelled.'),
                ]);
            }

            $salesOrder = $this->salesOrderService->createSalesOrder([
                'customer_id' => $salesQuote->customer_id,
                'po_no' => null,
                'order_date' => $data['order_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
                'items' => $salesQuote->items->map(fn ($item) => [
                    'generic_name_id' => $item->generic_name_id,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'advance_order_qty' => 0,
                ])->all(),
            ]);

            $salesOrder->update(['sales_quote_id' => $salesQuote->id]);
            $salesQuote->update(['status' => 'converted']);

            return $salesOrder;
        });
    }

    /**
     * Generate the next sequential Sales Quote number for the current year,
     * e.g. SQ-2026-00001.
     */
    public function generateQuoteNo(): string
    {
        $year = now()->year;
        $prefix = "SQ-{$year}-";

        // lockForUpdate() blocks a concurrent caller until this transaction
        // commits, preventing two requests from generating the same number.
        $lastQuoteNo = SalesQuote::where('quote_no', 'like', "{$prefix}%")
            ->orderByDesc('quote_no')
            ->lockForUpdate()
            ->value('quote_no');

        $nextSequence = 1;
        if ($lastQuoteNo) {
            $nextSequence = (int) substr($lastQuoteNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
