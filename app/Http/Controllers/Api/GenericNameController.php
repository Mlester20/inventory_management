<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GenericName;
use App\Services\DeliveryReceiptService;

class GenericNameController extends Controller
{
    public function __construct(protected DeliveryReceiptService $deliveryReceiptService) {}

    /**
     * Get in-stock batches under a Generic Name, for the Delivery Receipt
     * "Available Product?" check.
     */
    public function availableItems(GenericName $genericName)
    {
        $batches = $this->deliveryReceiptService->getAvailableItemsForGenericName($genericName)
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'item_name' => $batch->product->item_name,
                    'brand_name' => $batch->product->brand_name,
                    'quantity' => $batch->qty,
                    'batch_no' => $batch->batch_no,
                    'expiration_date' => $batch->expiration_date?->format('Y-m-d'),
                    'supplier' => $batch->product->supplier ? [
                        'id' => $batch->product->supplier->id,
                        'supplier_name' => $batch->product->supplier->supplier_name,
                    ] : null,
                ];
            });

        return response()->json($batches);
    }
}
