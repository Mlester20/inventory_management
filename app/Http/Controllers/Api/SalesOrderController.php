<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Services\DeliveryReceiptService;

class SalesOrderController extends Controller
{
    public function __construct(protected DeliveryReceiptService $deliveryReceiptService) {}

    /**
     * Get a Sales Order's customer + remaining (undelivered) lines, for the
     * Purchase-Order-mode Delivery Receipt flow.
     */
    public function remainingItems(SalesOrder $salesOrder)
    {
        $remainingItems = $this->deliveryReceiptService->getRemainingItems($salesOrder)
            ->map(function ($item) {
                return [
                    'sales_order_item_id' => $item->id,
                    'generic_name_id' => $item->generic_name_id,
                    'generic_name' => $item->genericName->generic_name,
                    'qty' => $item->qty,
                    'delivered_qty' => $item->delivered_qty,
                    'remaining_qty' => $item->remaining_qty,
                    'price' => $item->price,
                ];
            });

        return response()->json([
            'customer' => [
                'id' => $salesOrder->customer_id,
                'customer_name' => $salesOrder->customer->customer_name,
            ],
            'items' => $remainingItems,
        ]);
    }
}
