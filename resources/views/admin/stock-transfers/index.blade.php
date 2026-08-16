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
                        <th>Status</th>
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
                            <td>
                                <span class="badge bg-{{ $stockTransfer->isDraft() ? 'secondary' : 'success' }}">
                                    {{ \App\Models\StockTransfer::STATUSES[$stockTransfer->status] ?? $stockTransfer->status }}
                                </span>
                            </td>
                            <td>{{ $stockTransfer->date?->format('M d, Y') ?? '—' }}</td>
                            <td>{{ $stockTransfer->fromLocation?->name ?? '—' }}</td>
                            <td>{{ $stockTransfer->toLocation?->name ?? '—' }}</td>
                            <td>{{ $stockTransfer->preparedBy->name ?? '—' }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('stock-transfers.show', $stockTransfer) }}" class="btn btn-sm btn-info">
                                        View
                                    </a>
                                    @if($stockTransfer->isDraft())
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a href="{{ route('stock-transfers.edit', $stockTransfer) }}" class="dropdown-item">
                                                    <i class="bx bx-edit-alt me-1"></i> Continue Editing
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('stock-transfers.destroy', $stockTransfer) }}" method="POST" class="m-0" onsubmit="return confirm('Delete draft {{ $stockTransfer->reference }}? This cannot be undone.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bx bx-trash me-1"></i> Delete Draft
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">No Stock Transfers found.</td>
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
