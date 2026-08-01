@extends('layout.app')

@section('title', 'Stock Disposals')

@section('content')
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Stock Disposals</h5>
            <a href="{{ route('stock-disposals.create') }}" class="btn btn-primary">New Stock Disposal</a>
        </div>
        <div class="card-body">
            <form method="GET" class="mb-3" style="max-width: 400px;">
                <div class="input-group">
                    <input type="search" name="search" class="form-control" placeholder="Search by reference..." value="{{ $search }}">
                    <button type="submit" class="btn btn-outline-secondary">Search</button>
                </div>
            </form>
        </div>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Reason</th>
                        <th class="text-end">Items</th>
                        <th>Prepared By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockDisposals as $stockDisposal)
                        <tr>
                            <td><a href="{{ route('stock-disposals.show', $stockDisposal) }}">{{ $stockDisposal->reference }}</a></td>
                            <td>{{ $stockDisposal->date->format('M d, Y') }}</td>
                            <td>{{ \App\Models\StockDisposal::REASONS[$stockDisposal->reason] ?? $stockDisposal->reason }}</td>
                            <td class="text-end">{{ $stockDisposal->lines_count }}</td>
                            <td>{{ $stockDisposal->preparedBy->name ?? '—' }}</td>
                            <td>
                                <a href="{{ route('stock-disposals.show', $stockDisposal) }}" class="btn btn-sm btn-info">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No Stock Disposals yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($stockDisposals->hasPages())
            <div class="card-footer">
                {{ $stockDisposals->links() }}
            </div>
        @endif
    </div>
@endsection
