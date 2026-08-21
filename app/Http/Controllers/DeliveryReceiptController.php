<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\GenericName;
use App\Models\ProductBatch;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class DeliveryReceiptController extends Controller
{
    public function __construct(protected DeliveryReceiptService $deliveryReceiptService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $deliveryReceipts = DeliveryReceipt::query()
            ->with('customer', 'salesOrder', 'items')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('dr_no', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('customer_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.delivery-receipts.index', compact('deliveryReceipts', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // A failed validation redirect while the Purchase Order tab was
        // active flashes sales_order_id via old() — fall back to that so
        // the tab re-opens on the right Sales Order, same as the query
        // string does for a fresh visit from Sales Order's "Create Delivery
        // Receipt" button.
        return view('admin.delivery-receipts.create', $this->formData($request->query('sales_order_id') ?? old('sales_order_id')));
    }

    /**
     * Data shared by the create and edit-draft forms. When editing a draft,
     * its own items are mapped back into the shape the item picker's async
     * fetch-then-select flow expects: keyed by product_batch_id so, once
     * the fresh available-items fetch comes back for that line's generic
     * name, the saved batch/qty/remarks can be reselected on top of it.
     */
    protected function formData(?string $preselectedSalesOrderId = null, ?DeliveryReceipt $editing = null): array
    {
        $customers = Customer::orderBy('customer_name')->get();
        $genericNames = GenericName::with('category')->orderBy('generic_name')->get();
        $openSalesOrders = SalesOrder::with('customer')
            ->whereIn('status', ['open', 'partially_delivered'])
            ->latest()
            ->get();
        $users = User::orderBy('name')->get();

        $genericNamesForJs = $genericNames->map(fn (GenericName $g) => [
            'id' => $g->id,
            'code' => $g->code,
            'generic_name' => $g->generic_name,
            'unit' => $g->unit,
            'category_name' => $g->category->category_name,
        ])->values();

        $prefillLines = [];

        if ($editing) {
            $preselectedSalesOrderId = $editing->sales_order_id ? (string) $editing->sales_order_id : null;
            $editing->load('items.productBatch.product.genericName');

            $prefillLines = $editing->items
                ->filter(fn ($item) => $item->productBatch?->product?->genericName)
                ->map(function ($item) {
                    $generic = $item->productBatch->product->genericName;

                    return [
                        'generic_name_id' => $generic->id,
                        'product_batch_id' => $item->product_batch_id,
                        'qty' => $item->qty,
                        'remarks' => $item->remarks,
                        'sales_order_item_id' => $item->sales_order_item_id,
                    ];
                })
                ->values();
        }

        // A failed validation redirect flashes the submitted `items` array
        // via old() — reuse it so the JS-built line-item rows repopulate
        // from what the user actually typed, instead of resetting to blank.
        // Takes precedence over the draft's own saved items. generic_name_id
        // isn't itself submitted (only product_batch_id is), so it's
        // resolved the same way the draft-prefill above does: walk the
        // batch's own product/genericName relationship.
        if (old('items')) {
            $batchIds = collect(old('items'))->pluck('product_batch_id')->filter()->all();
            $batches = ProductBatch::with('product.genericName')->whereIn('id', $batchIds)->get()->keyBy('id');

            $prefillLines = collect(old('items'))
                ->filter(fn ($line) => ! empty($line['product_batch_id']) && $batches->get($line['product_batch_id'])?->product?->genericName)
                ->map(function ($line) use ($batches) {
                    $generic = $batches->get($line['product_batch_id'])->product->genericName;

                    return [
                        'generic_name_id' => $generic->id,
                        'product_batch_id' => $line['product_batch_id'],
                        'qty' => $line['qty'] ?? null,
                        'remarks' => $line['remarks'] ?? null,
                        'sales_order_item_id' => $line['sales_order_item_id'] ?? null,
                    ];
                })
                ->values();
        }

        return compact(
            'customers', 'genericNames', 'openSalesOrders', 'users',
            'preselectedSalesOrderId', 'genericNamesForJs', 'prefillLines'
        ) + ['editingDeliveryReceipt' => $editing];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $deliveryReceipt = $this->deliveryReceiptService->saveDraft($validated, Auth::id());

            ActivityLog::record(
                module: 'DeliveryReceipt',
                action: 'draft_saved',
                loggable: $deliveryReceipt,
                description: "Saved draft Delivery Receipt {$deliveryReceipt->dr_no}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before finalizing.');
            return redirect()->route('delivery-receipts.show', $deliveryReceipt);
        }

        $validated = $request->validate($this->postedValidationRules());

        if ($validated['transaction_type'] === 'purchase_order') {
            $validated['customer_id'] = SalesOrder::findOrFail($validated['sales_order_id'])->customer_id;
        } else {
            $validated['sales_order_id'] = null;
        }

        try {
            $deliveryReceipt = $this->deliveryReceiptService->createDeliveryReceipt($validated, Auth::id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        ActivityLog::record(
            module: 'DeliveryReceipt',
            action: 'created',
            loggable: $deliveryReceipt,
            description: "Created Delivery Receipt {$deliveryReceipt->dr_no}",
        );

        Alert::success('Success', 'Delivery Receipt created successfully');
        return redirect()->route('delivery-receipts.show', $deliveryReceipt);
    }

    /**
     * Loose rules for a draft — an interrupted encoder can leave anything
     * blank or half-typed, so nothing here can block the save.
     */
    protected function draftValidationRules(): array
    {
        return [
            'transaction_type' => 'nullable|in:advance_order,purchase_order,walk_in',
            'customer_id' => 'nullable|exists:customers,id',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'description' => 'nullable|string|max:255',
            'receipt_date' => 'nullable|date',
            'prepared_by' => 'nullable|exists:users,id',
            'items' => 'nullable|array',
            'items.*.product_batch_id' => 'nullable|exists:product_batches,id',
            'items.*.qty' => 'nullable|integer|min:1',
            'items.*.remarks' => 'nullable|string|max:255',
            'items.*.sales_order_item_id' => 'nullable|exists:sales_order_items,id',
        ];
    }

    /**
     * Strict rules for the moment stock actually moves — whether that's a
     * direct Save or finalizing a draft, the data must be complete.
     */
    protected function postedValidationRules(): array
    {
        return [
            'transaction_type' => 'required|in:advance_order,purchase_order,walk_in',
            'customer_id' => 'required_if:transaction_type,advance_order,walk_in|nullable|exists:customers,id',
            'sales_order_id' => 'required_if:transaction_type,purchase_order|nullable|exists:sales_orders,id',
            'description' => 'nullable|string|max:255',
            'receipt_date' => 'required|date',
            'prepared_by' => 'nullable|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.product_batch_id' => 'required|exists:product_batches,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.remarks' => 'nullable|string|max:255',
            'items.*.sales_order_item_id' => 'nullable|exists:sales_order_items,id',
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show(DeliveryReceipt $deliveryReceipt)
    {
        $deliveryReceipt->load('customer', 'salesOrder', 'preparedBy', 'items.productBatch.product.genericName', 'items.sales.invoice');

        return view('admin.delivery-receipts.show', compact('deliveryReceipt'));
    }

    /**
     * Create a Sales Invoice covering the checked (undelivered-but-not-yet-
     * invoiced) lines of this Delivery Receipt.
     */
    public function createInvoice(Request $request, DeliveryReceipt $deliveryReceipt)
    {
        $validated = $request->validate([
            'line_ids' => 'required|array|min:1',
            'line_ids.*' => 'exists:delivery_receipt_items,id',
        ]);

        try {
            $invoice = $this->deliveryReceiptService->createInvoiceFromLines(
                $deliveryReceipt,
                $validated['line_ids'],
                Auth::id()
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        ActivityLog::record(
            module: 'DeliveryReceipt',
            action: 'invoice_created',
            loggable: $deliveryReceipt,
            description: "Created Invoice {$invoice->sales_no} from Delivery Receipt {$deliveryReceipt->dr_no}",
            metadata: ['invoice_id' => $invoice->id, 'line_ids' => $validated['line_ids']],
        );

        Alert::success('Success', 'Invoice created successfully');
        return redirect()->route('invoices.show', $invoice);
    }

    /**
     * Mark this Delivery Receipt as physically delivered to the customer.
     */
    public function markDelivered(DeliveryReceipt $deliveryReceipt)
    {
        $previousStatus = $deliveryReceipt->status;
        $deliveryReceipt->update(['status' => 'delivered']);

        ActivityLog::record(
            module: 'DeliveryReceipt',
            action: 'marked_delivered',
            loggable: $deliveryReceipt,
            description: "Marked Delivery Receipt {$deliveryReceipt->dr_no} as delivered",
            metadata: ['before' => ['status' => $previousStatus], 'after' => ['status' => 'delivered']],
        );

        Alert::success('Success', 'Delivery Receipt marked as delivered');
        return redirect()->route('delivery-receipts.show', $deliveryReceipt);
    }

    /**
     * A draft has never touched stock or Sales Order balances, so editing
     * it is completely safe — reuses the same create view, pre-filled with
     * the draft's own current items. A posted receipt already mutated
     * location_stocks (and any linked SO's delivered_qty) the moment it was
     * created, so editing that is not supported.
     */
    public function edit(DeliveryReceipt $deliveryReceipt)
    {
        if (! $deliveryReceipt->isDraft()) {
            Alert::info('Not supported', 'Editing an issued Delivery Receipt is not supported.');
            return redirect()->route('delivery-receipts.show', $deliveryReceipt);
        }

        return view('admin.delivery-receipts.create', $this->formData(editing: $deliveryReceipt));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DeliveryReceipt $deliveryReceipt)
    {
        if (! $deliveryReceipt->isDraft()) {
            Alert::info('Not supported', 'Editing an issued Delivery Receipt is not supported.');
            return redirect()->route('delivery-receipts.show', $deliveryReceipt);
        }

        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $deliveryReceipt = $this->deliveryReceiptService->saveDraft($validated, Auth::id(), $deliveryReceipt);

            ActivityLog::record(
                module: 'DeliveryReceipt',
                action: 'draft_updated',
                loggable: $deliveryReceipt,
                description: "Updated draft Delivery Receipt {$deliveryReceipt->dr_no}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before finalizing.');
            return redirect()->route('delivery-receipts.show', $deliveryReceipt);
        }

        $validated = $request->validate($this->postedValidationRules());

        if ($validated['transaction_type'] === 'purchase_order') {
            $validated['customer_id'] = SalesOrder::findOrFail($validated['sales_order_id'])->customer_id;
        } else {
            $validated['sales_order_id'] = null;
        }

        try {
            $deliveryReceipt = $this->deliveryReceiptService->finalizeDraft($deliveryReceipt, $validated, Auth::id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        ActivityLog::record(
            module: 'DeliveryReceipt',
            action: 'finalized',
            loggable: $deliveryReceipt,
            description: "Finalized Delivery Receipt {$deliveryReceipt->dr_no}",
        );

        Alert::success('Success', 'Delivery Receipt posted successfully');
        return redirect()->route('delivery-receipts.show', $deliveryReceipt);
    }

    /**
     * A draft never touched stock or Sales Order balances, so deleting it
     * outright is safe. A posted receipt can never be deleted — same
     * policy as everywhere else.
     */
    public function destroy(DeliveryReceipt $deliveryReceipt)
    {
        if (! $deliveryReceipt->isDraft()) {
            Alert::info('Not supported', 'Deleting an issued Delivery Receipt is not supported.');
            return redirect()->route('delivery-receipts.show', $deliveryReceipt);
        }

        $drNo = $deliveryReceipt->dr_no;
        $deliveryReceipt->delete();

        ActivityLog::record(
            module: 'DeliveryReceipt',
            action: 'draft_deleted',
            description: "Deleted draft Delivery Receipt {$drNo}",
        );

        Alert::success('Deleted', "Draft {$drNo} has been deleted.");
        return redirect()->route('delivery-receipts.index');
    }
}
