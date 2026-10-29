<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A product's cost price is left out on purpose: the API is for what is sold and what is on hand. */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stock = $this->api_stock ?? [];

        return [
            'id' => $this->id,
            'code' => $this->code,
            'item_name' => $this->item_name,
            'brand_name' => $this->brand_name,
            'generic_name' => $this->genericName?->generic_name,
            'description' => $this->display_description,
            'barcode' => $this->barcode,
            'unit' => $this->genericName?->unit,
            'prices' => [
                'retail' => $this->unit_price,
                'wholesale' => $this->wholesale_price,
                'level_1' => $this->price_1,
                'level_2' => $this->price_2,
                'level_3' => $this->price_3,
            ],
            'low_stock_threshold' => $this->low_stock_threshold,
            'fda_reg_no' => $this->fda_reg_no,
            'stock' => ['by_location' => $stock, 'total' => array_sum($stock)],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
