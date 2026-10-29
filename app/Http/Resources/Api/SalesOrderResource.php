<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'so_no' => $this->so_no,
            'customer_po_no' => $this->po_no,
            'customer_po_date' => $this->customer_po_date?->toDateString(),
            'order_date' => $this->order_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'customer' => ['id' => $this->customer_id, 'name' => $this->customer?->customer_name],
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->genericName?->generic_name,
                'product_id' => $item->product_id,
                'qty' => $item->qty,
                'price' => $item->price,
                'advance_qty' => $item->advance_order_qty,
                'delivered_qty' => $item->delivered_qty,
                'remaining_qty' => $item->remaining_qty,
            ])->values(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
