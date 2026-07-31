<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class PurchaseInvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $purchaseInvoices = PurchaseInvoice::query()
            ->with('supplier')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_no', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($q) use ($search) {
                            $q->where('supplier_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('invoice_date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.purchase-invoices.index', compact('purchaseInvoices', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $suppliers = Supplier::orderBy('supplier_name')->get();
        $users = User::orderBy('name')->get();

        $goodsReceipts = GoodsReceipt::with('items')
            ->orderByDesc('receipt_date')
            ->get()
            ->map(fn (GoodsReceipt $gr) => [
                'id' => $gr->id,
                'gr_no' => $gr->gr_no,
                'supplier_id' => $gr->supplier_id,
                'purchase_order_id' => $gr->purchase_order_id,
                'total' => (float) $gr->items->sum(fn ($item) => $item->qty * $item->unit_cost),
            ])->values();

        $purchaseOrders = PurchaseOrder::orderByDesc('order_date')
            ->get()
            ->map(fn (PurchaseOrder $po) => [
                'id' => $po->id,
                'po_no' => $po->po_no,
                'supplier_id' => $po->supplier_id,
            ])->values();

        return view('admin.purchase-invoices.create', compact('suppliers', 'users', 'goodsReceipts', 'purchaseOrders'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'goods_receipt_id' => 'nullable|exists:goods_receipts,id',
            'invoice_no' => 'required|string|max:255',
            'invoice_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'vat_amount' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:255',
            'prepared_by' => 'nullable|exists:users,id',
        ]);

        $purchaseInvoice = PurchaseInvoice::create([
            'supplier_id' => $validated['supplier_id'],
            'purchase_order_id' => $validated['purchase_order_id'] ?? null,
            'goods_receipt_id' => $validated['goods_receipt_id'] ?? null,
            'invoice_no' => $validated['invoice_no'],
            'invoice_date' => $validated['invoice_date'],
            'amount' => $validated['amount'],
            'vat_amount' => $validated['vat_amount'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'prepared_by' => $validated['prepared_by'] ?? auth()->id(),
        ]);

        Alert::success('Success', 'Purchase Invoice recorded successfully');
        return redirect()->route('purchase-invoices.show', $purchaseInvoice);
    }

    /**
     * Display the specified resource.
     */
    public function show(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->load('supplier', 'purchaseOrder', 'goodsReceipt', 'preparedBy');

        return view('admin.purchase-invoices.show', compact('purchaseInvoice'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PurchaseInvoice $purchaseInvoice)
    {
        Alert::info('Not supported', 'Editing a recorded Purchase Invoice is not supported.');
        return redirect()->route('purchase-invoices.show', $purchaseInvoice);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        Alert::info('Not supported', 'Editing a recorded Purchase Invoice is not supported.');
        return redirect()->route('purchase-invoices.show', $purchaseInvoice);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->delete();

        Alert::success('Success', 'Purchase Invoice deleted successfully');
        return redirect()->route('purchase-invoices.index');
    }
}
