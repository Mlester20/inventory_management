<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\GenericName;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class GenericNameController extends Controller
{
    /**
     * Display a listing of the resource (legacy Categories admin page —
     * Category CRUD only; Generic Item CRUD now lives on the Inventory
     * Items module's "General Item" tab).
     */
    public function index()
    {
        $categories = Category::orderBy('category_name')->get();

        return view('admin.categories', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:generic_names,code',
            'generic_name' => 'required|unique:generic_names,generic_name',
            'category_id' => 'required|exists:categories,id',
            'unit' => 'required|string|max:50',
            'vat_type' => 'required|in:' . implode(',', array_keys(GenericName::VAT_TYPES)),
        ]);

        GenericName::create([
            'code' => $request->code,
            'generic_name' => $request->generic_name,
            'category_id' => $request->category_id,
            'unit' => $request->unit,
            'vat_type' => $request->vat_type,
        ]);

        Alert::success('Success', 'Generic item created successfully');
        return redirect()->route('inventory-items.index', ['tab' => 'general']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GenericName $genericName)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:generic_names,code,' . $genericName->id,
            'generic_name' => 'required|unique:generic_names,generic_name,' . $genericName->id,
            'category_id' => 'required|exists:categories,id',
            'unit' => 'required|string|max:50',
            'vat_type' => 'required|in:' . implode(',', array_keys(GenericName::VAT_TYPES)),
        ]);

        $genericName->update([
            'code' => $request->code,
            'generic_name' => $request->generic_name,
            'category_id' => $request->category_id,
            'unit' => $request->unit,
            'vat_type' => $request->vat_type,
        ]);

        Alert::success('Success', 'Generic item updated successfully');
        return redirect()->route('inventory-items.index', ['tab' => 'general']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GenericName $genericName)
    {
        $genericName->delete();
        Alert::success('Success', 'Generic item deleted successfully');
        return redirect()->route('inventory-items.index', ['tab' => 'general']);
    }
}
