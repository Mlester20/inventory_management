@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Invoices')

@section('content')
    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form action="{{ route('invoices.index') }}" method="GET" class="d-flex gap-2">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by customer or sales no."
                value="{{ $search }}"
            >
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            @if($search)
                <a href="{{ route('invoices.index') }}" class="btn btn-outline-danger">Clear</a>
            @endif
        </form>

        <div class="d-flex gap-2">
            <a href="{{ route('invoices.index', array_merge(request()->query(), ['show_archived' => $showArchived ? 0 : 1, 'show_trashed' => 0])) }}" class="btn btn-outline-secondary">
                @if($showArchived)
                    <i class="bx bx-undo"></i> Hide archived
                @else
                    <i class="bx bx-archive"></i> Show archived
                @endif
            </a>
            <a href="{{ route('invoices.index', array_merge(request()->query(), ['show_trashed' => $showTrashed ? 0 : 1, 'show_archived' => 0])) }}" class="btn btn-outline-secondary">
                @if($showTrashed)
                    <i class="bx bx-undo"></i> Hide trashed
                @else
                    <i class="bx bx-trash"></i> Show trashed
                @endif
            </a>
            <a href="{{ route('invoices.create') }}" class="btn btn-primary">
                <i class="bx bx-plus"></i> New Invoice
            </a>
        </div>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Sales Invoices</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Sales No.</th>
                        <th>Customer</th>
                        <th>PO No.</th>
                        <th>Total Sales</th>
                        <th>Amount Due</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->id }}</td>
                            <td>{{ $invoice->sales_no }}</td>
                            <td>{{ $invoice->customer_name }}</td>
                            <td>{{ $invoice->po_no ?? '—' }}</td>
                            <td>₱{{ number_format($invoice->total_sales, 2) }}</td>
                            <td>₱{{ number_format($invoice->amount_due, 2) }}</td>
                            <td>
                                {{ $invoice->created_at->format('M d, Y') }}
                                @if($invoice->isCancelled())
                                    <span class="badge bg-danger">CANCELLED</span>
                                @endif
                                @if($invoice->isArchived())
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
                                                <form action="{{ route('invoices.restore', $invoice->id) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="dropdown-item text-success" onclick="return confirmSubmit(this.form, 'Restore this invoice?')">
                                                        <i class="bx bx-undo me-1"></i> Restore
                                                    </button>
                                                </form>
                                            @endif
                                        @else
                                        <a href="{{ route('invoices.show', $invoice) }}" class="dropdown-item">
                                            <i class="bx bx-show me-1"></i> View / Print
                                        </a>

                                        @if($invoice->isArchived())
                                            <form action="{{ route('invoices.unarchive', $invoice) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bx bx-undo me-1"></i> Unarchive
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('invoices.archive', $invoice) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bx bx-archive me-1"></i> Archive
                                                </button>
                                            </form>
                                        @endif

                                        @if(Auth::user()->role === 'admin')
                                            <div class="dropdown-divider"></div>

                                            @if(! $invoice->isCancelled())
                                                <form action="{{ route('invoices.cancel', $invoice) }}" method="POST" onsubmit="return confirmSubmit(this, 'Cancel/void this invoice? The record and its recorded sales stay, only the status changes.');">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bx bx-block me-1"></i> Cancel/Void
                                                    </button>
                                                </form>
                                            @endif

                                            <form
                                                action="{{ route('invoices.destroy', $invoice) }}"
                                                method="POST"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="dropdown-item text-danger"
                                                    onclick="return confirmSubmit(this.form, 'Are you sure you want to delete this invoice?')"
                                                >
                                                    <i class="bx bx-trash me-1"></i> Delete
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
                            <td colspan="8" class="text-center text-muted">No invoices found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())
            <div class="card-footer">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
@endsection
