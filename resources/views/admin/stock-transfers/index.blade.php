@extends('layout.app')

@section('title', 'Stock Transfers')

@section('content')
    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form action="{{ route('stock-transfers.index') }}" method="GET" class="d-flex gap-2">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by reference"
                value="{{ $search }}"
            >
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            @if($search)
                <a href="{{ route('stock-transfers.index') }}" class="btn btn-outline-danger">Clear</a>
            @endif
        </form>

        <a href="{{ route('stock-transfers.create') }}" class="btn btn-primary">
            <i class="bx bx-plus"></i> New Stock Transfer
        </a>
    </div>

    <div class="card mt-4">
        <h5 class="card-header">Stock Transfers</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Prepared By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockTransfers as $stockTransfer)
                        <tr>
                            <td>{{ $stockTransfer->id }}</td>
                            <td>{{ $stockTransfer->reference }}</td>
                            <td>{{ $stockTransfer->date->format('M d, Y') }}</td>
                            <td>{{ $stockTransfer->fromLocation->name }}</td>
                            <td>{{ $stockTransfer->toLocation->name }}</td>
                            <td>{{ $stockTransfer->preparedBy->name ?? '—' }}</td>
                            <td>
                                <a href="{{ route('stock-transfers.show', $stockTransfer) }}" class="btn btn-sm btn-info">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No Stock Transfers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($stockTransfers->hasPages())
            <div class="card-footer">
                {{ $stockTransfers->links() }}
            </div>
        @endif
    </div>
@endsection
