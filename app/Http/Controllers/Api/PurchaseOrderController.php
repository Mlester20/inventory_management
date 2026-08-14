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
                // A PO line orders a Generic Item — product_id (the specific
                // brand) is normally still null at this point, only ever
                // set on older POs from before this line ordered at the
                // generic level. Fall back to the Generic Item's own name
                // either way, so the receiver picks the actual brand here.
                $name = $line->product->item_name ?? $line->genericName->generic_name;

                return [
                    'purchase_order_item_id' => $line->id,
                    'product_id' => $line->product_id,
                    'item_name' => $name,
                    'description' => $line->product?->description ?: $name,
                    // Older PO lines (from before this ordered at the generic
                    // level) never got generic_name_id backfilled on this
                    // table — fall back to the chosen product's own generic,
                    // so Brand-sibling matching keeps working for them too.
                    'generic_name_id' => $line->generic_name_id ?? $line->product?->generic_name_id,
                    'qty' => $line->qty,
                    'unit' => $line->unit,
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
