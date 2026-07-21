<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GenericName;
use App\Services\DeliveryReceiptService;

class GenericNameController extends Controller
{
    public function __construct(protected DeliveryReceiptService $deliveryReceiptService) {}

    /**
     * Get in-stock items under a Generic Name, for the Delivery Receipt
     * "Available Product?" check.
     */
    public function availableItems(GenericName $genericName)
    {
        $items = $this->deliveryReceiptService->getAvailableItemsForGenericName($genericName)
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'brand_name' => $item->brand_name,
                    'quantity' => $item->quantity,
                    'batch_no' => $item->batch_no,
                    'expiration_date' => $item->expiration_date?->format('Y-m-d'),
                    'supplier' => $item->supplier ? [
                        'id' => $item->supplier->id,
                        'supplier_name' => $item->supplier->supplier_name,
                    ] : null,
                ];
            });

        return response()->json($items);
    }
}
