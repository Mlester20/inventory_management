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

        <div class="d-flex gap-2">
            <a href="{{ route('sales-orders.index', array_merge(request()->query(), ['show_archived' => $showArchived ? 0 : 1, 'show_trashed' => 0])) }}" class="btn btn-outline-secondary">
                @if($showArchived)
                    <i class="bx bx-undo"></i> Hide archived
                @else
                    <i class="bx bx-archive"></i> Show archived
                @endif
            </a>
            <a href="{{ route('sales-orders.index', array_merge(request()->query(), ['show_trashed' => $showTrashed ? 0 : 1, 'show_archived' => 0])) }}" class="btn btn-outline-secondary">
                @if($showTrashed)
                    <i class="bx bx-undo"></i> Hide trashed
                @else
                    <i class="bx bx-trash"></i> Show trashed
                @endif
            </a>
            <a href="{{ route('sales-orders.create') }}" class="btn btn-primary">
                <i class="bx bx-plus"></i> New Sales Order
            </a>
        </div>
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
                                @if($salesOrder->isArchived())
                                    <span class="badge bg-dark">ARCHIVED</span>
                                @endif
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if($showTrashed)
                                            @if(Auth::user()->role === 'admin')
                                                <form action="{{ route('sales-orders.restore', $salesOrder->id) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="dropdown-item text-success" onclick="return confirmSubmit(this.form, 'Restore this Sales Order?')">
                                                        <i class="bx bx-undo me-1"></i> Restore
                                                    </button>
                                                </form>
                                            @endif
                                        @else
                                        <a href="{{ route('sales-orders.show', $salesOrder) }}" class="dropdown-item">
                                            <i class="bx bx-show me-1"></i> View
                                        </a>
                                        @if($salesOrder->isDraft())
                                            <a href="{{ route('sales-orders.edit', $salesOrder) }}" class="dropdown-item">
                                                <i class="bx bx-edit-alt me-1"></i> Continue Editing
                                            </a>
                                        @endif

                                        @if($salesOrder->isArchived())
                                            <form action="{{ route('sales-orders.unarchive', $salesOrder) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bx bx-undo me-1"></i> Unarchive
                                                </button>
                                            </form>
                                        @elseif(! $salesOrder->isDraft())
                                            <form action="{{ route('sales-orders.archive', $salesOrder) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bx bx-archive me-1"></i> Archive
                                                </button>
                                            </form>
                                        @endif

                                        @if(Auth::user()->role === 'admin')
                                            <div class="dropdown-divider"></div>

                                            @if(! $salesOrder->isDraft() && ! $salesOrder->isCancelled())
                                                <form action="{{ route('sales-orders.cancel', $salesOrder) }}" method="POST">
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="dropdown-item text-danger"
                                                        onclick="return confirmSubmit(this.form, 'Cancel/void this Sales Order? The record and its delivery history stay, only the status changes.')"
                                                    >
                                                        <i class="bx bx-block me-1"></i> Cancel/Void
                                                    </button>
                                                </form>
                                            @endif

                                            <form
                                                action="{{ route('sales-orders.destroy', $salesOrder) }}"
                                                method="POST"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="dropdown-item text-danger"
                                                    onclick="return confirmSubmit(this.form, 'Are you sure you want to delete this {{ $salesOrder->isDraft() ? 'draft' : 'Sales Order' }}?')"
                                                >
                                                    <i class="bx bx-trash me-1"></i> {{ $salesOrder->isDraft() ? 'Delete Draft' : 'Delete' }}
                                                </button>
                                            </form>
                                        @endif
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
