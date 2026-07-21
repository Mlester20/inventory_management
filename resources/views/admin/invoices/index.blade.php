@extends('layout.app')

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

        <a href="{{ route('invoices.create') }}" class="btn btn-primary">
            <i class="bx bx-plus"></i> New Invoice
        </a>
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
                            <td>{{ $invoice->created_at->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-info">
                                    View / Print
                                </a>
                                @if(Auth::user()->role === 'admin')
                                    <form
                                        action="{{ route('invoices.destroy', $invoice) }}"
                                        method="POST"
                                        class="d-inline"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('Are you sure you want to delete this invoice?')"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                @endif
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
