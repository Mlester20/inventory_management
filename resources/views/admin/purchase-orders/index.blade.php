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
                            <td>{{ $purchaseOrder->supplier->supplier_name ?? '—' }}</td>
                            <td>{{ $purchaseOrder->order_date->format('M d, Y') }}</td>
                            <td>
                                @if($purchaseOrder->isDraft())
                                    <span class="badge bg-secondary">DRAFT</span>
                                @else
                                    <span class="badge bg-{{ ['open' => 'warning', 'partially_received' => 'info', 'completed' => 'success', 'cancelled' => 'danger'][$purchaseOrder->status] ?? 'secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $purchaseOrder->status)) }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="dropdown-item">
                                            <i class="bx bx-show me-1"></i> View
                                        </a>
                                        @if($purchaseOrder->isDraft())
                                            <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="dropdown-item">
                                                <i class="bx bx-edit-alt me-1"></i> Continue Editing
                                            </a>
                                        @endif
                                        <div class="dropdown-divider"></div>
                                        <form
                                            action="{{ route('purchase-orders.destroy', $purchaseOrder) }}"
                                            method="POST"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="dropdown-item text-danger"
                                                onclick="return confirm('Are you sure you want to delete this {{ $purchaseOrder->isDraft() ? 'draft' : 'Purchase Order' }}?')"
                                            >
                                                <i class="bx bx-trash me-1"></i> {{ $purchaseOrder->isDraft() ? 'Delete Draft' : 'Delete' }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
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
