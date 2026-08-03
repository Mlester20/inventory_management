<?php

namespace App\Services;

use App\Models\Location;
use App\Models\ReturnItem;
use Illuminate\Support\Facades\DB;

class ReturnItemService
{
    public function __construct(protected StockService $stockService) {}

    /**
     * Approve a return item: restock it (as before), and if the return is
     * attached to a customer, issue an advance-credit CustomerPayment for it.
     *
     * The credit amount is quantity x the customer's own price tier (via
     * Customer::priceColumn()), falling back to retail unit_price when no
     * customer is attached. This is a known approximation — return_items
     * never recorded the actual price the item was originally sold at.
     *
     * @return array{returnItem: ReturnItem, creditPayment: ?\App\Models\CustomerPayment}
     */
    public function approve(ReturnItem $returnItem, ?int $userId = null): array
    {
        return DB::transaction(function () use ($returnItem, $userId) {
            $this->stockService->restock(
                $returnItem->productBatch,
                $returnItem->quantity,
                Location::pos(),
                "Return item approved - Return ID: {$returnItem->id}, Reason: {$returnItem->reason}",
                $userId
            );

            $returnItem->update(['status' => 'approved']);

            $creditPayment = null;

            if ($returnItem->customer_id) {
                $customer = $returnItem->customer;
                $product = $returnItem->productBatch->product;
                $price = (float) ($product->{$customer->priceColumn()} ?? $product->unit_price);
                $creditAmount = round($returnItem->quantity * $price, 2);

                $creditPayment = $customer->payments()->create([
                    'type' => 'advance',
                    'amount' => $creditAmount,
                    'payment_date' => now()->toDateString(),
                    'remarks' => "Credit for approved Return Item #{$returnItem->id}",
                    'prepared_by' => $userId,
                ]);
            }

            return ['returnItem' => $returnItem, 'creditPayment' => $creditPayment];
        });
    }
}
