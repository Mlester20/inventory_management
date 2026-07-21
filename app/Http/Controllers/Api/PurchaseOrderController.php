<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\GoodsReceiptService;

class PurchaseOrderController extends Controller
{
    public function __construct(protected GoodsReceiptService $goodsReceiptService) {}

    /**
     * Get a Purchase Order's supplier + pending (unreceived) lines, for the
     * Goods-Receipt-against-Purchase-Order flow.
     */
    public function pendingItems(PurchaseOrder $purchaseOrder)
    {
        $pendingItems = $this->goodsReceiptService->getPendingPurchaseOrderItems($purchaseOrder)
            ->map(function ($line) {
                return [
                    'purchase_order_item_id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_name' => $line->item->item_name,
                    'qty' => $line->qty,
                    'received_qty' => $line->received_qty,
                    'remaining_qty' => $line->remaining_qty,
                    'unit_cost' => $line->unit_cost,
                ];
            });

        return response()->json([
            'supplier' => [
                'id' => $purchaseOrder->supplier_id,
                'supplier_name' => $purchaseOrder->supplier->supplier_name,
            ],
            'items' => $pendingItems,
        ]);
    }
}
