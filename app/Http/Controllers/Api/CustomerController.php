<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;

class CustomerController extends Controller
{
    /**
     * Products this customer has actually been invoiced for, with the
     * total quantity purchased across every invoice — powers the admin
     * Return Item form's item picker, scoped to a specific customer so an
     * admin can't record a return for something that customer never
     * bought. Batches offered are the product's current batches (same
     * "pick any existing lot to restock into" pattern used by every other
     * transaction-document picker in this app), not the exact original lot.
     */
    public function purchasedItems(Customer $customer)
    {
        $sales = Sale::whereHas('invoice', fn ($q) => $q->where('customer_id', $customer->id))
            ->with('productBatch.product.genericName', 'productBatch.product.batches')
            ->get()
            ->filter(fn (Sale $sale) => $sale->productBatch?->product);

        $items = $sales->groupBy(fn (Sale $sale) => $sale->productBatch->product_id)
            ->map(function ($salesForProduct) {
                $product = $salesForProduct->first()->productBatch->product;

                return [
                    'id' => $product->id,
                    'name' => $product->description ?: $product->item_name,
                    'unit' => $product->genericName->unit ?? '',
                    'total_purchased' => (int) $salesForProduct->sum('qty'),
                    'batches' => $product->batches->map(fn ($b) => [
                        'id' => $b->id,
                        'batch_no' => $b->batch_no,
                        'expiration_date' => $b->expiration_date?->format('Y-m-d'),
                    ])->values(),
                ];
            })
            ->sortBy('name')
            ->values();

        return response()->json(['items' => $items]);
    }
}
