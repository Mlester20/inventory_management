<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\ProductResource;
use App\Models\Location;
use App\Models\LocationStock;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends ApiController
{
    public function index(Request $request)
    {
        $query = Product::query()->with('genericName')->whereNull('archived_at')->orderBy('item_name');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', $search);
            });
        }

        $page = $this->updatedSince($query, $request)->paginate($this->perPage($request));
        $stock = $this->stockFor($page->getCollection()->pluck('id')->all());
        $page->getCollection()->each(fn (Product $product) => $product->setAttribute('api_stock', $stock[$product->id] ?? []));

        return ProductResource::collection($page);
    }

    public function show(Product $product)
    {
        abort_if($product->archived_at, 404);

        $product->load('genericName')->setAttribute('api_stock', $this->stockFor([$product->id])[$product->id] ?? []);

        return new ProductResource($product);
    }

    /** @return array<int,array<string,int>> product id => [location name => qty], one query for the whole page */
    protected function stockFor(array $productIds): array
    {
        $names = Location::pluck('name', 'id');
        $stock = [];

        LocationStock::query()
            ->join('product_batches', 'product_batches.id', '=', 'location_stocks.product_batch_id')
            ->whereIn('product_batches.product_id', $productIds)
            ->selectRaw('product_batches.product_id, location_stocks.location_id, sum(location_stocks.qty) as qty')
            ->groupBy('product_batches.product_id', 'location_stocks.location_id')
            ->get()
            ->each(function ($row) use (&$stock, $names) {
                $stock[$row->product_id][$names[$row->location_id] ?? 'Location ' . $row->location_id] = (int) $row->qty;
            });

        return $stock;
    }
}
