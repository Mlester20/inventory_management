<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GenericName;
use App\Models\Location;
use App\Services\StockService;
use Illuminate\Http\Request;

class GenericNameController extends Controller
{
    public function __construct(protected StockService $stockService) {}

    /**
     * Get in-stock batches under a Generic Name at a given location, for
     * the Delivery Receipt "Available Product?" check and the Stock
     * Transfer line picker. Defaults to the Warehouse when no location_id
     * is given, matching Delivery Receipt's original (Warehouse-only)
     * behavior.
     */
    public function availableItems(Request $request, GenericName $genericName)
    {
        $locationId = $request->query('location_id');
        $location = $locationId ? Location::findOrFail($locationId) : Location::warehouse();

        $batches = $this->stockService->getAvailableBatchesForGenericName($genericName, $location)
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'item_name' => $batch->product->item_name,
                    'brand_name' => $batch->product->brand_name,
                    'quantity' => $batch->locationStocks->sum('qty'),
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
