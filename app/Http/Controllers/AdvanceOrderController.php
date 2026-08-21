<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\DeliveryReceiptItem;
use App\Services\DeliveryReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Per SAIMS-REV-2.0B.pdf item 9: a per-customer, cross-Delivery-Receipt view
 * of every Advance Order line, so front desk staff don't have to open each
 * Delivery Receipt one at a time to see what's still owed/uninvoiced for a
 * given customer. Deliberately reuses DeliveryReceiptService's existing
 * createInvoiceFromLines() rather than a second invoicing path — see
 * createInvoice() below.
 */
class AdvanceOrderController extends Controller
{
    public function __construct(protected DeliveryReceiptService $deliveryReceiptService) {}

    public function index(Request $request)
    {
        $customers = Customer::orderBy('customer_name')->get();
        $customerId = $request->input('customer_id');
        $customer = $customerId ? Customer::find($customerId) : null;

        // Checkboxes/invoicing work the same whether or not a customer is
        // picked — the customer filter is purely for narrowing what's
        // shown. createInvoice() below groups selected lines by their own
        // source Delivery Receipt (each already scoped to one real
        // customer), so selecting lines that span several customers just
        // produces one invoice per customer/DR, same as it already does for
        // several DRs under a single customer.
        $allLines = DeliveryReceiptItem::query()
            ->whereHas('deliveryReceipt', function ($query) use ($customerId) {
                $query->where('transaction_type', 'advance_order')
                    ->where('is_draft', false)
                    ->when($customerId, fn ($q) => $q->where('customer_id', $customerId));
            })
            ->with(['deliveryReceipt.customer', 'productBatch.product.genericName', 'sales.invoice'])
            ->get()
            ->filter(fn (DeliveryReceiptItem $line) => $line->productBatch?->product?->genericName)
            ->sortBy(fn (DeliveryReceiptItem $line) => $line->deliveryReceipt->receipt_date)
            ->values();

        // Paginated in-memory rather than at the query level — the filter
        // above (dropping lines whose batch/product/generic got deleted)
        // can't cleanly translate into a WHERE clause, and this listing's
        // row counts are nowhere near large enough for that tradeoff to
        // matter. Keeps the rendered page light regardless of how many
        // Advance Orders a customer accumulates.
        $perPage = 10;
        $page = (int) $request->input('page', 1);
        $lines = new \Illuminate\Pagination\LengthAwarePaginator(
            $allLines->forPage($page, $perPage)->values(),
            $allLines->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.advance-orders.index', compact('customers', 'customer', 'lines'));
    }

    /**
     * Invoice a set of selected lines that may span multiple Delivery
     * Receipts — and, since checkboxes are available in the "all customers"
     * browse view too, multiple customers — grouped by their source DR and
     * handed to DeliveryReceiptService::createInvoiceFromLines() once per
     * group, the exact same call a single DR's own "Create Invoice" button
     * already makes. Reusing it here (rather than a parallel invoicing
     * path) is what keeps the invoiced_qty bookkeeping and Sale-row
     * creation consistent regardless of which screen triggered it. Every
     * group is inherently scoped to its own real customer (a Delivery
     * Receipt only ever belongs to one), so there's no risk of a line
     * ending up on the wrong customer's invoice — no separate ownership
     * check is needed here the way a single-customer_id design would need.
     */
    public function createInvoice(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'line_ids' => 'nullable|array',
            'line_ids.*' => 'exists:delivery_receipt_items,id',
        ]);

        // A plain validate() rule here (required|min:1) would silently
        // redirect back with no visible message, since this page doesn't
        // render $errors — an unchecked "Create Invoice" click would look
        // like nothing happened. Alert::error() matches how every other
        // business-rule failure in this app surfaces (e.g. "Cannot delete"),
        // so this stays consistent instead of introducing a second error UI
        // just for this page.
        if (empty($validated['line_ids'])) {
            Alert::error('Nothing selected', 'Check at least one line to invoice before clicking Create Invoice.');
            return redirect()->route('advance-orders.index', ['customer_id' => $validated['customer_id'] ?? null]);
        }

        // Eager-load deliveryReceipt so each group below reuses the model
        // already in memory instead of a second per-DR query.
        $lines = DeliveryReceiptItem::whereIn('id', $validated['line_ids'])->with('deliveryReceipt')->get();
        $linesByDeliveryReceipt = $lines->groupBy('delivery_receipt_id');
        $invoices = [];

        try {
            foreach ($linesByDeliveryReceipt as $deliveryReceiptId => $group) {
                $deliveryReceipt = $group->first()->deliveryReceipt;
                $invoice = $this->deliveryReceiptService->createInvoiceFromLines(
                    $deliveryReceipt,
                    $group->pluck('id')->all(),
                    Auth::id()
                );
                $invoices[] = $invoice;

                ActivityLog::record(
                    module: 'DeliveryReceipt',
                    action: 'invoice_created',
                    loggable: $deliveryReceipt,
                    description: "Created Invoice {$invoice->sales_no} from Delivery Receipt {$deliveryReceipt->dr_no} via Advance Orders",
                    metadata: ['invoice_id' => $invoice->id, 'line_ids' => $group->pluck('id')->all()],
                );
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        if (count($invoices) === 1) {
            Alert::success('Success', 'Invoice created successfully');
            return redirect()->route('invoices.show', $invoices[0]);
        }

        Alert::success('Success', count($invoices) . ' invoices created successfully (selected lines spanned multiple Delivery Receipts).');
        return redirect()->route('advance-orders.index', ['customer_id' => $validated['customer_id']]);
    }
}
