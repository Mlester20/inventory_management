<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
// sweet alert
use RealRashid\SweetAlert\Facades\Alert;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::all();
        return view('admin.categories', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //validate the request
        $request->validate([
            'category_name' => 'required|unique:categories,category_name',
        ]);

        //create the category
        Category::create([
            'category_name' => $request->category_name,
        ]);

        //redirect to the categories page
        Alert::success('Success', 'Category created successfully');
        return redirect()->route('categories.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        //validate the request
        $request->validate([
            'category_name' => 'required|unique:categories,category_name,' . $category->id,
        ]);

        //update the category
        $category->update([
            'category_name' => $request->category_name,
        ]);

        //redirect to the categories page
        Alert::success('Success', 'Category updated successfully');
        return redirect()->route('categories.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        //
        $category->delete();        
        Alert::success('Success', 'Category deleted successfully');
        return redirect()->route('categories.index');
    }
}