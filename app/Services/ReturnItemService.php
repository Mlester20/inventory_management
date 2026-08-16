<?php

namespace App\Services;

use App\Models\Location;
use App\Models\ReturnItem;
use Illuminate\Support\Facades\DB;

class ReturnItemService
{
    public function __construct(
        protected StockService $stockService,
        protected StockDisposalService $stockDisposalService,
    ) {}

    /**
     * Approve a return item: restock it, then dispose of both its physical
     * stock and its monetary value.
     *
     * Stock disposition — 'sellable' leaves it restocked to POS as
     * available inventory; 'write_off' restocks it (so the return itself is
     * still a real, traceable movement) then immediately disposes the same
     * qty via StockDisposalService, so a defective/damaged return never
     * actually sits in sellable stock — net zero on-hand change, but both
     * movements land in the shared Product History ledger.
     *
     * Refund method — 'credit' issues an advance-credit CustomerPayment
     * (requires a customer, since the credit lives on their account); 'cash'
     * hands the money back immediately and creates no CustomerPayment at
     * all, since it was never something the customer can still draw on.
     * Either way the computed amount is stamped onto the ReturnItem itself
     * (refund_method/refund_amount).
     *
     * The amount is quantity x the customer's own price tier (via
     * Customer::priceColumn()), falling back to retail unit_price when no
     * customer is attached. This is a known approximation — return_items
     * never recorded the actual price the item was originally sold at.
     *
     * @return array{returnItem: ReturnItem, creditPayment: ?\App\Models\CustomerPayment}
     */
    public function approve(ReturnItem $returnItem, string $refundMethod, string $stockDisposition, ?int $userId = null): array
    {
        return DB::transaction(function () use ($returnItem, $refundMethod, $stockDisposition, $userId) {
            $posLocation = Location::pos();

            $this->stockService->restock(
                $returnItem->productBatch,
                $returnItem->quantity,
                $posLocation,
                "Return item approved - Return ID: {$returnItem->id}, Reason: {$returnItem->reason}",
                $userId
            );

            $stockDisposal = null;

            if ($stockDisposition === 'write_off') {
                $stockDisposal = $this->stockDisposalService->createStockDisposal([
                    'date' => now()->toDateString(),
                    'reason' => 'Customer Return: ' . $returnItem->reason,
                    'remarks' => "Written off from Return Item #{$returnItem->id} — not sellable.",
                    'prepared_by' => $userId,
                    'lines' => [[
                        'product_batch_id' => $returnItem->product_batch_id,
                        'location_id' => $posLocation->id,
                        'qty' => $returnItem->quantity,
                    ]],
                ], $userId);
            }

            $customer = $returnItem->customer;
            $product = $returnItem->productBatch->product;
            $price = $customer
                ? (float) ($product->{$customer->priceColumn()} ?? $product->unit_price)
                : (float) $product->unit_price;
            $refundAmount = round($returnItem->quantity * $price, 2);

            $returnItem->update([
                'status' => 'approved',
                'refund_method' => $refundMethod,
                'refund_amount' => $refundAmount,
                'stock_disposition' => $stockDisposition,
                'stock_disposal_id' => $stockDisposal?->id,
            ]);

            $creditPayment = null;

            if ($refundMethod === 'credit' && $customer) {
                $creditPayment = $customer->payments()->create([
                    'type' => 'advance',
                    'amount' => $refundAmount,
                    'payment_date' => now()->toDateString(),
                    'remarks' => "Credit for approved Return Item #{$returnItem->id}",
                    'prepared_by' => $userId,
                ]);
            }

            return ['returnItem' => $returnItem, 'creditPayment' => $creditPayment];
        });
    }
}
