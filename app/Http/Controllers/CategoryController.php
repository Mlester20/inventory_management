<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::withCount(['genericNames', 'products'])
            ->orderBy('category_name')
            ->paginate(15);

        return view('admin.categories', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_name' => 'required|unique:categories,category_name',
        ]);

        $category = Category::create([
            'category_name' => $request->category_name,
        ]);

        ActivityLog::record(
            module: 'Category',
            action: 'created',
            loggable: $category,
            description: "Created category {$category->category_name}",
        );

        Alert::success('Success', 'Category created successfully');
        return redirect()->route('categories.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $request->validate([
            'category_name' => 'required|unique:categories,category_name,' . $category->id,
        ]);

        $original = $category->getOriginal();
        $category->update([
            'category_name' => $request->category_name,
        ]);

        $changes = $category->getChanges();
        ActivityLog::record(
            module: 'Category',
            action: 'updated',
            loggable: $category,
            description: "Updated category {$category->category_name}",
            metadata: [
                'before' => collect($changes)->keys()->mapWithKeys(fn ($key) => [$key => $original[$key] ?? null])->toArray(),
                'after' => $changes,
            ],
        );

        Alert::success('Success', 'Category updated successfully');
        return redirect()->route('categories.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        if ($category->genericNames()->exists() || $category->products()->exists()) {
            Alert::error('Cannot delete', 'This category still has Generic Names or Products assigned to it and cannot be deleted.');
            return redirect()->route('categories.index');
        }

        $categoryName = $category->category_name;
        $category->delete();

        ActivityLog::record(
            module: 'Category',
            action: 'deleted',
            loggable: $category,
            description: "Deleted category {$categoryName}",
        );

        Alert::success('Success', 'Category deleted successfully');
        return redirect()->route('categories.index');
    }
}