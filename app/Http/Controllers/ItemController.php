<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $items = Item::all();
        $categories = Category::all();
        $suppliers = Supplier::all();
        return view('admin.items', compact('items', 'categories', 'suppliers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //validate the request
        $request->validate([
            'item_name' => 'required|unique:items,item_name',
            'category_id' => 'required',
            'supplier_id' => 'required',
            'description' => 'nullable',
            'quantity' => 'required|integer',
            'unit_price' => 'required|numeric',
            'low_stock_threshold' => 'required|integer',
        ]);
        Item::create([
            'item_name' => $request->item_name,
            'category_id' => $request->category_id,
            'supplier_id' => $request->supplier_id,
            'description' => $request->description,
            'quantity' => $request->quantity,
            'unit_price' => $request->unit_price,
            'low_stock_threshold' => $request->low_stock_threshold,
        ]);
        Alert::success('Success', 'Item created successfully');
        return redirect()->route('items.index');
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Item $item)
    {
        //validate the request
        $request->validate([
            'item_name' => 'required|unique:items,item_name,' . $item->id,
            'category_id' => 'required',
            'supplier_id' => 'required',
            'description' => 'nullable',
            'quantity' => 'required|integer',
            'unit_price' => 'required|numeric',
            'low_stock_threshold' => 'required|integer',
        ]);
        $item->update([
            'item_name' => $request->item_name,
            'category_id' => $request->category_id,
            'supplier_id' => $request->supplier_id,
            'description' => $request->description,
            'quantity' => $request->quantity,
            'unit_price' => $request->unit_price,
            'low_stock_threshold' => $request->low_stock_threshold,
        ]);
        Alert::success('Success', 'Item updated successfully');
        return redirect()->route('items.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Item $item)
    {
        //
        $item->delete();
        Alert::success('Success', 'Item deleted successfully');
        return redirect()->route('items.index');
    }
}
