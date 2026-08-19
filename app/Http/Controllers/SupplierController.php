<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $suppliers = Supplier::withCount([
                'purchaseOrders', 'goodsReceipts', 'purchaseInvoices',
                // GRNI (Goods Received Not Invoiced): Goods Receipts already
                // in the Warehouse with no Purchase Invoice against them yet.
                // Replaces the old "Advances" column, which was a payment
                // figure and didn't match what that slot was supposed to
                // mean — see docs/supplier-grni-plan.md.
                'goodsReceipts as grni_count' => fn ($query) => $query->doesntHave('purchaseInvoices'),
            ])
            ->with([
                'payments' => fn ($query) => $query->latest('payment_date')->limit(5),
            ])
            ->orderBy('supplier_name')
            ->paginate(15);

        // Payables are based on recorded Purchase Invoices (the supplier's
        // actual billing document), not Goods Receipts or Purchase Orders —
        // receiving goods isn't a liability until the supplier has actually
        // billed for them.
        $invoiceTotals = PurchaseInvoice::selectRaw('supplier_id, SUM(amount) as total')
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        // Payments are recorded against a real supplier_id (see
        // SupplierPayment), so these totals are exact. Split by type since
        // "advance" (prepayment, not yet tied to any delivery) and "payment"
        // (paid against an existing/invoiced balance) are declared explicitly
        // by whoever records the payment, not inferred from the numbers.
        $paymentTotals = SupplierPayment::selectRaw('supplier_id, type, SUM(amount) as total')
            ->groupBy('supplier_id', 'type')
            ->get()
            ->groupBy('supplier_id');

        foreach ($suppliers as $supplier) {
            $supplier->payables = (float) ($invoiceTotals->get($supplier->id)?->total ?? 0);

            $supplierPayments = $paymentTotals->get($supplier->id, collect());
            $totalPayments = (float) $supplierPayments->firstWhere('type', 'payment')?->total;
            $totalAdvances = (float) $supplierPayments->firstWhere('type', 'advance')?->total;

            // Advance payments on file (prepayment credit, declared when the
            // payment was recorded) — no longer the list's "Advances" column
            // (that's now GRNI, see grni_count above); kept for the View
            // modal only. Renamed from $supplier->advances to avoid reading
            // as the same concept as GRNI.
            $supplier->advance_payments = $totalAdvances;

            // "Balances" = true bottom-line amount owed, after both payments
            // and advances (can go negative if we've paid more than we've
            // been invoiced for).
            $supplier->balance = $supplier->payables - $totalPayments - $totalAdvances;
        }

        return view('admin.supplier', compact('suppliers'));
    }

    /**
     * Record a payment against a supplier's running balance.
     */
    public function storePayment(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(SupplierPayment::TYPES)),
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|in:' . implode(',', array_keys(SupplierPayment::PAYMENT_METHODS)),
            'remarks' => 'nullable|string|max:255',
        ]);

        $payment = $supplier->payments()->create([
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'prepared_by' => auth()->id(),
        ]);

        ActivityLog::record(
            module: 'Supplier',
            action: 'payment_recorded',
            loggable: $payment,
            description: "Recorded {$validated['type']} of {$validated['amount']} for supplier {$supplier->supplier_name}",
        );

        Alert::success('Success', 'Payment recorded successfully');
        return redirect()->route('suppliers.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Adding suppliers is restricted to full admin accounts.');
            return redirect()->route('suppliers.index');
        }

        //validate the request
        $request->validate([
            'supplier_name' => 'required|unique:suppliers,supplier_name',
            'contact_person' => 'required',
            'email' => 'required|email|unique:suppliers,email',
            'contact_number' => 'required|unique:suppliers,contact_number',
            'delivery_address' => 'required',
            'vat_type' => 'required|in:' . implode(',', array_keys(Supplier::VAT_TYPES)),
        ]);
        $supplier = Supplier::create([
            'supplier_name' => $request->supplier_name,
            'contact_person' => $request->contact_person,
            'email' => $request->email,
            'contact_number' => $request->contact_number,
            'delivery_address' => $request->delivery_address,
            'vat_type' => $request->vat_type,
        ]);

        ActivityLog::record(
            module: 'Supplier',
            action: 'created',
            loggable: $supplier,
            description: "Created supplier {$supplier->supplier_name}",
        );

        Alert::success('Success', 'Supplier created successfully');
        return redirect()->route('suppliers.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Editing suppliers is restricted to full admin accounts.');
            return redirect()->route('suppliers.index');
        }

        //validate the request
        $request->validate([
            'supplier_name' => 'required|unique:suppliers,supplier_name,' . $supplier->id,
            'contact_person' => 'required',
            'email' => 'required|email|unique:suppliers,email,' . $supplier->id,
            'contact_number' => 'required|unique:suppliers,contact_number,' . $supplier->id,
            'delivery_address' => 'required',
            'vat_type' => 'required|in:' . implode(',', array_keys(Supplier::VAT_TYPES)),
        ]);
        //update the supplier
        $original = $supplier->getOriginal();
        $supplier->update([
            'supplier_name' => $request->supplier_name,
            'contact_person' => $request->contact_person,
            'email' => $request->email,
            'contact_number' => $request->contact_number,
            'delivery_address' => $request->delivery_address,
            'vat_type' => $request->vat_type,
        ]);

        $changes = $supplier->getChanges();
        ActivityLog::record(
            module: 'Supplier',
            action: 'updated',
            loggable: $supplier,
            description: "Updated supplier {$supplier->supplier_name}",
            metadata: [
                'before' => collect($changes)->keys()->mapWithKeys(fn ($key) => [$key => $original[$key] ?? null])->toArray(),
                'after' => $changes,
            ],
        );

        //redirect to the suppliers page
        Alert::success('Success', 'Supplier updated successfully');
        return redirect()->route('suppliers.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        $supplierName = $supplier->supplier_name;

        //delete the supplier
        $supplier->delete();

        ActivityLog::record(
            module: 'Supplier',
            action: 'deleted',
            loggable: $supplier,
            description: "Deleted supplier {$supplierName}",
        );

        Alert::success('Success', 'Supplier deleted successfully');
        return redirect()->route('suppliers.index');
    }
}
