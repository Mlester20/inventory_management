<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\GenericName;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class GenericNameController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $genericNames = GenericName::with('category')->orderBy('generic_name')->get();
        $categories = Category::orderBy('category_name')->get();

        return view('admin.categories', compact('genericNames', 'categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'generic_name' => 'required|unique:generic_names,generic_name',
            'category_id' => 'required|exists:categories,id',
            'unit' => 'required|string|max:50',
        ]);

        GenericName::create([
            'generic_name' => $request->generic_name,
            'category_id' => $request->category_id,
            'unit' => $request->unit,
        ]);

        Alert::success('Success', 'Generic name created successfully');
        return redirect()->route('generic-names.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GenericName $genericName)
    {
        $request->validate([
            'generic_name' => 'required|unique:generic_names,generic_name,' . $genericName->id,
            'category_id' => 'required|exists:categories,id',
            'unit' => 'required|string|max:50',
        ]);

        $genericName->update([
            'generic_name' => $request->generic_name,
            'category_id' => $request->category_id,
            'unit' => $request->unit,
        ]);

        Alert::success('Success', 'Generic name updated successfully');
        return redirect()->route('generic-names.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GenericName $genericName)
    {
        $genericName->delete();
        Alert::success('Success', 'Generic name deleted successfully');
        return redirect()->route('generic-names.index');
    }
}
