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
        // A failed validation redirect while the Against-PO tab was active
        // flashes purchase_order_id via old() — fall back to that so the
        // tab re-opens on the right Purchase Order, same as the query
        // string does for a fresh visit.
        return view('admin.goods-receipts.create', $this->formData($request->query('purchase_order_id') ?? old('purchase_order_id')));
    }

    /**
     * Data shared by the create and edit-draft forms. When editing a draft,
     * its own items are mapped back into the shape each tab's JS expects to
     * prefill: Direct Receipt items by item label (synchronous, no fetch
     * needed — ITEMS already has everything client-side), Against-PO items
     * keyed by purchase_order_item_id so the tab's existing
     * fetch-pending-lines flow can overlay the draft's saved qty/batch/brand
     * once the fresh pending lines come back.
     */
    protected function formData(?string $preselectedPurchaseOrderId = null, ?GoodsReceipt $editing = null): array
    {
        $suppliers = Supplier::orderBy('supplier_name')->get();
        $items = Product::with(['genericName', 'batches'])->orderBy('item_name')->get();
        $openPurchaseOrders = PurchaseOrder::with('supplier')
            ->whereIn('status', ['open', 'partially_received'])
            ->latest()
            ->get();
        $users = User::orderBy('name')->get();

        $itemsForJs = $items->map(fn (Product $item) => [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->description ?: $item->item_name,
            'unit' => $item->genericName->unit ?? '',
            'unit_cost' => (float) $item->unit_cost,
            'supplier_id' => $item->supplier_id,
            'generic_name_id' => $item->generic_name_id,
            'batches' => $item->batches->map(fn ($b) => [
                'id' => $b->id,
                'batch_no' => $b->batch_no,
                'expiration_date' => $b->expiration_date?->format('Y-m-d'),
            ])->values(),
        ])->values();

        $directPrefillLines = [];
        $poPrefillLines = [];

        if ($editing) {
            $preselectedPurchaseOrderId = $editing->purchase_order_id ? (string) $editing->purchase_order_id : null;
            $editing->load('items.product');

            foreach ($editing->items as $item) {
                if (! $item->product) {
                    continue;
                }

                $shared = [
                    'product_id' => $item->product_id,
                    'label' => $item->product->description ?: $item->product->item_name,
                    'qty' => $item->qty,
                    'unit_cost' => $item->unit_cost !== null ? (float) $item->unit_cost : null,
                    'unit' => $item->unit,
                    'batch_no' => $item->batch_no,
                    'expiration_date' => $item->expiration_date?->format('Y-m-d'),
                    'remarks' => $item->remarks,
                ];

                if ($item->purchase_order_item_id) {
                    $poPrefillLines[] = $shared + ['purchase_order_item_id' => $item->purchase_order_item_id];
                } else {
                    $directPrefillLines[] = $shared;
                }
            }
        }

        // A failed validation redirect flashes the submitted `items` array
        // via old() — reuse it so the JS-built line-item rows repopulate
        // from what the user actually typed, instead of resetting to blank.
        // Takes precedence over the draft's own saved items. Both tabs
        // submit into the same flat `items[]` array, split the same way the
        // draft-prefill above does: purchase_order_item_id present or not.
        if (old('items')) {
            $directPrefillLines = [];
            $poPrefillLines = [];

            foreach (old('items') as $line) {
                if (empty($line['product_id'])) {
                    continue;
                }

                $product = $items->firstWhere('id', $line['product_id']);

                $shared = [
                    'product_id' => $line['product_id'],
                    'label' => $product ? ($product->description ?: $product->item_name) : null,
                    'qty' => $line['qty'] ?? null,
                    'unit_cost' => $line['unit_cost'] ?? null,
                    'unit' => $line['unit'] ?? null,
                    'batch_no' => $line['batch_no'] ?? null,
                    'expiration_date' => $line['expiration_date'] ?? null,
                    'remarks' => $line['remarks'] ?? null,
                ];

                if (! empty($line['purchase_order_item_id'])) {
                    $poPrefillLines[] = $shared + ['purchase_order_item_id' => $line['purchase_order_item_id']];
                } else {
                    $directPrefillLines[] = $shared;
                }
            }
        }

        return compact(
            'suppliers', 'items', 'itemsForJs', 'openPurchaseOrders', 'users',
            'preselectedPurchaseOrderId', 'directPrefillLines', 'poPrefillLines'
        ) + ['editingGoodsReceipt' => $editing];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $goodsReceipt = $this->goodsReceiptService->saveDraft($validated, Auth::id());

            ActivityLog::record(
                module: 'GoodsReceipt',
                action: 'draft_saved',
                loggable: $goodsReceipt,
                description: "Saved draft Goods Receipt {$goodsReceipt->gr_no}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before finalizing.');
            return redirect()->route('goods-receipts.show', $goodsReceipt);
        }

        $validated = $request->validate($this->postedValidationRules());

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
     * Loose rules for a draft — an interrupted encoder can leave anything
     * blank or half-typed, so nothing here can block the save.
     */
    protected function draftValidationRules(): array
    {
        return [
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'receipt_date' => 'nullable|date',
            'prepared_by' => 'nullable|exists:users,id',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.qty' => 'nullable|integer|min:1',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.batch_no' => 'nullable|string|max:100',
            'items.*.expiration_date' => 'nullable|date',
            'items.*.remarks' => 'nullable|string|max:255',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
        ];
    }

    /**
     * Strict rules for the moment stock actually moves — whether that's a
     * direct Save or finalizing a draft, the data must be complete.
     */
    protected function postedValidationRules(): array
    {
        return [
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'supplier_id' => 'required_without:purchase_order_id|nullable|exists:suppliers,id',
            'receipt_date' => 'required|date',
            'prepared_by' => 'nullable|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'nullable|integer|min:1',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.batch_no' => 'nullable|string|max:100',
            'items.*.expiration_date' => 'nullable|date',
            'items.*.remarks' => 'nullable|string|max:255',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show(GoodsReceipt $goodsReceipt)
    {
        $goodsReceipt->load('supplier', 'purchaseOrder', 'preparedBy', 'items.productBatch.product', 'items.product');

        return view('admin.goods-receipts.show', compact('goodsReceipt'));
    }

    /**
     * A draft has never touched stock or PO balances, so editing it is
     * completely safe — reuses the same create view, pre-filled with the
     * draft's own current items. A posted receipt already mutated
     * location_stocks (and any linked PO's received_qty) the moment it was
     * created, so editing that is not supported.
     */
    public function edit(GoodsReceipt $goodsReceipt)
    {
        if (! $goodsReceipt->isDraft()) {
            Alert::info('Not supported', 'Editing an issued Goods Receipt is not supported.');
            return redirect()->route('goods-receipts.show', $goodsReceipt);
        }

        return view('admin.goods-receipts.create', $this->formData(editing: $goodsReceipt));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GoodsReceipt $goodsReceipt)
    {
        if (! $goodsReceipt->isDraft()) {
            Alert::info('Not supported', 'Editing an issued Goods Receipt is not supported.');
            return redirect()->route('goods-receipts.show', $goodsReceipt);
        }

        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $goodsReceipt = $this->goodsReceiptService->saveDraft($validated, Auth::id(), $goodsReceipt);

            ActivityLog::record(
                module: 'GoodsReceipt',
                action: 'draft_updated',
                loggable: $goodsReceipt,
                description: "Updated draft Goods Receipt {$goodsReceipt->gr_no}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the list before finalizing.');
            return redirect()->route('goods-receipts.show', $goodsReceipt);
        }

        $validated = $request->validate($this->postedValidationRules());

        if (! empty($validated['purchase_order_id'])) {
            $validated['supplier_id'] = PurchaseOrder::findOrFail($validated['purchase_order_id'])->supplier_id;
        }

        try {
            $goodsReceipt = $this->goodsReceiptService->finalizeDraft($goodsReceipt, $validated, Auth::id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        ActivityLog::record(
            module: 'GoodsReceipt',
            action: 'finalized',
            loggable: $goodsReceipt,
            description: "Finalized Goods Receipt {$goodsReceipt->gr_no}",
        );

        Alert::success('Success', 'Goods Receipt posted successfully');
        return redirect()->route('goods-receipts.show', $goodsReceipt);
    }

    /**
     * A draft never touched stock or PO balances, so deleting it outright
     * is safe. A posted receipt can never be deleted — same policy as
     * everywhere else.
     */
    public function destroy(GoodsReceipt $goodsReceipt)
    {
        if (! $goodsReceipt->isDraft()) {
            Alert::info('Not supported', 'Deleting an issued Goods Receipt is not supported.');
            return redirect()->route('goods-receipts.show', $goodsReceipt);
        }

        $grNo = $goodsReceipt->gr_no;
        $goodsReceipt->delete();

        ActivityLog::record(
            module: 'GoodsReceipt',
            action: 'draft_deleted',
            description: "Deleted draft Goods Receipt {$grNo}",
        );

        Alert::success('Deleted', "Draft {$grNo} has been deleted.");
        return redirect()->route('goods-receipts.index');
    }
}
