<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Item;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $purchases = Purchase::all();
        $items = Item::all();
        return view('admin.purchases', compact('purchases', 'items'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Purchase $purchase)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Purchase $purchase)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Purchase $purchase)
    {
        $purchase->delete();
        Alert::success('Success', 'Purchase deleted successfully');
        return redirect()->route('purchases.index');
    }
}
