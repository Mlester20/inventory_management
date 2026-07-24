@extends('layout.app')

@section('title', 'Inventory Adjustment ' . $inventoryAdjustment->adjustment_no)

@section('content')
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Inventory Adjustment {{ $inventoryAdjustment->adjustment_no }}</h5>
            <a href="{{ route('inventory-adjustments.index') }}" class="btn btn-outline-secondary btn-sm">Back to list</a>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="fw-bold">Date</label>
                    <p>{{ $inventoryAdjustment->adjustment_date->format('Y-m-d') }}</p>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold">Type</label>
                    <p>{{ \App\Models\InventoryAdjustment::TYPES[$inventoryAdjustment->adjustment_type] ?? $inventoryAdjustment->adjustment_type }}</p>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold">Prepared By</label>
                    <p>{{ $inventoryAdjustment->preparedBy->name ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold">Description</label>
                    <p>{{ $inventoryAdjustment->description ?? '—' }}</p>
                </div>
            </div>

            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th>Lot/Batch No.</th>
                        <th>Expiry Date</th>
                        <th>Qty</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($inventoryAdjustment->lines as $line)
                        <tr>
                            <td>{{ $line->product->item_name }}</td>
                            <td>{{ $line->batch_no ?? '—' }}</td>
                            <td>{{ $line->expiration_date?->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $line->qty }}</td>
                            <td>{{ $line->remarks ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($inventoryAdjustment->note)
                <div class="mt-3">
                    <label class="fw-bold">Note</label>
                    <p>{{ $inventoryAdjustment->note }}</p>
                </div>
            @endif
        </div>
    </div>
@endsection
