@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

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
                        <th>Notes</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesOrders as $salesOrder)
                        <tr>
                            <td>{{ $salesOrder->id }}</td>
                            <td>{{ $salesOrder->so_no }}</td>
                            <td>{{ $salesOrder->customer->customer_name ?? '—' }}</td>
                            <td>{{ $salesOrder->po_no ?? '—' }}</td>
                            <td>{{ $salesOrder->order_date->format('M d, Y') }}</td>
                            <td style="max-width: 200px;">
                                @if($salesOrder->notes)
                                    <span class="d-inline-block text-truncate" style="max-width: 200px;" title="{{ $salesOrder->notes }}">
                                        {{ $salesOrder->notes }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($salesOrder->isDraft())
                                    <span class="badge bg-secondary">DRAFT</span>
                                @else
                                    <span class="badge bg-{{ ['open' => 'warning', 'partially_delivered' => 'info', 'completed' => 'success', 'cancelled' => 'danger'][$salesOrder->status] ?? 'secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $salesOrder->status)) }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a href="{{ route('sales-orders.show', $salesOrder) }}" class="dropdown-item">
                                            <i class="bx bx-show me-1"></i> View
                                        </a>
                                        @if($salesOrder->isDraft())
                                            <a href="{{ route('sales-orders.edit', $salesOrder) }}" class="dropdown-item">
                                                <i class="bx bx-edit-alt me-1"></i> Continue Editing
                                            </a>
                                        @endif
                                        @if(in_array(Auth::user()->role, ['admin', 'admin_staff'], true))
                                            <div class="dropdown-divider"></div>
                                            <form
                                                action="{{ route('sales-orders.destroy', $salesOrder) }}"
                                                method="POST"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="dropdown-item text-danger"
                                                    onclick="return confirm('Are you sure you want to delete this {{ $salesOrder->isDraft() ? 'draft' : 'Sales Order' }}?')"
                                                >
                                                    <i class="bx bx-trash me-1"></i> {{ $salesOrder->isDraft() ? 'Delete Draft' : 'Delete' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">No Sales Orders found.</td>
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
