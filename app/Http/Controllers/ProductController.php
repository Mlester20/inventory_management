<?php

namespace App\Http\Controllers;

use App\Exports\ProductsCatalogTemplateExport;
use App\Imports\ProductsCatalogImport;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\GenericName;
use App\Models\Supplier;
use App\Models\Taxes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
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
        $snapshot = $product->getAttributes();

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
            metadata: ['deleted_record' => $snapshot],
        );

        Alert::success('Success', 'Product deleted successfully');
        return redirect()->route('inventory-items.index', ['tab' => 'products']);
    }

    /**
     * Restore a soft-deleted product from the trash.
     */
    public function restore(int $id)
    {
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Restoring items is restricted to full admin accounts.');
            return redirect()->route('inventory-items.index', ['tab' => 'products']);
        }

        $product = Product::onlyTrashed()->findOrFail($id);
        $product->restore();

        ActivityLog::record(
            module: 'Product',
            action: 'restored',
            loggable: $product,
            description: "Restored product {$product->item_name}",
        );

        Alert::success('Success', 'Product restored successfully');
        return redirect()->route('inventory-items.index', ['tab' => 'products', 'show_trashed' => 1]);
    }

    public function archive(Product $product)
    {
        $product->update(['archived_at' => now()]);

        ActivityLog::record(
            module: 'Product',
            action: 'archived',
            loggable: $product,
            description: "Archived product {$product->item_name}",
        );

        Alert::success('Success', 'Product archived.');
        return redirect()->route('inventory-items.index', ['tab' => 'products']);
    }

    public function unarchive(Product $product)
    {
        $product->update(['archived_at' => null]);

        ActivityLog::record(
            module: 'Product',
            action: 'unarchived',
            loggable: $product,
            description: "Unarchived product {$product->item_name}",
        );

        Alert::success('Success', 'Product unarchived.');
        return redirect()->route('inventory-items.index', ['tab' => 'products', 'show_archived' => 1]);
    }

    /**
     * Bulk-import a product catalog from an uploaded Excel/CSV file.
     * Unlike Supplier/Customer's simple one-model-per-row import, this one
     * also creates whatever Category/Generic Name rows a product needs
     * underneath it — see ProductsCatalogImport for the chunked-processing
     * approach (500 rows/transaction) this needed instead.
     */
    public function import(Request $request)
    {
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'Importing products is restricted to full admin accounts.');
            return redirect()->route('inventory-items.index', ['tab' => 'products']);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        $import = new ProductsCatalogImport();
        Excel::import($import, $request->file('file'));

        ActivityLog::record(
            module: 'Product',
            action: 'imported',
            description: "Imported {$import->productsImported} product(s) from Excel "
                . "({$import->categoriesCreated} new categories, {$import->genericNamesCreated} new generic names; "
                . "{$import->duplicatesSkipped} duplicate row(s), " . count($import->crossCategorySkips()) . ' cross-category row(s) skipped)',
        );

        $summary = "{$import->productsImported} product(s) imported "
            . "({$import->categoriesCreated} new categories, {$import->genericNamesCreated} new generic names).";

        $skipNotes = [];
        if ($import->duplicatesSkipped > 0) {
            $skipNotes[] = "{$import->duplicatesSkipped} duplicate row(s) skipped";
        }
        if (count($import->crossCategorySkips()) > 0) {
            $skipNotes[] = count($import->crossCategorySkips()) . ' row(s) skipped — generic description already exists under a different category: '
                . implode('; ', array_slice($import->crossCategorySkips(), 0, 5))
                . (count($import->crossCategorySkips()) > 5 ? ' …' : '');
        }

        if (empty($skipNotes)) {
            Alert::success('Success', $summary);
        } else {
            Alert::error('Imported with some rows skipped', $summary . ' ' . implode(' | ', $skipNotes));
        }

        return redirect()->route('inventory-items.index', ['tab' => 'products']);
    }

    /**
     * Downloadable blank template matching ProductsCatalogImport's expected columns.
     */
    public function downloadTemplate()
    {
        return Excel::download(new ProductsCatalogTemplateExport(), 'products-import-template.xlsx');
    }
}
