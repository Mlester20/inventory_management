<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Product;
use App\Models\Taxes;
use App\Models\User;
use App\Services\CustomerPaymentService;
use App\Services\InvoiceDraftService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceDraftService $invoiceDraftService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $showTrashed = $request->boolean('show_trashed');
        $showArchived = $request->boolean('show_archived');

        // Drafts are hidden from every query by the model's 'notDraft' global
        // scope; the list is one of the few places that has to show them.
        $invoices = Invoice::query()
            ->withoutGlobalScope('notDraft')
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->when(! $showTrashed && $showArchived, fn ($q) => $q->whereNotNull('archived_at'))
            ->when(! $showTrashed && ! $showArchived, fn ($q) => $q->whereNull('archived_at'))
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('sales_no', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.invoices.index', compact('invoices', 'search', 'showTrashed', 'showArchived'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.invoices.create', $this->formData());
    }

    /**
     * Data shared by the create form and the edit-draft form. When editing a
     * draft, its saved lines are mapped into the shape the line-item JS
     * prefills from; a failed validation redirect flashes the submitted
     * `items` via old() and takes precedence (the user's latest, unsaved
     * edits) — same approach Sales Order/Sales Quote use.
     */
    protected function formData(?Invoice $editing = null): array
    {
        // Invoice checkout deducts via FEFO at the POS location (same
        // immediate-sale semantics as the POS screen, just issued from the
        // admin/back-office side) — availability shown here must match.
        $posLocationId = Location::pos()->id;
        $warehouseLocationId = Location::warehouse()->id;

        // The Warehouse quantity is shown for information only — a direct
        // Invoice never reads or deducts it, but without it a product that is
        // stocked in the Warehouse and not yet transferred to POS just looks
        // like it has no stock at all.
        $products = Product::with('tax')
            ->withSum(['locationStocks as pos_qty' => fn ($q) => $q->where('location_id', $posLocationId)], 'qty')
            ->withSum(['locationStocks as warehouse_qty' => fn ($q) => $q->where('location_id', $warehouseLocationId)], 'qty')
            ->orderBy('item_name')->get();
        $salesNo = $editing?->sales_no ?? $this->generateSalesNo();
        $activeVatRate = Taxes::activeRate();
        $users = User::orderBy('name')->get();
        $customers = Customer::orderBy('customer_name')->get(['id', 'customer_name', 'wt_rate_goods', 'wt_rate_services']);

        // The customers' withholding rates ride along so the form can suggest the
        // Withholding Tax (see the Invoice form's script).
        $customersForJs = $customers->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->customer_name,
            'wt_goods' => $c->wt_rate_goods !== null ? (float) $c->wt_rate_goods : null,
            'wt_services' => $c->wt_rate_services !== null ? (float) $c->wt_rate_services : null,
        ])->values();

        $itemsForJs = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->description ?: $product->item_name,
                'price' => (float) $product->unit_price,
                'quantity' => (int) ($product->pos_qty ?? 0),
                'warehouse_quantity' => (int) ($product->warehouse_qty ?? 0),
                'unit' => 'pc',
                'tax_classification' => $product->taxClassification(),
                'taxable' => $product->taxClassification() === 'vatable',
            ];
        })->values();

        $prefillLines = [];

        if ($editing) {
            $editing->load('draftItems');

            $prefillLines = $editing->draftItems->map(fn ($line) => [
                'item_id' => $line->product_id,
                'desc' => $line->desc,
                'unit' => $line->unit,
                'batch_no' => $line->batch_no,
                'exp' => $line->exp?->toDateString(),
                'qty' => $line->qty,
                'price' => $line->price !== null ? (float) $line->price : null,
                'dis' => $line->dis !== null ? (float) $line->dis : null,
                'tax_override' => $line->tax_override,
                'wt_type' => $line->wt_type,
            ])->values();
        }

        if (old('items')) {
            $prefillLines = collect(old('items'))->map(fn ($line) => [
                'item_id' => $line['item_id'] ?? null,
                'desc' => $line['desc'] ?? null,
                'unit' => $line['unit'] ?? null,
                'batch_no' => $line['batch_no'] ?? null,
                'exp' => $line['exp'] ?? null,
                'qty' => $line['qty'] ?? null,
                'price' => $line['price'] ?? null,
                'dis' => $line['dis'] ?? null,
                'tax_override' => $line['tax_override'] ?? null,
                'wt_type' => $line['wt_type'] ?? null,
            ])->values();
        }

        return compact('products', 'salesNo', 'activeVatRate', 'itemsForJs', 'users', 'customers', 'customersForJs', 'prefillLines') + ['editingInvoice' => $editing];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, CustomerPaymentService $customerPaymentService)
    {
        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            $invoice = $this->invoiceDraftService->saveDraft($validated, Auth::id());

            ActivityLog::record(
                module: 'Invoice',
                action: 'draft_saved',
                loggable: $invoice,
                description: "Saved draft Invoice {$invoice->sales_no}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the Invoices list before posting.');
            return redirect()->route('invoices.show', $invoice);
        }

        return $this->postInvoice($request->validate($this->postedValidationRules()), null, $customerPaymentService);
    }

    /**
     * Loose rules for a draft — an interrupted encoder can leave anything
     * blank or half-typed, so nothing here can block the save.
     */
    protected function draftValidationRules(): array
    {
        return [
            'customer_name' => 'nullable|string|max:255',
            'customer_id' => 'nullable|exists:customers,id',
            'po_no' => 'nullable|string|max:255',
            'osca_no' => 'nullable|string|max:255',
            'prepared_by' => 'nullable|exists:users,id',
            'approved_by' => 'nullable|string|max:255',
            'less_wt' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.item_id' => 'nullable|exists:products,id',
            'items.*.qty' => 'nullable|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.dis' => 'nullable|numeric|min:0',
            'items.*.desc' => 'nullable|string|max:255',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.batch_no' => 'nullable|string|max:100',
            'items.*.exp' => 'nullable|date',
            'items.*.tax_override' => 'nullable|in:vatable,vatex,zero',
            'items.*.wt_type' => 'nullable|in:goods,services',
        ];
    }

    /**
     * Strict rules for the moment stock actually moves — whether that's a
     * direct Save or posting a draft, the data must be complete.
     */
    protected function postedValidationRules(): array
    {
        return [
            'customer_name' => 'required|string|max:255',
            'customer_id' => 'nullable|exists:customers,id',
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
            'items.*.wt_type' => 'nullable|in:goods,services',
        ];
    }

    /**
     * Post an Invoice — deducts stock (FEFO, at the POS location) and records
     * the VAT breakdown. Used by a direct Save and by posting a draft; with a
     * draft, the existing row (and its sales_no) is turned into the real
     * invoice instead of creating a second one.
     */
    protected function postInvoice(array $validated, ?Invoice $draft, CustomerPaymentService $customerPaymentService)
    {
        // Per BIR rules, VAT is computed using the currently active VAT rate
        // in the taxes table, not each item's individually linked tax rate.
        $activeVatRate = Taxes::activeRate();

        try {
            $invoice = DB::transaction(function () use ($validated, $draft, $activeVatRate, $customerPaymentService) {
                if ($draft) {
                    // Re-fetch under a row lock instead of trusting the route-bound
                    // model's already-loaded flag: two near-simultaneous "Post"
                    // submissions of the same draft (e.g. a double-click) would
                    // otherwise both see is_draft=true and both deduct stock.
                    $draft = Invoice::withoutGlobalScope('notDraft')->lockForUpdate()->findOrFail($draft->id);

                    if (! $draft->isDraft()) {
                        throw ValidationException::withMessages([
                            'status' => 'This Invoice has already been posted.',
                        ]);
                    }
                }

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
                            'items' => "Insufficient stock for {$product->item_name} at {$posLocation->name}. Available at {$posLocation->name}: {$available}"
                                . $this->warehouseHint($product),
                        ]);
                    }

                    $price = (float) $line['price'];
                    $dis = (float) ($line['dis'] ?? 0);
                    $lineAmount = ($qty * $price) - $dis;

                    // Default classification comes from the product's tax (VATable,
                    // Zero-Rated at 0%, or none = VAT-exempt); the form may override
                    // per line (e.g. SC/PWD) regardless of the product's default.
                    $classification = $line['tax_override'] ?? $product->taxClassification();

                    // Per Sir: Price is the VAT-inclusive amount the customer
                    // actually pays, not a net-of-VAT base price — so a
                    // VATable line's own amount already has VAT inside it.
                    // VAT is extracted back out of it here, not added on top
                    // (adding on top double-charges VAT — the same bug fixed
                    // for Sales Order/Sales Quote).
                    $lineVat = 0;
                    if ($classification === 'vatable') {
                        $lineNet = round($lineAmount / (1 + $activeVatRate / 100), 2);
                        $lineVat = round($lineAmount - $lineNet, 2);
                        $vatSales += $lineNet;
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

                // A draft already holds its number from when it was first saved.
                $salesNo = $draft?->sales_no ?? $this->generateSalesNo();

                $attributes = [
                    'customer_name' => $validated['customer_name'],
                    'customer_id' => $validated['customer_id'] ?? null,
                    'po_no' => $validated['po_no'] ?? null,
                    'osca_no' => $oscaNo,
                    'sales_no' => $salesNo,
                    'is_draft' => false,
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
                ];

                if ($draft) {
                    $draft->update($attributes);
                    $draft->draftItems()->delete();
                    $invoice = $draft;
                } else {
                    $invoice = Invoice::create($attributes);
                }

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

                $customerPaymentService->applyAvailableCredit($invoice);

                return $invoice;
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        // Direct Invoice creation deducts from the POS location (see
        // SCHEMA_NOTES.md) — same immediate-sale semantics as the POS
        // screen, just issued from the admin/back-office side.
        ActivityLog::record(
            module: 'Invoice',
            action: 'created',
            loggable: $invoice,
            description: "Created Invoice {$invoice->sales_no} for {$invoice->customer_name} (amount due: {$invoice->amount_due})",
            source: ActivityLog::SOURCE_POS,
        );

        Alert::success('Success', 'Invoice created successfully');
        return redirect()->route('invoices.show', $invoice);
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        if ($invoice->isDraft()) {
            $invoice->load('draftItems.product', 'preparedBy', 'customer');

            return view('admin.invoices.draft', compact('invoice'));
        }

        $invoice->load('sales.productBatch.product.tax', 'preparedBy', 'customer');

        return view('admin.invoices.show', compact('invoice'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Invoice $invoice)
    {
        // Only a draft can be edited — an issued invoice already deducted
        // stock and recorded its VAT breakdown, so editing that isn't supported.
        if (! $invoice->isDraft()) {
            Alert::info('Not supported', 'Editing an issued invoice is not supported.');
            return redirect()->route('invoices.show', $invoice);
        }

        return view('admin.invoices.create', $this->formData($invoice));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Invoice $invoice, CustomerPaymentService $customerPaymentService)
    {
        if (! $invoice->isDraft()) {
            Alert::info('Not supported', 'Editing an issued invoice is not supported.');
            return redirect()->route('invoices.show', $invoice);
        }

        if ($request->input('save_action') === 'draft') {
            $validated = $request->validate($this->draftValidationRules());

            try {
                $invoice = $this->invoiceDraftService->saveDraft($validated, Auth::id(), $invoice);
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }

            ActivityLog::record(
                module: 'Invoice',
                action: 'draft_updated',
                loggable: $invoice,
                description: "Updated draft Invoice {$invoice->sales_no}",
            );

            Alert::success('Draft saved', 'Resume it anytime from the Invoices list before posting.');
            return redirect()->route('invoices.show', $invoice);
        }

        return $this->postInvoice($request->validate($this->postedValidationRules()), $invoice, $customerPaymentService);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice)
    {
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Deleting invoices is restricted to full admin accounts.');
            return redirect()->route('invoices.index');
        }

        if ($invoice->sales()->exists()) {
            Alert::error('Cannot delete', 'This invoice has recorded sales and stock deductions and cannot be deleted.');
            return redirect()->route('invoices.index');
        }

        $salesNo = $invoice->sales_no;
        $invoice->delete();

        ActivityLog::record(
            module: 'Invoice',
            action: 'deleted',
            loggable: $invoice,
            description: "Deleted Invoice {$salesNo}",
        );

        Alert::success('Success', 'Invoice deleted successfully');
        return redirect()->route('invoices.index');
    }

    /**
     * Restore a soft-deleted Invoice from the trash.
     */
    public function restore(int $id)
    {
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Restoring invoices is restricted to full admin accounts.');
            return redirect()->route('invoices.index');
        }

        $invoice = Invoice::withoutGlobalScope('notDraft')->onlyTrashed()->findOrFail($id);
        $invoice->restore();

        ActivityLog::record(
            module: 'Invoice',
            action: 'restored',
            loggable: $invoice,
            description: "Restored Invoice {$invoice->sales_no}",
        );

        Alert::success('Success', 'Invoice restored successfully');
        return redirect()->route('invoices.index', ['show_trashed' => 1]);
    }

    /**
     * Void an Invoice — status label only. Does NOT reverse the recorded
     * Sales rows or the stock they already deducted; use a Return Item or
     * Inventory Adjustment separately if the stock itself needs correcting.
     * This just marks the document as no longer valid.
     */
    public function cancel(Invoice $invoice)
    {
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Cancelling invoices is restricted to full admin accounts.');
            return redirect()->route('invoices.show', $invoice);
        }

        if ($invoice->isCancelled()) {
            Alert::info('Already cancelled', 'This invoice is already cancelled.');
            return redirect()->route('invoices.show', $invoice);
        }

        $invoice->update(['cancelled_at' => now()]);

        ActivityLog::record(
            module: 'Invoice',
            action: 'cancelled',
            loggable: $invoice,
            description: "Cancelled Invoice {$invoice->sales_no}",
        );

        Alert::success('Success', 'Invoice cancelled.');
        return redirect()->route('invoices.show', $invoice);
    }

    public function archive(Invoice $invoice)
    {
        $invoice->update(['archived_at' => now()]);

        ActivityLog::record(
            module: 'Invoice',
            action: 'archived',
            loggable: $invoice,
            description: "Archived Invoice {$invoice->sales_no}",
        );

        Alert::success('Success', 'Invoice archived.');
        return redirect()->route('invoices.index');
    }

    public function unarchive(Invoice $invoice)
    {
        $invoice->update(['archived_at' => null]);

        ActivityLog::record(
            module: 'Invoice',
            action: 'unarchived',
            loggable: $invoice,
            description: "Unarchived Invoice {$invoice->sales_no}",
        );

        Alert::success('Success', 'Invoice unarchived.');
        return redirect()->route('invoices.index', ['show_archived' => 1]);
    }

    /**
     * Generate the next sequential sales number for the current year,
     * e.g. INV-2026-00001.
     */
    /**
     * A direct Invoice only sells what is at the POS location. When the
     * product does have stock in the Warehouse, say so — otherwise "Available:
     * 0" looks like the item is out of stock everywhere.
     */
    private function warehouseHint(Product $product): string
    {
        $warehouseQty = $product->quantityAtLocation(Location::warehouse()->id);

        return $warehouseQty > 0
            ? " ({$warehouseQty} in the Warehouse — transfer it to POS with a Stock Transfer first.)"
            : '';
    }

    private function generateSalesNo(): string
    {
        return Invoice::nextSalesNo();
    }
}
