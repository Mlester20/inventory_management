<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dr_no' => $this->dr_no,
            'status' => $this->status,
            'transaction_type' => $this->transaction_type,
            'receipt_date' => $this->receipt_date?->toDateString(),
            'description' => $this->description,
            'customer' => ['id' => $this->customer_id, 'name' => $this->customer?->customer_name],
            'sales_order' => $this->sales_order_id ? ['id' => $this->sales_order_id, 'so_no' => $this->salesOrder?->so_no] : null,
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->productBatch?->product_id,
                'description' => $item->productBatch?->product?->display_description,
                'batch_no' => $item->batch_no,
                'expiration_date' => $item->expiration_date?->toDateString(),
                'qty' => $item->qty,
                'invoiced_qty' => $item->invoiced_qty,
                'remarks' => $item->remarks,
            ])->values(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
