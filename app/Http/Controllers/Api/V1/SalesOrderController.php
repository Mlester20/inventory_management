<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\SalesOrderResource;
use App\Models\SalesOrder;
use Illuminate\Http\Request;

class SalesOrderController extends ApiController
{
    public function index(Request $request)
    {
        $query = SalesOrder::query()->with(['customer', 'items.genericName'])
            ->where('is_draft', false)->whereNull('archived_at')->latest('order_date')->latest('id');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->query('customer_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('from')) {
            $query->whereDate('order_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('order_date', '<=', $request->query('to'));
        }

        return SalesOrderResource::collection($this->updatedSince($query, $request)->paginate($this->perPage($request)));
    }

    public function show(SalesOrder $salesOrder)
    {
        abort_if($salesOrder->is_draft || $salesOrder->archived_at, 404);

        return new SalesOrderResource($salesOrder->load(['customer', 'items.genericName']));
    }
}
