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
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'prepared_by' => $data['prepared_by'] ?? null,
            ]);

            foreach ($data['items'] as $line) {
                $salesQuote->items()->create([
                    'generic_name_id' => $line['generic_name_id'],
                    'qty' => $line['qty'],
                    'price' => $line['price'],
                ]);
            }

            return $salesQuote;
        });
    }

    /**
     * Convert an open Sales Quote into a Sales Order, copying its lines.
     *
     * @param array $data ['order_date', 'prepared_by']
     */
    public function convertToSalesOrder(SalesQuote $salesQuote, array $data): SalesOrder
    {
        return DB::transaction(function () use ($salesQuote, $data) {
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

        $lastQuoteNo = SalesQuote::where('quote_no', 'like', "{$prefix}%")
            ->orderByDesc('quote_no')
            ->value('quote_no');

        $nextSequence = 1;
        if ($lastQuoteNo) {
            $nextSequence = (int) substr($lastQuoteNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
