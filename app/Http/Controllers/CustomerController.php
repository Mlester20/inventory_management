<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Services\CustomerPaymentService;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Advances = count of this customer's Advance Order Delivery Receipts.
     * Balances = count of this customer's invoices still carrying an
     * outstanding balance. Receivables = the real peso amount still owed,
     * summed across those unpaid invoices — accurate now that invoices carry
     * a real customer_id (previously a free-text customer_name match).
     * Invoices left unlinked by the backfill (no exact name match) are
     * excluded here — expected, see the backfill migration.
     */
    public function index()
    {
        $customers = Customer::withCount([
                'salesOrders',
                'deliveryReceipts',
                'deliveryReceipts as advances_count' => fn ($query) => $query->where('transaction_type', 'advance_order'),
                'invoices as sales_invoices_count',
                'invoices as balances_count' => fn ($query) => $query->whereColumn('amount_paid', '<', 'amount_due'),
            ])
            ->with(['payments' => fn ($query) => $query->latest('payment_date')->limit(5)])
            ->orderBy('customer_name')
            ->paginate(15);

        $receivables = Invoice::whereNotNull('customer_id')
            ->whereColumn('amount_paid', '<', 'amount_due')
            ->selectRaw('customer_id, SUM(amount_due - amount_paid) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        // Available credit = unconsumed 'advance' CustomerPayment amounts —
        // what's left to auto-apply against this customer's next invoice
        // (see CustomerPaymentService::applyAvailableCredit()).
        $availableCredit = CustomerPayment::where('type', 'advance')
            ->whereColumn('consumed_amount', '<', 'amount')
            ->selectRaw('customer_id, SUM(amount - consumed_amount) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        foreach ($customers as $customer) {
            $customer->receivables = (float) ($receivables->get($customer->id) ?? 0);
            $customer->available_credit = (float) ($availableCredit->get($customer->id) ?? 0);
        }

        return view('admin.customers', compact('customers'));
    }

    /**
     * Record a payment/collection against a customer's running balance. A
     * 'collection' payment is FIFO-applied against the customer's oldest
     * unpaid invoices by CustomerPaymentService — see that class.
     */
    public function storePayment(Request $request, Customer $customer, CustomerPaymentService $customerPaymentService)
    {
        $validated = $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(CustomerPayment::TYPES)),
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|in:' . implode(',', array_keys(CustomerPayment::PAYMENT_METHODS)),
            'remarks' => 'nullable|string|max:255',
        ]);

        $payment = $customerPaymentService->recordPayment($customer, $validated, auth()->id());

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
        // admin_staff is blocked from the dedicated Customers page (a plain
        // form POST, no Accept: application/json), but stays able to use the
        // inline "quick add" popup during Delivery Receipt creation, which
        // hits this same endpoint via fetch() with Accept: application/json
        // — see resources/views/admin/delivery-receipts/create.blade.php.
        if (auth()->user()->role === 'admin_staff' && ! $request->expectsJson()) {
            Alert::error('Not allowed', 'Adding customers is restricted to full admin accounts.');
            return redirect()->route('customers.index');
        }

        //validate the request
        $request->validate([
            'customer_name' => 'required|unique:customers,customer_name',
            'delivery_address' => 'nullable|string',
            'contact_number' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'contact_person' => 'nullable|string|max:255',
            'customer_type' => 'required|string|max:255',
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
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Editing customers is restricted to full admin accounts.');
            return redirect()->route('customers.index');
        }

        //validate the request
        $request->validate([
            'customer_name' => 'required|unique:customers,customer_name,' . $customer->id,
            'delivery_address' => 'nullable|string',
            'contact_number' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:customers,email,' . $customer->id,
            'contact_person' => 'nullable|string|max:255',
            'customer_type' => 'required|string|max:255',
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
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Deleting customers is restricted to full admin accounts.');
            return redirect()->route('customers.index');
        }

        $customerName = $customer->customer_name;

        try {
            $customer->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                Alert::error('Cannot delete', "{$customerName} still has related records (sales orders, delivery receipts, invoices, or payments) and cannot be deleted.");
                return redirect()->route('customers.index');
            }
            throw $e;
        }

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
