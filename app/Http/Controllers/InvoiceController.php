<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Location;
use App\Models\Product;
use App\Models\Taxes;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $invoices = Invoice::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('sales_no', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.invoices.index', compact('invoices', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Invoice checkout deducts via FEFO at the POS location (same
        // immediate-sale semantics as the POS screen, just issued from the
        // admin/back-office side) — availability shown here must match.
        $posLocationId = Location::pos()->id;

        $products = Product::with('tax')
            ->withSum(['locationStocks as pos_qty' => fn ($q) => $q->where('location_id', $posLocationId)], 'qty')
            ->orderBy('item_name')->get();
        $salesNo = $this->generateSalesNo();
        $activeVatRate = (float) (Taxes::where('is_active', true)->value('rate') ?? 0);
        $users = User::orderBy('name')->get();

        $itemsForJs = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->item_name,
                'price' => (float) $product->unit_price,
                'quantity' => (int) ($product->pos_qty ?? 0),
                'unit' => 'pc',
                'taxable' => $product->tax_id !== null,
            ];
        })->values();

        return view('admin.invoices.create', compact('products', 'salesNo', 'activeVatRate', 'itemsForJs', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'po_no' => 'nullable|string|max:255',
            'osca_no' => 'nullable|string|max:255',
            'prepared_by' => 'nullable|exists:users,id',
            'approved_by' => 'nullable|string|max:255',
            'less_wt' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.dis' => 'nullable|numeric|min:0',
            'items.*.desc' => 'nullable|string|max:255',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.batch_no' => 'nullable|string|max:100',
            'items.*.exp' => 'nullable|date',
            'items.*.tax_override' => 'nullable|in:vatable,vatex,zero',
        ]);

        // Per BIR rules, VAT is computed using the currently active VAT rate
        // in the taxes table, not each item's individually linked tax rate.
        $activeVatRate = (float) (Taxes::where('is_active', true)->value('rate') ?? 0);

        try {
            $invoice = DB::transaction(function () use ($validated, $activeVatRate) {
                $stockService = new StockService();
                $userId = Auth::id();
                $posLocation = Location::pos();

                $vatSales = 0;
                $vatexSales = 0;
                $zeroSales = 0;
                $vatAmount = 0;
                $saleLines = [];

                foreach ($validated['items'] as $line) {
                    $product = Product::findOrFail($line['item_id']);
                    $qty = (int) $line['qty'];

                    $available = $product->quantityAtLocation($posLocation->id);
                    if ($available < $qty) {
                        throw ValidationException::withMessages([
                            'items' => "Insufficient stock for {$product->item_name}. Available: {$available}",
                        ]);
                    }

                    $price = (float) $line['price'];
                    $dis = (float) ($line['dis'] ?? 0);
                    $lineAmount = ($qty * $price) - $dis;

                    // Default classification comes from whether the product has a tax
                    // assigned; the form may override per line (e.g. SC/PWD or
                    // zero-rated sales) regardless of the product's default tax.
                    $classification = $line['tax_override'] ?? ($product->tax_id ? 'vatable' : 'vatex');

                    $lineVat = 0;
                    if ($classification === 'vatable') {
                        $vatSales += $lineAmount;
                        $lineVat = round($lineAmount * ($activeVatRate / 100), 2);
                        $vatAmount += $lineVat;
                    } elseif ($classification === 'zero') {
                        $zeroSales += $lineAmount;
                    } else {
                        $vatexSales += $lineAmount;
                    }

                    $saleLines[] = [
                        'product' => $product,
                        'qty' => $qty,
                        'desc' => $line['desc'] ?? $product->item_name,
                        'unit' => $line['unit'] ?? null,
                        'price' => $price,
                        'vat' => $lineVat,
                        'dis' => $dis,
                        'amount' => $lineAmount,
                    ];
                }

                $totalSales = $vatSales + $vatexSales + $zeroSales + $vatAmount;
                $oscaNo = $validated['osca_no'] ?? null;

                if ($oscaNo) {
                    // VAT is removed before applying the SC/PWD discount, and the
                    // 20% discount only applies to the portion that was actually
                    // VATable — lines already VAT-exempt/zero-rated for other
                    // reasons are not discounted a second time.
                    $lessVat = $vatAmount;
                    $amountNet = $totalSales - $lessVat;
                    $lessSc = round($vatSales * 0.20, 2);
                } else {
                    $lessVat = 0;
                    $amountNet = $totalSales;
                    $lessSc = 0;
                }

                $lessWt = (float) ($validated['less_wt'] ?? 0);
                $amountDue = $amountNet - $lessSc - $lessWt;

                $salesNo = $this->generateSalesNo();

                $invoice = Invoice::create([
                    'customer_name' => $validated['customer_name'],
                    'po_no' => $validated['po_no'] ?? null,
                    'osca_no' => $oscaNo,
                    'sales_no' => $salesNo,
                    'prepared_by' => $validated['prepared_by'] ?? $userId,
                    'approved_by' => $validated['approved_by'] ?? null,
                    'vat_sales' => round($vatSales, 2),
                    'vatex_sales' => round($vatexSales, 2),
                    'zero_sales' => round($zeroSales, 2),
                    'vat_amount' => round($vatAmount, 2),
                    'total_sales' => round($totalSales, 2),
                    'less_vat' => round($lessVat, 2),
                    'amount_net' => round($amountNet, 2),
                    'less_sc' => round($lessSc, 2),
                    'less_wt' => round($lessWt, 2),
                    'amount_due' => round($amountDue, 2),
                    'add_vat' => 0,
                ]);

                foreach ($saleLines as $line) {
                    $movements = $stockService->deductFefo($line['product'], $line['qty'], $posLocation, 'Invoice ' . $salesNo, $userId, $invoice);

                    foreach ($movements as $movement) {
                        $movementQty = abs($movement->quantity);
                        $ratio = $movementQty / $line['qty'];
                        $batch = $movement->productBatch;

                        $invoice->sales()->create([
                            'product_batch_id' => $batch->id,
                            'desc' => $line['desc'],
                            'qty' => $movementQty,
                            'unit' => $line['unit'],
                            'batch_no' => $batch->batch_no,
                            'exp' => $batch->expiration_date,
                            'price' => $line['price'],
                            'vat' => round($line['vat'] * $ratio, 2),
                            'dis' => round($line['dis'] * $ratio, 2),
                            'amount' => round($line['amount'] * $ratio, 2),
                        ]);
                    }
                }

                return $invoice;
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        Alert::success('Success', 'Invoice created successfully');
        return redirect()->route('invoices.show', $invoice);
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        $invoice->load('sales.productBatch.product.tax', 'preparedBy');

        return view('admin.invoices.show', compact('invoice'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Invoice $invoice)
    {
        Alert::info('Not supported', 'Editing an issued invoice is not supported.');
        return redirect()->route('invoices.show', $invoice);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Invoice $invoice)
    {
        Alert::info('Not supported', 'Editing an issued invoice is not supported.');
        return redirect()->route('invoices.show', $invoice);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice)
    {
        if ($invoice->sales()->exists()) {
            Alert::error('Cannot delete', 'This invoice has recorded sales and stock deductions and cannot be deleted.');
            return redirect()->route('invoices.index');
        }

        $invoice->delete();

        Alert::success('Success', 'Invoice deleted successfully');
        return redirect()->route('invoices.index');
    }

    /**
     * Generate the next sequential sales number for the current year,
     * e.g. INV-2026-00001.
     */
    private function generateSalesNo(): string
    {
        $year = now()->year;
        $prefix = "INV-{$year}-";

        $lastSalesNo = Invoice::where('sales_no', 'like', "{$prefix}%")
            ->orderByDesc('sales_no')
            ->value('sales_no');

        $nextSequence = 1;
        if ($lastSalesNo) {
            $nextSequence = (int) substr($lastSalesNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
