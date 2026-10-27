<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\DeliveryReceiptItem;
use App\Models\InventoryAdjustment;
use App\Models\ReturnItem;
use App\Models\Sale;

class CustomerController extends Controller
{
    /**
     * What this customer can send back, powering the admin Return Item form's item picker: everything
     * delivered to them on a posted Delivery Receipt (invoiced or not), plus anything sold on an
     * Invoice that did not come from a DR, minus what was already returned. Each item lists the DRs
     * it was delivered on, so the return can point at one of them. Batches offered are the product's
     * current batches (same "pick any existing lot to restock into" pattern used by every other
     * transaction-document picker in this app), not the exact original lot.
     */
    public function purchasedItems(Customer $customer)
    {
        $deliveryLines = DeliveryReceiptItem::whereHas('deliveryReceipt', fn ($q) => $q
                ->where('customer_id', $customer->id)->where('is_draft', false)->where('status', '!=', 'cancelled'))
            ->with('deliveryReceipt.salesOrder', 'productBatch.product.genericName', 'productBatch.product.batches')
            ->get()
            ->filter(fn (DeliveryReceiptItem $line) => $line->productBatch?->product);

        $directSales = Sale::whereNull('delivery_receipt_item_id')
            ->whereHas('invoice', fn ($q) => $q->where('customer_id', $customer->id))
            ->with('productBatch.product.genericName', 'productBatch.product.batches')
            ->get()
            ->filter(fn (Sale $sale) => $sale->productBatch?->product);

        $returned = ReturnItem::where('customer_id', $customer->id)
            ->where('is_draft', false)->where('status', '!=', 'rejected')
            ->with('productBatch')
            ->get()
            ->groupBy(fn (ReturnItem $return) => $return->productBatch?->product_id)
            ->map(fn ($returns) => (int) $returns->sum('quantity'));

        $productIds = $deliveryLines->map(fn ($l) => $l->productBatch->product_id)
            ->merge($directSales->map(fn ($s) => $s->productBatch->product_id))
            ->unique();

        $items = $productIds->map(function ($productId) use ($deliveryLines, $directSales, $returned) {
            $lines = $deliveryLines->filter(fn ($l) => $l->productBatch->product_id === $productId);
            $sales = $directSales->filter(fn ($s) => $s->productBatch->product_id === $productId);
            $product = ($lines->first()?->productBatch ?? $sales->first()->productBatch)->product;
            $bought = (int) $lines->sum('qty') + (int) $sales->sum('qty');
            $alreadyReturned = (int) ($returned[$productId] ?? 0);

            return [
                'id' => $product->id,
                'name' => $product->display_description,
                'unit' => $product->genericName->unit ?? '',
                'total_purchased' => $bought,
                'already_returned' => $alreadyReturned,
                'returnable' => max(0, $bought - $alreadyReturned),
                'sources' => $lines->groupBy('delivery_receipt_id')->map(function ($drLines) {
                    $dr = $drLines->first()->deliveryReceipt;

                    return [
                        'id' => $dr->id,
                        'dr_no' => $dr->dr_no,
                        'so_no' => $dr->salesOrder?->so_no,
                        'qty' => (int) $drLines->sum('qty'),
                        'invoiced' => (int) $drLines->sum('invoiced_qty'),
                        // The lot(s) that actually went out on this DR, so the return can name the right one
                        // even when it has no lot number.
                        'batches' => $drLines->map(fn ($l) => [
                            'id' => $l->product_batch_id,
                            'batch_no' => $l->productBatch->batch_no,
                            'expiration_date' => $l->productBatch->expiration_date?->format('Y-m-d'),
                        ])->unique('id')->values(),
                    ];
                })->values(),
                'batches' => $product->batches->map(fn ($b) => [
                    'id' => $b->id,
                    'batch_no' => $b->batch_no,
                    'expiration_date' => $b->expiration_date?->format('Y-m-d'),
                ])->values(),
            ];
        })->sortBy('name')->values();

        return response()->json(['items' => $items]);
    }

    /**
     * The customer's Delivery Receipts (posted, not cancelled) with their lines already worked out for
     * the "return from a Delivery Receipt" form: one row per lot delivered, how many were delivered,
     * how many already came back (Return Items not rejected, plus posted "Returned by Customer"
     * adjustments) and so how many can still be returned. Receipts with nothing left to return are left out.
     */
    public function returnableDeliveries(Customer $customer)
    {
        $receipts = DeliveryReceipt::where('customer_id', $customer->id)
            ->where('is_draft', false)->where('status', '!=', 'cancelled')
            ->with('salesOrder', 'items.productBatch.product.genericName')
            ->latest('id')
            ->get();

        $ids = $receipts->pluck('id');

        $returned = ReturnItem::whereIn('delivery_receipt_id', $ids)
            ->where('is_draft', false)->where('status', '!=', 'rejected')
            ->get()
            ->groupBy(fn (ReturnItem $return) => $return->delivery_receipt_id . '|' . $return->product_batch_id)
            ->map(fn ($rows) => (int) $rows->sum('quantity'));

        foreach (InventoryAdjustment::whereIn('delivery_receipt_id', $ids)->where('adjustment_type', InventoryAdjustment::CUSTOMER_RETURN)
            ->where('status', 'posted')->with('lines')->get() as $adjustment) {
            foreach ($adjustment->lines as $line) {
                $key = $adjustment->delivery_receipt_id . '|' . $line->product_batch_id;
                $returned[$key] = ($returned[$key] ?? 0) + (int) $line->qty;
            }
        }

        $deliveries = $receipts->map(function (DeliveryReceipt $dr) use ($returned) {
            $rows = $dr->items
                ->filter(fn ($line) => $line->productBatch?->product)
                ->groupBy('product_batch_id')
                ->map(function ($lines, $batchId) use ($dr, $returned) {
                    $batch = $lines->first()->productBatch;
                    $delivered = (int) $lines->sum('qty');
                    $already = (int) ($returned[$dr->id . '|' . $batchId] ?? 0);

                    return [
                        'batch_id' => (int) $batchId,
                        'name' => $batch->product->display_description,
                        'unit' => $batch->product->genericName->unit ?? '',
                        'batch_no' => $batch->batch_no,
                        'expiration_date' => $batch->expiration_date?->format('Y-m-d'),
                        'delivered' => $delivered,
                        'returned' => $already,
                        'returnable' => max(0, $delivered - $already),
                    ];
                })
                ->values();

            return [
                'id' => $dr->id,
                'dr_no' => $dr->dr_no,
                'so_no' => $dr->salesOrder?->so_no,
                'date' => $dr->receipt_date?->format('Y-m-d'),
                'invoiced' => $dr->items->sum('invoiced_qty') > 0,
                'rows' => $rows,
            ];
        })->filter(fn ($dr) => $dr['rows']->contains(fn ($row) => $row['returnable'] > 0))->values();

        return response()->json(['deliveries' => $deliveries]);
    }
}
