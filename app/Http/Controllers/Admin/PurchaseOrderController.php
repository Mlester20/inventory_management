<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\GenericName;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrderService $purchaseOrderService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $purchaseOrders = PurchaseOrder::query()
            ->with('supplier')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('po_no', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($q) use ($search) {
                            $q->where('supplier_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.purchase-orders.index', compact('purchaseOrders', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.purchase-orders.create', $this->formData());
    }

    /**
     * Data shared by the create and edit-draft forms. When editing a draft,
     * its own items are mapped back into the shape the item picker's JS
     * expects to prefill (generic_label so it can be typed straight into
     * the search input — everything is already client-side, no fetch
     * needed, same as Inventory Adjustment's picker).
     */
    protected function formData(?PurchaseOrder $editing = null): array
    {
        $suppliers = Supplier::orderBy('supplier_name')->get();
        // A Purchase Order orders a Generic Item, not a specific brand — the
        // brand isn't decided until Goods Receipt time (same as the
        // existing GR "Against PO" Brand field already works).
        $genericNames = GenericName::with('category')->orderBy('generic_name')->get();
        $users = User::orderBy('name')->get();

        $genericNamesForJs = $genericNames->map(fn (GenericName $g) => [
            'id' => $g->id,
            'code' => $g->code,
            'generic_name' => $g->generic_name,
            'unit' => $g->unit,
            'category_name' => $g->category->category_name ?? '',
        ])->values();

        $prefillLines = [];

        if ($editing) {
            $editing->load('items.genericName.category');

            $prefillLines = $editing->items
                ->filter(fn ($line) => $line->genericName)
                ->map(fn ($line) => [
                    'generic_label' => "{$line->genericName->generic_name} ({$line->genericName->unit}) — {$line->genericName->category->category_name}",
                    'generic_name_id' => $line->generic_name_id,
                    'qty' => $line->qty,
                    'unit' => $line->unit,
                    'unit_cost' => $line->unit_cost !== null ? (float) $line->unit_cost : null,
                    'remarks' => $line->remarks,
                ])
                ->values();
        }

        return compact('suppliers', 'users', 'genericNamesForJs', 'prefillLines') + ['editingPurchaseOrder' => $editing];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $purchaseOrder = $this->purchaseOrderService->saveDraft($validated);

            ActivityLog::record(
                module: 'PurchaseOrder',
                action: 'draft_saved',
                loggable: $purchaseOrder,
                description: "Saved draft Purchase Order {$purchaseOrder->po_no}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before finalizing.');
            return redirect()->route('purchase-orders.show', $purchaseOrder);
        }

        $validated = $request->validate($this->postedValidationRules());

        $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder($validated);

        ActivityLog::record(
            module: 'PurchaseOrder',
            action: 'created',
            loggable: $purchaseOrder,
            description: "Created Purchase Order {$purchaseOrder->po_no}",
        );

        Alert::success('Success', 'Purchase Order created successfully');
        return redirect()->route('purchase-orders.show', $purchaseOrder);
    }

    /**
     * Loose rules for a draft — an interrupted encoder can leave anything
     * blank or half-typed, so nothing here can block the save.
     */
    protected function draftValidationRules(): array
    {
        return [
            'supplier_id' => 'nullable|exists:suppliers,id',
            'order_date' => 'nullable|date',
            'prepared_by' => 'nullable|exists:users,id',
            'items' => 'nullable|array',
            'items.*.generic_name_id' => 'nullable|exists:generic_names,id',
            'items.*.qty' => 'nullable|integer|min:1',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:255',
        ];
    }

    /**
     * Strict rules for the moment the order is actually issued — whether
     * that's a direct Save or finalizing a draft, the data must be complete.
     */
    protected function postedValidationRules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'prepared_by' => 'nullable|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.generic_name_id' => 'required|exists:generic_names,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:255',
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('supplier', 'preparedBy', 'items.product', 'items.genericName', 'goodsReceipts');

        return view('admin.purchase-orders.show', compact('purchaseOrder'));
    }

    /**
     * A draft has never been received against, so editing it is completely
     * safe — reuses the same create view, pre-filled with the draft's own
     * current items. A posted (issued) order is not supported for editing,
     * matching the existing behavior for non-draft orders.
     */
    public function edit(PurchaseOrder $purchaseOrder)
    {
        if (! $purchaseOrder->isDraft()) {
            Alert::info('Not supported', 'Editing an issued Purchase Order is not supported.');
            return redirect()->route('purchase-orders.show', $purchaseOrder);
        }

        return view('admin.purchase-orders.create', $this->formData($purchaseOrder));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (! $purchaseOrder->isDraft()) {
            Alert::info('Not supported', 'Editing an issued Purchase Order is not supported.');
            return redirect()->route('purchase-orders.show', $purchaseOrder);
        }

        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $purchaseOrder = $this->purchaseOrderService->saveDraft($validated, $purchaseOrder);

            ActivityLog::record(
                module: 'PurchaseOrder',
                action: 'draft_updated',
                loggable: $purchaseOrder,
                description: "Updated draft Purchase Order {$purchaseOrder->po_no}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before finalizing.');
            return redirect()->route('purchase-orders.show', $purchaseOrder);
        }

        $validated = $request->validate($this->postedValidationRules());

        $purchaseOrder = $this->purchaseOrderService->finalizeDraft($purchaseOrder, $validated);

        ActivityLog::record(
            module: 'PurchaseOrder',
            action: 'finalized',
            loggable: $purchaseOrder,
            description: "Finalized Purchase Order {$purchaseOrder->po_no}",
        );

        Alert::success('Success', 'Purchase Order issued successfully');
        return redirect()->route('purchase-orders.show', $purchaseOrder);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->items()->where('received_qty', '>', 0)->exists()) {
            Alert::error('Cannot delete', 'This Purchase Order already has received items and cannot be deleted.');
            return redirect()->route('purchase-orders.index');
        }

        $poNo = $purchaseOrder->po_no;
        $purchaseOrder->delete();

        ActivityLog::record(
            module: 'PurchaseOrder',
            action: 'deleted',
            loggable: $purchaseOrder,
            description: "Deleted Purchase Order {$poNo}",
        );

        Alert::success('Success', 'Purchase Order deleted successfully');
        return redirect()->route('purchase-orders.index');
    }
}
