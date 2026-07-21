<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\GenericName;
use App\Models\Supplier;
use App\Models\Taxes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Item::with('tax', 'genericName.category');

        // Filter for low stock items if requested
        if ($request->query('filter') === 'low-stock') {
            $query->whereColumn('quantity', '<=', 'low_stock_threshold');
        }

        $items = $query->get();
        $genericNames = GenericName::with('category')->orderBy('generic_name')->get();
        $suppliers = Supplier::all();
        $showLowStockOnly = $request->query('filter') === 'low-stock';

        // Active taxes, plus any tax already assigned to an item even if it has
        // since been deactivated, so the Edit modal can still display it.
        $assignedTaxIds = Item::whereNotNull('tax_id')->pluck('tax_id')->unique();
        $taxes = Taxes::where('is_active', true)
            ->orWhereIn('id', $assignedTaxIds)
            ->orderBy('name')
            ->get();

        return view('admin.items', compact('items', 'genericNames', 'suppliers', 'taxes', 'showLowStockOnly'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //validate the request
        $request->validate([
            'generic_name_id' => 'required|exists:generic_names,id',
            'brand_name' => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'supplier_id' => 'required',
            'tax_id' => 'nullable|exists:taxes,id',
            'description' => 'nullable',
            'quantity' => 'required|integer',
            'unit_price' => 'required|numeric',
            'unit_cost' => 'required|numeric|min:0',
            'wholesale_percent' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'price_1_percent' => 'nullable|numeric|min:0',
            'price_1' => 'nullable|numeric|min:0',
            'price_2_percent' => 'nullable|numeric|min:0',
            'price_2' => 'nullable|numeric|min:0',
            'price_3_percent' => 'nullable|numeric|min:0',
            'price_3' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'low_stock_threshold' => 'required|integer',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'batch_no' => 'nullable|string|max:100',
            'expiration_date' => 'nullable|date',
        ]);

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('items', 'public')
            : null;

        Item::create([
            'generic_name_id' => $request->generic_name_id,
            'brand_name' => $request->brand_name,
            'serial_number' => $request->serial_number,
            'supplier_id' => $request->supplier_id,
            'tax_id' => $request->tax_id,
            'description' => $request->description,
            'quantity' => $request->quantity,
            'unit_price' => $request->unit_price,
            'unit_cost' => $request->unit_cost,
            'wholesale_percent' => $request->wholesale_percent,
            'wholesale_price' => $request->wholesale_price,
            'price_1_percent' => $request->price_1_percent,
            'price_1' => $request->price_1,
            'price_2_percent' => $request->price_2_percent,
            'price_2' => $request->price_2,
            'price_3_percent' => $request->price_3_percent,
            'price_3' => $request->price_3,
            'location' => $request->location,
            'low_stock_threshold' => $request->low_stock_threshold,
            'image' => $imagePath,
            'batch_no' => $request->batch_no,
            'expiration_date' => $request->expiration_date,
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
            'generic_name_id' => 'required|exists:generic_names,id',
            'brand_name' => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'supplier_id' => 'required',
            'tax_id' => 'nullable|exists:taxes,id',
            'description' => 'nullable',
            'quantity' => 'required|integer',
            'unit_price' => 'required|numeric',
            'unit_cost' => 'required|numeric|min:0',
            'wholesale_percent' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'price_1_percent' => 'nullable|numeric|min:0',
            'price_1' => 'nullable|numeric|min:0',
            'price_2_percent' => 'nullable|numeric|min:0',
            'price_2' => 'nullable|numeric|min:0',
            'price_3_percent' => 'nullable|numeric|min:0',
            'price_3' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'low_stock_threshold' => 'required|integer',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'batch_no' => 'nullable|string|max:100',
            'expiration_date' => 'nullable|date',
        ]);

        $imagePath = $item->image;
        if ($request->hasFile('image')) {
            if ($item->image) {
                Storage::disk('public')->delete($item->image);
            }
            $imagePath = $request->file('image')->store('items', 'public');
        }

        $item->update([
            'generic_name_id' => $request->generic_name_id,
            'brand_name' => $request->brand_name,
            'serial_number' => $request->serial_number,
            'supplier_id' => $request->supplier_id,
            'tax_id' => $request->tax_id,
            'description' => $request->description,
            'quantity' => $request->quantity,
            'unit_price' => $request->unit_price,
            'unit_cost' => $request->unit_cost,
            'wholesale_percent' => $request->wholesale_percent,
            'wholesale_price' => $request->wholesale_price,
            'price_1_percent' => $request->price_1_percent,
            'price_1' => $request->price_1,
            'price_2_percent' => $request->price_2_percent,
            'price_2' => $request->price_2,
            'price_3_percent' => $request->price_3_percent,
            'price_3' => $request->price_3,
            'location' => $request->location,
            'low_stock_threshold' => $request->low_stock_threshold,
            'image' => $imagePath,
            'batch_no' => $request->batch_no,
            'expiration_date' => $request->expiration_date,
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

    /** 
     * Display a listing of low stock items.
    */
    public function lowStock()
    {
        // Fetch items where quantity is less than or equal to low stock threshold
        $items = Item::whereColumn('quantity', '<=', 'low_stock_threshold')->get();
        return view('admin.items.low-stock', compact('items'));
    }
    
}