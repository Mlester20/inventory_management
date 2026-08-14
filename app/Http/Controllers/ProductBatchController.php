<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ProductBatch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;

class ProductBatchController extends Controller
{
    /**
     * Direct fix for a typo'd Batch No. or Expiration Date — pure metadata,
     * StockService never reads either column, so this never touches
     * location_stocks/stock_movements/quantities. Distinct from Inventory
     * Adjustment's Write-off flow, which is for genuine quantity mistakes.
     */
    public function update(Request $request, ProductBatch $productBatch)
    {
        $request->validate([
            'batch_no' => [
                'nullable', 'string', 'max:100',
                Rule::unique('product_batches', 'batch_no')
                    ->where('product_id', $productBatch->product_id)
                    ->ignore($productBatch->id),
            ],
            'expiration_date' => 'nullable|date',
        ]);

        $original = $productBatch->getOriginal();
        $productBatch->update([
            'batch_no' => $request->batch_no,
            'expiration_date' => $request->expiration_date,
        ]);

        $changes = $productBatch->getChanges();
        ActivityLog::record(
            module: 'ProductBatch',
            action: 'updated',
            loggable: $productBatch,
            description: "Updated batch details for {$productBatch->product->item_name}",
            metadata: [
                'before' => collect($changes)->keys()->mapWithKeys(fn ($key) => [$key => $original[$key] ?? null])->toArray(),
                'after' => $changes,
            ],
        );

        Alert::success('Success', 'Batch updated successfully');
        return redirect()->route('inventory-items.index', ['tab' => 'batches']);
    }
}
