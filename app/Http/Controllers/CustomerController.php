<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerPayment;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $customers = Customer::withCount(['salesOrders', 'deliveryReceipts'])
            ->with(['payments' => fn ($query) => $query->latest('payment_date')->limit(5)])
            ->orderBy('customer_name')
            ->paginate(15);

        // Invoices aren't linked to a customer_id (invoices.customer_name is
        // free text — see the earlier Sales Per Customer report), so this is
        // a best-effort name match, same caveat as that report.
        $invoiceCounts = \App\Models\Invoice::selectRaw('customer_name, COUNT(*) as count, SUM(amount_due) as total_due')
            ->groupBy('customer_name')
            ->get()
            ->keyBy('customer_name');

        // Payments are recorded against a real customer_id (see
        // CustomerPayment), unlike invoices — so these totals are exact, not
        // a name-match proxy. Split by type since "advance" (prepayment,
        // not yet tied to any invoice) and "collection" (paid against an
        // existing balance) are declared explicitly by whoever records the
        // payment, not inferred from the numbers.
        $paymentTotals = CustomerPayment::selectRaw('customer_id, type, SUM(amount) as total')
            ->groupBy('customer_id', 'type')
            ->get()
            ->groupBy('customer_id');

        foreach ($customers as $customer) {
            $invoiceData = $invoiceCounts->get($customer->customer_name);
            $customer->sales_invoices_count = $invoiceData->count ?? 0;

            // "Receivables" = gross invoiced amount (unchanged meaning from
            // before) — this doesn't yet account for payments, matching how
            // it always worked here.
            $customer->receivables = (float) ($invoiceData->total_due ?? 0);

            $customerPayments = $paymentTotals->get($customer->id, collect());
            $totalCollections = (float) $customerPayments->firstWhere('type', 'collection')?->total;
            $totalAdvances = (float) $customerPayments->firstWhere('type', 'advance')?->total;

            // "Advances" = prepayment credit currently on file, as declared
            // when the payment was recorded.
            $customer->advances = $totalAdvances;

            // "Balances" = true bottom-line net owed, after both collections
            // and advances (can go negative if the customer has paid more
            // than they've been invoiced for).
            $customer->balance = $customer->receivables - $totalCollections - $totalAdvances;
        }

        return view('admin.customers', compact('customers'));
    }

    /**
     * Record a payment/collection against a customer's running balance.
     */
    public function storePayment(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(CustomerPayment::TYPES)),
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|in:' . implode(',', array_keys(CustomerPayment::PAYMENT_METHODS)),
            'remarks' => 'nullable|string|max:255',
        ]);

        $payment = $customer->payments()->create([
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'prepared_by' => auth()->id(),
        ]);

        ActivityLog::record(
            module: 'Customer',
            action: 'payment_recorded',
            loggable: $payment,
            description: "Recorded {$validated['type']} of {$validated['amount']} for customer {$customer->customer_name}",
        );

        Alert::success('Success', 'Payment recorded successfully');
        return redirect()->route('customers.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //validate the request
        $request->validate([
            'customer_name' => 'required|unique:customers,customer_name',
            'delivery_address' => 'nullable|string',
            'contact_number' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'contact_person' => 'nullable|string|max:255',
            'customer_type' => 'nullable|string|max:255',
            'price_level' => 'required|in:' . implode(',', array_keys(Customer::PRICE_LEVELS)),
            'vat_type' => 'required|in:' . implode(',', array_keys(Customer::VAT_TYPES)),
        ]);
        $customer = Customer::create([
            'customer_name' => $request->customer_name,
            'delivery_address' => $request->delivery_address,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            'contact_person' => $request->contact_person,
            'customer_type' => $request->customer_type,
            'price_level' => $request->price_level,
            'vat_type' => $request->vat_type,
        ]);

        ActivityLog::record(
            module: 'Customer',
            action: 'created',
            loggable: $customer,
            description: "Created customer {$customer->customer_name}",
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'customer' => $customer,
            ], 201);
        }

        Alert::success('Success', 'Customer created successfully');
        return redirect()->route('customers.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        //validate the request
        $request->validate([
            'customer_name' => 'required|unique:customers,customer_name,' . $customer->id,
            'delivery_address' => 'nullable|string',
            'contact_number' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:customers,email,' . $customer->id,
            'contact_person' => 'nullable|string|max:255',
            'customer_type' => 'nullable|string|max:255',
            'price_level' => 'required|in:' . implode(',', array_keys(Customer::PRICE_LEVELS)),
            'vat_type' => 'required|in:' . implode(',', array_keys(Customer::VAT_TYPES)),
        ]);
        //update the customer
        $original = $customer->getOriginal();
        $customer->update([
            'customer_name' => $request->customer_name,
            'delivery_address' => $request->delivery_address,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            'contact_person' => $request->contact_person,
            'customer_type' => $request->customer_type,
            'price_level' => $request->price_level,
            'vat_type' => $request->vat_type,
        ]);

        $changes = $customer->getChanges();
        ActivityLog::record(
            module: 'Customer',
            action: 'updated',
            loggable: $customer,
            description: "Updated customer {$customer->customer_name}",
            metadata: [
                'before' => collect($changes)->keys()->mapWithKeys(fn ($key) => [$key => $original[$key] ?? null])->toArray(),
                'after' => $changes,
            ],
        );

        //redirect to the customers page
        Alert::success('Success', 'Customer updated successfully');
        return redirect()->route('customers.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        $customerName = $customer->customer_name;

        //delete the customer
        $customer->delete();

        ActivityLog::record(
            module: 'Customer',
            action: 'deleted',
            loggable: $customer,
            description: "Deleted customer {$customerName}",
        );

        Alert::success('Success', 'Customer deleted successfully');
        return redirect()->route('customers.index');
    }
}
