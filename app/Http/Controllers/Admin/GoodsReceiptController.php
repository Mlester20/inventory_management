<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\GoodsReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class GoodsReceiptController extends Controller
{
    public function __construct(protected GoodsReceiptService $goodsReceiptService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $goodsReceipts = GoodsReceipt::query()
            ->with('supplier', 'purchaseOrder')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('gr_no', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($q) use ($search) {
                            $q->where('supplier_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.goods-receipts.index', compact('goodsReceipts', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $suppliers = Supplier::orderBy('supplier_name')->get();
        $items = Product::orderBy('item_name')->get();
        $openPurchaseOrders = PurchaseOrder::with('supplier')
            ->whereIn('status', ['open', 'partially_received'])
            ->latest()
            ->get();
        $users = User::orderBy('name')->get();
        $preselectedPurchaseOrderId = $request->query('purchase_order_id');

        $itemsForJs = $items->map(fn (Product $item) => [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->description ?: $item->item_name,
            'unit_cost' => (float) $item->unit_cost,
            'supplier_id' => $item->supplier_id,
            'generic_name_id' => $item->generic_name_id,
        ])->values();

        return view('admin.goods-receipts.create', compact(
            'suppliers', 'items', 'itemsForJs', 'openPurchaseOrders', 'users', 'preselectedPurchaseOrderId'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'supplier_id' => 'required_without:purchase_order_id|nullable|exists:suppliers,id',
            'receipt_date' => 'required|date',
            'prepared_by' => 'nullable|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'nullable|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.batch_no' => 'nullable|string|max:100',
            'items.*.expiration_date' => 'nullable|date',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
        ]);

        if (! empty($validated['purchase_order_id'])) {
            $validated['supplier_id'] = PurchaseOrder::findOrFail($validated['purchase_order_id'])->supplier_id;
        }

        try {
            $goodsReceipt = $this->goodsReceiptService->createGoodsReceipt($validated, Auth::id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        ActivityLog::record(
            module: 'GoodsReceipt',
            action: 'created',
            loggable: $goodsReceipt,
            description: "Created Goods Receipt {$goodsReceipt->gr_no}",
        );

        Alert::success('Success', 'Goods Receipt created successfully');
        return redirect()->route('goods-receipts.show', $goodsReceipt);
    }

    /**
     * Display the specified resource.
     */
    public function show(GoodsReceipt $goodsReceipt)
    {
        $goodsReceipt->load('supplier', 'purchaseOrder', 'preparedBy', 'items.productBatch.product');

        return view('admin.goods-receipts.show', compact('goodsReceipt'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GoodsReceipt $goodsReceipt)
    {
        Alert::info('Not supported', 'Editing an issued Goods Receipt is not supported.');
        return redirect()->route('goods-receipts.show', $goodsReceipt);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GoodsReceipt $goodsReceipt)
    {
        Alert::info('Not supported', 'Editing an issued Goods Receipt is not supported.');
        return redirect()->route('goods-receipts.show', $goodsReceipt);
    }
}
