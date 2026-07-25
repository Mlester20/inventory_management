@extends('layout.app')

@section('title', 'Sales Quotes')

@section('content')
    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form action="{{ route('sales-quotes.index') }}" method="GET" class="d-flex gap-2">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by customer or quote no."
                value="{{ $search }}"
            >
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            @if($search)
                <a href="{{ route('sales-quotes.index') }}" class="btn btn-outline-danger">Clear</a>
            @endif
        </form>

        <a href="{{ route('sales-quotes.create') }}" class="btn btn-primary">
            <i class="bx bx-plus"></i> New Sales Quote
        </a>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Sales Quotes</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Quote No.</th>
                        <th>Customer</th>
                        <th>Quote Date</th>
                        <th>Valid Until</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesQuotes as $salesQuote)
                        <tr>
                            <td>{{ $salesQuote->id }}</td>
                            <td>{{ $salesQuote->quote_no }}</td>
                            <td>{{ $salesQuote->customer->customer_name }}</td>
                            <td>{{ $salesQuote->quote_date->format('M d, Y') }}</td>
                            <td>{{ $salesQuote->valid_until?->format('M d, Y') ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ ['open' => 'warning', 'converted' => 'success', 'cancelled' => 'danger'][$salesQuote->status] ?? 'secondary' }}">
                                    {{ ucfirst($salesQuote->status) }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('sales-quotes.show', $salesQuote) }}" class="btn btn-sm btn-info">
                                    View
                                </a>
                                @if(Auth::user()->role === 'admin')
                                    <form
                                        action="{{ route('sales-quotes.destroy', $salesQuote) }}"
                                        method="POST"
                                        class="d-inline"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('Are you sure you want to delete this Sales Quote?')"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No Sales Quotes found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($salesQuotes->hasPages())
            <div class="card-footer">
                {{ $salesQuotes->links() }}
            </div>
        @endif
    </div>
@endsection
