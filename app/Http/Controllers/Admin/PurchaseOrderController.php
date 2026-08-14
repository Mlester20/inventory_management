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

        return view('admin.purchase-orders.create', compact('suppliers', 'users', 'genericNamesForJs'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'prepared_by' => 'nullable|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.generic_name_id' => 'required|exists:generic_names,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:255',
        ]);

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
     * Display the specified resource.
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('supplier', 'preparedBy', 'items.product', 'items.genericName', 'goodsReceipts');

        return view('admin.purchase-orders.show', compact('purchaseOrder'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PurchaseOrder $purchaseOrder)
    {
        Alert::info('Not supported', 'Editing an issued Purchase Order is not supported.');
        return redirect()->route('purchase-orders.show', $purchaseOrder);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        Alert::info('Not supported', 'Editing an issued Purchase Order is not supported.');
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
