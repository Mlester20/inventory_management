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
        $customers = Customer::all();
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
            'customer_type' => 'required|in:' . implode(',', array_keys(Customer::CUSTOMER_TYPES)),
            'contact_person' => 'nullable',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => 'nullable|unique:customers,phone',
            'address' => 'nullable',
        ]);
        $customer = Customer::create([
            'customer_name' => $request->customer_name,
            'customer_type' => $request->customer_type,
            'contact_person' => $request->contact_person,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
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
            'customer_type' => 'required|in:' . implode(',', array_keys(Customer::CUSTOMER_TYPES)),
            'contact_person' => 'nullable',
            'email' => 'nullable|email|unique:customers,email,' . $customer->id,
            'phone' => 'nullable|unique:customers,phone,' . $customer->id,
            'address' => 'nullable',
        ]);
        //update the customer
        $customer->update([
            'customer_name' => $request->customer_name,
            'customer_type' => $request->customer_type,
            'contact_person' => $request->contact_person,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
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
