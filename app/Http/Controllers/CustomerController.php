<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $customers = Customer::withCount(['salesOrders', 'deliveryReceipts'])->get();

        // Invoices aren't linked to a customer_id (invoices.customer_name is
        // free text — see the earlier Sales Per Customer report), so this is
        // a best-effort name match, same caveat as that report.
        $invoiceCounts = \App\Models\Invoice::selectRaw('customer_name, COUNT(*) as count, SUM(amount_due) as total_due')
            ->groupBy('customer_name')
            ->get()
            ->keyBy('customer_name');

        foreach ($customers as $customer) {
            $invoiceData = $invoiceCounts->get($customer->customer_name);
            $customer->sales_invoices_count = $invoiceData->count ?? 0;
            // "Receivables" proxy = sum of invoice amount_due for this customer.
            // There's no payments/AR ledger in this app yet, so this doesn't
            // account for money already collected — it's the closest available
            // approximation, not a true running balance.
            $customer->receivables = $invoiceData->total_due ?? 0;
        }

        return view('admin.customers', compact('customers'));
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
        //redirect to the customers page
        Alert::success('Success', 'Customer updated successfully');
        return redirect()->route('customers.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        //delete the customer
        $customer->delete();
        Alert::success('Success', 'Customer deleted successfully');
        return redirect()->route('customers.index');
    }
}
