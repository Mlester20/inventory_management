<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\GenericName;
use App\Models\Supplier;
use App\Models\Taxes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;

class ProductController extends Controller
{
    protected function rules(?Product $product = null): array
    {
        $uniqueCode = 'unique:products,code' . ($product ? ",{$product->id}" : '');

        return [
            'code' => "required|string|max:50|{$uniqueCode}",
            'generic_name_id' => 'required|exists:generic_names,id',
            'brand_name' => 'nullable|string|max:255',
            'description' => 'nullable',
            'barcode' => 'nullable|string|max:100',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'tax_id' => 'nullable|exists:taxes,id',
            'unit_cost' => 'nullable|numeric|min:0',
            'unit_price_percent' => 'nullable|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'wholesale_percent' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'price_1_percent' => 'nullable|numeric|min:0',
            'price_1' => 'nullable|numeric|min:0',
            'price_2_percent' => 'nullable|numeric|min:0',
            'price_2' => 'nullable|numeric|min:0',
            'price_3_percent' => 'nullable|numeric|min:0',
            'price_3' => 'nullable|numeric|min:0',
            'fda_reg_no' => 'nullable|string|max:100',
            'fda_reg_exp' => 'nullable|date',
            'custom_field_1' => 'nullable|string|max:255',
            'custom_field_2' => 'nullable|string|max:255',
            'custom_field_3' => 'nullable|string|max:255',
            'custom_field_4' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'low_stock_threshold' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('products', 'public')
            : null;

        $product = Product::create($validated + ['image' => $imagePath]);

        ActivityLog::record(
            module: 'Product',
            action: 'created',
            loggable: $product,
            description: "Created product {$product->item_name}",
        );

        Alert::success('Success', 'Product created successfully');
        return redirect()->route('inventory-items.index', ['tab' => 'products']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate($this->rules($product));

        $imagePath = $product->image;
        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $original = $product->getOriginal();
        $product->update($validated + ['image' => $imagePath]);

        $changes = $product->getChanges();
        ActivityLog::record(
            module: 'Product',
            action: 'updated',
            loggable: $product,
            description: "Updated product {$product->item_name}",
            metadata: [
                'before' => collect($changes)->keys()->mapWithKeys(fn ($key) => [$key => $original[$key] ?? null])->toArray(),
                'after' => $changes,
            ],
        );

        Alert::success('Success', 'Product updated successfully');
        return redirect()->route('inventory-items.index', ['tab' => 'products']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Deleting items is restricted to full admin accounts.');
            return redirect()->route('inventory-items.index', ['tab' => 'products']);
        }

        $productName = $product->item_name;

        try {
            $product->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                Alert::error('Cannot delete', "{$productName} still has related records (batches, purchase orders, or goods receipts) and cannot be deleted.");
                return redirect()->route('inventory-items.index', ['tab' => 'products']);
            }
            throw $e;
        }

        ActivityLog::record(
            module: 'Product',
            action: 'deleted',
            loggable: $product,
            description: "Deleted product {$productName}",
        );

        Alert::success('Success', 'Product deleted successfully');
        return redirect()->route('inventory-items.index', ['tab' => 'products']);
    }
}
