<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customer_name,
            'customer_type' => $this->customer_type,
            'contact_person' => $this->contact_person,
            'contact_number' => $this->contact_number,
            'email' => $this->email,
            'delivery_address' => $this->delivery_address,
            'price_level' => $this->price_level,
            'vat_type' => $this->vat_type,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
