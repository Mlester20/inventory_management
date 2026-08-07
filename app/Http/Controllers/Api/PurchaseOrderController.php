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
                    'product_id' => $line->product_id,
                    'item_name' => $line->product->item_name,
                    'description' => $line->product->description ?: $line->product->item_name,
                    'generic_name_id' => $line->product->generic_name_id,
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
