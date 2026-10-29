<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_no' => $this->sales_no,
            'customer_po_no' => $this->po_no,
            'date' => $this->created_at?->toDateString(),
            'customer' => ['id' => $this->customer_id, 'name' => $this->customer?->customer_name ?? $this->customer_name],
            'cancelled' => $this->cancelled_at !== null,
            'is_personal_use' => $this->is_personal_use,
            'vat_sales' => $this->vat_sales,
            'vat_exempt_sales' => $this->vatex_sales,
            'zero_rated_sales' => $this->zero_sales,
            'vat_amount' => $this->vat_amount,
            'total_sales' => $this->total_sales,
            'less_vat' => $this->less_vat,
            'less_senior_pwd' => $this->less_sc,
            'less_withholding_tax' => $this->less_wt,
            'add_vat' => $this->add_vat,
            'amount_net' => $this->amount_net,
            'amount_due' => $this->amount_due,
            'amount_paid' => $this->amount_paid,
            'lines' => $this->sales->map(fn ($line) => [
                'description' => $line->desc,
                'qty' => $line->qty,
                'unit' => $line->unit,
                'batch_no' => $line->batch_no,
                'expiration_date' => $line->exp?->toDateString(),
                'price' => $line->price,
                'vat' => $line->vat,
                'discount' => $line->dis,
                'amount' => $line->amount,
            ])->values(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
