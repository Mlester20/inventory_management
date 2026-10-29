<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\DeliveryReceiptResource;
use App\Models\DeliveryReceipt;
use Illuminate\Http\Request;

class DeliveryReceiptController extends ApiController
{
    public function index(Request $request)
    {
        $query = DeliveryReceipt::query()->with(['customer', 'salesOrder', 'items.productBatch.product'])
            ->where('is_draft', false)->whereNull('archived_at')->latest('receipt_date')->latest('id');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->query('customer_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('from')) {
            $query->whereDate('receipt_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('receipt_date', '<=', $request->query('to'));
        }

        return DeliveryReceiptResource::collection($this->updatedSince($query, $request)->paginate($this->perPage($request)));
    }

    public function show(DeliveryReceipt $deliveryReceipt)
    {
        abort_if($deliveryReceipt->is_draft || $deliveryReceipt->archived_at, 404);

        return new DeliveryReceiptResource($deliveryReceipt->load(['customer', 'salesOrder', 'items.productBatch.product']));
    }
}
