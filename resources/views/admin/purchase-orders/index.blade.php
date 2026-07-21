@extends('layout.app')

@section('title', 'Purchase Orders')

@section('content')
    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form action="{{ route('purchase-orders.index') }}" method="GET" class="d-flex gap-2">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by supplier or P.O. no."
                value="{{ $search }}"
            >
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            @if($search)
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-danger">Clear</a>
            @endif
        </form>

        <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">
            <i class="bx bx-plus"></i> New Purchase Order
        </a>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Purchase Orders</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>P.O. No.</th>
                        <th>Supplier</th>
                        <th>Order Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrders as $purchaseOrder)
                        <tr>
                            <td>{{ $purchaseOrder->id }}</td>
                            <td>{{ $purchaseOrder->po_no }}</td>
                            <td>{{ $purchaseOrder->supplier->supplier_name }}</td>
                            <td>{{ $purchaseOrder->order_date->format('M d, Y') }}</td>
                            <td>
                                <span class="badge bg-{{ ['open' => 'warning', 'partially_received' => 'info', 'completed' => 'success', 'cancelled' => 'danger'][$purchaseOrder->status] ?? 'secondary' }}">
                                    {{ ucfirst(str_replace('_', ' ', $purchaseOrder->status)) }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-sm btn-info">
                                    View
                                </a>
                                <form
                                    action="{{ route('purchase-orders.destroy', $purchaseOrder) }}"
                                    method="POST"
                                    class="d-inline"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this Purchase Order?')"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">No Purchase Orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($purchaseOrders->hasPages())
            <div class="card-footer">
                {{ $purchaseOrders->links() }}
            </div>
        @endif
    </div>
@endsection
