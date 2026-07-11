<?php

namespace App\Http\Controllers;

use App\Models\Taxes;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class TaxesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $taxes = Taxes::all();
        return view('admin.taxes', compact('taxes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'tax_name' => 'required|string|unique:taxes,name',
            'rate' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        Taxes::create([
            'name' => $request->tax_name,
            'rate' => $request->rate,
            'is_active' => $request->is_active
        ]);

        Alert::success('Success', 'Tax created successfully');
        return redirect()->route('taxes.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Taxes $tax)
    {
        $request->validate([
            'tax_name' => 'required|string|unique:taxes,name,' . $tax->id,
            'rate' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $tax->update([
            'name' => $request->tax_name,
            'rate' => $request->rate,
            'is_active' => $request->is_active,
        ]);

        Alert::success('Success', 'Tax updated successfully');
        return redirect()->route('taxes.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Taxes $tax)
    {
        $tax->delete();
        Alert::success('success', 'Tax Deleted Successfully!');
        return redirect()->route('taxes.index');
    }
}