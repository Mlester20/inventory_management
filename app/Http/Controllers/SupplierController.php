<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $suppliers = Supplier::all();
        return view('admin.supplier', compact('suppliers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //validate the request
        $request->validate([
            'supplier_name' => 'required|unique:suppliers,supplier_name',
            'contact_person' => 'required',
            'email' => 'required|email|unique:suppliers,email',
            'phone' => 'required|unique:suppliers,phone',
            'address' => 'required',
        ]);
        Supplier::create([
            'supplier_name' => $request->supplier_name,
            'contact_person' => $request->contact_person,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
        ]);
        Alert::success('Success', 'Supplier created successfully');
        return redirect()->route('suppliers.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {
        //validate the request
        $request->validate([
            'supplier_name' => 'required|unique:suppliers,supplier_name,' . $supplier->id,
            'contact_person' => 'required',
            'email' => 'required|email|unique:suppliers,email,' . $supplier->id,
            'phone' => 'required|unique:suppliers,phone,' . $supplier->id,
            'address' => 'required',
        ]);
        //update the supplier
        $supplier->update([
            'supplier_name' => $request->supplier_name,
            'contact_person' => $request->contact_person,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
        ]);
        //redirect to the suppliers page
        Alert::success('Success', 'Supplier updated successfully');
        return redirect()->route('suppliers.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        //delete the supplier
        $supplier->delete();
        Alert::success('Success', 'Supplier deleted successfully');
        return redirect()->route('suppliers.index');
    }
}
