<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\GenericName;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Supplier;
use App\Models\Taxes;
use App\Services\InventoryReportService;
use Illuminate\Http\Request;

class InventoryItemsController extends Controller
{
    public function __construct(protected InventoryReportService $inventoryReportService) {}

    /**
     * The unified Products & Inventory module: General Item / Products /
     * Lot-Serial & Expiry / Product History tabs, matching the client
     * mockup's single tabbed screen.
     */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'general');
        $search = $request->query('search');

        $categories = Category::orderBy('category_name')->get();
        $genericNames = GenericName::with('category')->orderBy('generic_name')->get();
        $suppliers = Supplier::orderBy('supplier_name')->get();

        $assignedTaxIds = Product::whereNotNull('tax_id')->pluck('tax_id')->unique();
        $taxes = Taxes::where('is_active', true)
            ->orWhereIn('id', $assignedTaxIds)
            ->orderBy('name')
            ->get();

        $generalItems = null;
        $products = null;
        $batches = null;
        $historyProduct = null;
        $history = null;
        $nextGenericCode = null;
        $nextProductCode = null;

        if ($tab === 'general') {
            $generalItems = GenericName::with('category')
                ->withCount('products')
                ->when($search, fn ($q) => $q->where('generic_name', 'like', "%{$search}%"))
                ->orderBy('generic_name')
                ->get();

            foreach ($generalItems as $genericName) {
                $genericName->on_hand_qty = (int) ProductBatch::query()
                    ->join('products', 'products.id', '=', 'product_batches.product_id')
                    ->where('products.generic_name_id', $genericName->id)
                    ->sum('product_batches.qty');
            }

            $nextGenericCode = str_pad((string) (GenericName::max('id') + 1), 5, '0', STR_PAD_LEFT);
        }

        if ($tab === 'products') {
            $products = Product::with(['genericName', 'category'])
                ->withSum('batches', 'qty')
                ->withSum('batches as reserved_sum', 'reserved_qty')
                ->when($search, function ($q) use ($search) {
                    $q->where('item_name', 'like', "%{$search}%")
                        ->orWhere('brand_name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })
                ->orderBy('item_name')
                ->get();

            $nextProductCode = str_pad((string) (Product::max('id') + 1), 5, '0', STR_PAD_LEFT);
        }

        if ($tab === 'batches') {
            $batches = ProductBatch::with('product.category')
                ->whereHas('product', function ($q) use ($search) {
                    if ($search) {
                        $q->where('item_name', 'like', "%{$search}%")
                            ->orWhere('brand_name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    }
                })
                ->orderBy('product_id')
                ->orderBy('expiration_date')
                ->get();
        }

        if ($tab === 'history') {
            $productId = $request->query('product_id');

            if ($productId) {
                $historyProduct = Product::find($productId);
            } elseif ($search) {
                $historyProduct = Product::where('item_name', 'like', "%{$search}%")
                    ->orWhere('brand_name', 'like', "%{$search}%")
                    ->first();
            }

            if ($historyProduct) {
                $history = $this->inventoryReportService->getProductHistory($historyProduct->id);
            }
        }

        return view('admin.inventory-items.index', compact(
            'tab', 'search', 'categories', 'genericNames', 'suppliers', 'taxes',
            'generalItems', 'products', 'batches', 'historyProduct', 'history',
            'nextGenericCode', 'nextProductCode'
        ));
    }
}
