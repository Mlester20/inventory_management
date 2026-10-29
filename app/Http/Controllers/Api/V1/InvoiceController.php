<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends ApiController
{
    public function index(Request $request)
    {
        $query = Invoice::query()->with(['customer', 'sales'])
            ->where('is_draft', false)->whereNull('archived_at')->latest('id');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->query('customer_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        return InvoiceResource::collection($this->updatedSince($query, $request)->paginate($this->perPage($request)));
    }

    public function show(Invoice $invoice)
    {
        abort_if($invoice->is_draft || $invoice->archived_at, 404);

        return new InvoiceResource($invoice->load(['customer', 'sales']));
    }
}
