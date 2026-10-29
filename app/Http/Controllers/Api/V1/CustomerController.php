<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends ApiController
{
    public function index(Request $request)
    {
        $query = Customer::query()->orderBy('customer_name');

        if ($search = trim((string) $request->query('search'))) {
            $query->where('customer_name', 'like', "%{$search}%");
        }

        return CustomerResource::collection($this->updatedSince($query, $request)->paginate($this->perPage($request)));
    }

    public function show(Customer $customer)
    {
        return new CustomerResource($customer);
    }
}
