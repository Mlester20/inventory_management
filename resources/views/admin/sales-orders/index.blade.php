@extends('layout.app')

@section('title', 'Sales Orders')

@section('content')
    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form action="{{ route('sales-orders.index') }}" method="GET" class="d-flex gap-2">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by customer, S.O. no., or P.O. no."
                value="{{ $search }}"
            >
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            @if($search)
                <a href="{{ route('sales-orders.index') }}" class="btn btn-outline-danger">Clear</a>
            @endif
        </form>

        <a href="{{ route('sales-orders.create') }}" class="btn btn-primary">
            <i class="bx bx-plus"></i> New Sales Order
        </a>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Sales Orders</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>S.O. No.</th>
                        <th>Customer</th>
                        <th>P.O. No.</th>
                        <th>Order Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesOrders as $salesOrder)
                        <tr>
                            <td>{{ $salesOrder->id }}</td>
                            <td>{{ $salesOrder->so_no }}</td>
                            <td>{{ $salesOrder->customer->customer_name }}</td>
                            <td>{{ $salesOrder->po_no ?? '—' }}</td>
                            <td>{{ $salesOrder->order_date->format('M d, Y') }}</td>
                            <td>
                                <span class="badge bg-{{ ['open' => 'warning', 'partially_delivered' => 'info', 'completed' => 'success', 'cancelled' => 'danger'][$salesOrder->status] ?? 'secondary' }}">
                                    {{ ucfirst(str_replace('_', ' ', $salesOrder->status)) }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('sales-orders.show', $salesOrder) }}" class="btn btn-sm btn-info">
                                    View
                                </a>
                                @if(Auth::user()->role === 'admin')
                                    <form
                                        action="{{ route('sales-orders.destroy', $salesOrder) }}"
                                        method="POST"
                                        class="d-inline"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('Are you sure you want to delete this Sales Order?')"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No Sales Orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($salesOrders->hasPages())
            <div class="card-footer">
                {{ $salesOrders->links() }}
            </div>
        @endif
    </div>
@endsection
