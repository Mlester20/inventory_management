<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
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
        $items = Product::orderBy('item_name')->get();
        $users = User::orderBy('name')->get();

        $itemsForJs = $items->map(fn (Product $item) => [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->description ?: $item->item_name,
            'unit_cost' => (float) $item->unit_cost,
            'supplier_id' => $item->supplier_id,
        ])->values();

        return view('admin.purchase-orders.create', compact('suppliers', 'items', 'users', 'itemsForJs'));
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
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
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
        $purchaseOrder->load('supplier', 'preparedBy', 'items.product', 'goodsReceipts');

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
