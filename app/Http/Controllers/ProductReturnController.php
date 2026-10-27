<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ProductReturn;
use App\Services\ReturnItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * The Product Return document: one number over the lines returned from a Delivery Receipt, with a print,
 * and approve / reject for the whole document at once.
 */
class ProductReturnController extends Controller
{
    public function __construct(protected ReturnItemService $returnItemService) {}

    public function show(ProductReturn $productReturn)
    {
        $productReturn->load('deliveryReceipt.salesOrder', 'customer', 'preparedBy', 'items.productBatch.product.genericName');

        return view('admin.product-returns.show', compact('productReturn'));
    }

    /**
     * Approve every pending line: the stock goes back (to the Warehouse for goods from a Delivery
     * Receipt), each line as sellable or written off, and a refund only for goods that were invoiced.
     */
    public function approve(Request $request, ProductReturn $productReturn)
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Approving a Product Return is restricted to full admin accounts.');
            return redirect()->route('product-returns.show', $productReturn);
        }

        $validated = $request->validate([
            'refund_method' => 'nullable|in:cash,credit',
            'disposition' => 'nullable|array',
            'disposition.*' => 'in:sellable,write_off',
        ]);

        $pending = $productReturn->items()->where('status', 'pending')->where('is_draft', false)->get();
        if ($pending->isEmpty()) {
            Alert::info('Nothing to approve', 'Every line of this Product Return has already been decided.');
            return redirect()->route('product-returns.show', $productReturn);
        }

        $refundMethod = $validated['refund_method'] ?? 'cash';

        try {
            DB::transaction(function () use ($pending, $validated, $refundMethod, $productReturn) {
                foreach ($pending as $item) {
                    if ($refundMethod === 'credit' && $item->isRefundable() && ! $item->customer_id) {
                        throw ValidationException::withMessages(['refund_method' => 'Store credit needs a customer.']);
                    }

                    $this->returnItemService->approve(
                        $item,
                        $item->isRefundable() ? $refundMethod : 'none',
                        $validated['disposition'][$item->id] ?? $item->suggestedStockDisposition(),
                        auth()->id()
                    );

                    ActivityLog::record(
                        module: 'ReturnItem',
                        action: 'approved',
                        loggable: $item,
                        description: "Approved {$productReturn->return_no} line #{$item->id} (qty {$item->quantity})",
                    );
                }
            });
        } catch (ValidationException $e) {
            Alert::error('Cannot approve', $e->validator->errors()->first());
            return redirect()->route('product-returns.show', $productReturn);
        }

        Alert::success('Approved', "{$productReturn->return_no} approved — the stock is back in the Warehouse.");
        return redirect()->route('product-returns.show', $productReturn);
    }

    /** Reject every pending line (nothing moves). */
    public function reject(Request $request, ProductReturn $productReturn)
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Rejecting a Product Return is restricted to full admin accounts.');
            return redirect()->route('product-returns.show', $productReturn);
        }

        $validated = $request->validate(['rejection_reason' => 'nullable|string|max:255']);
        $why = $validated['rejection_reason'] ?? 'No reason provided';

        $pending = $productReturn->items()->where('status', 'pending')->where('is_draft', false)->get();
        foreach ($pending as $item) {
            $item->update(['status' => 'rejected', 'reason' => $item->reason . ' | Rejected: ' . $why]);

            ActivityLog::record(
                module: 'ReturnItem',
                action: 'rejected',
                loggable: $item,
                description: "Rejected {$productReturn->return_no} line #{$item->id}" . ($why !== 'No reason provided' ? " ({$why})" : ''),
            );
        }

        Alert::success('Rejected', "{$productReturn->return_no} rejected. Nothing was returned to stock.");
        return redirect()->route('product-returns.show', $productReturn);
    }
}
