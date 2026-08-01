@extends('layout.app')

@section('title', 'Stock Transfer ' . $stockTransfer->reference)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <a href="{{ route('stock-transfers.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Stock Transfers
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Reference</label>
                    <p class="fw-bold mb-0">{{ $stockTransfer->reference }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Date</label>
                    <p class="mb-0">{{ $stockTransfer->date->format('M d, Y') }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">From</label>
                    <p class="mb-0"><span class="badge bg-secondary">{{ $stockTransfer->fromLocation->name }}</span></p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">To</label>
                    <p class="mb-0"><span class="badge bg-primary">{{ $stockTransfer->toLocation->name }}</span></p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Prepared By</label>
                    <p class="mb-0">{{ $stockTransfer->preparedBy->name ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">Transferred Items</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr class="table-header-bg">
                        <th>Item</th>
                        <th>Batch No.</th>
                        <th>Expiry</th>
                        <th class="text-end">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stockTransfer->lines as $line)
                        <tr>
                            <td>{{ $line->productBatch->product->item_name }}</td>
                            <td>{{ $line->productBatch->batch_no ?? '—' }}</td>
                            <td>{{ $line->productBatch->expiration_date?->format('M d, Y') ?? '—' }}</td>
                            <td class="text-end">{{ $line->qty }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

<style>
    .table-header-bg {
        background-color: #f7f8fa;
    }
</style>
@endsection
