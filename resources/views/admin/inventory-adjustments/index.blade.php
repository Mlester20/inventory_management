@extends('layout.app')

@section('title', 'Inventory Adjustments')

@section('content')
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Inventory Adjustments</h5>
            <a href="{{ route('inventory-adjustments.create') }}" class="btn btn-primary">New Adjustment</a>
        </div>
        <div class="card-body">
            <form method="GET" class="mb-3" style="max-width: 400px;">
                <div class="input-group">
                    <input type="search" name="search" class="form-control" placeholder="Search..." value="{{ $search }}">
                    <button type="submit" class="btn btn-outline-secondary">Search</button>
                </div>
            </form>
        </div>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Adjustment #</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Prepared By</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($adjustments as $adjustment)
                        <tr>
                            <td><a href="{{ route('inventory-adjustments.show', $adjustment) }}">{{ $adjustment->adjustment_no }}</a></td>
                            <td>{{ $adjustment->adjustment_date->format('Y-m-d') }}</td>
                            <td>{{ \App\Models\InventoryAdjustment::TYPES[$adjustment->adjustment_type] ?? $adjustment->adjustment_type }}</td>
                            <td>{{ $adjustment->description }}</td>
                            <td>{{ $adjustment->preparedBy->name ?? '—' }}</td>
                            <td>{{ $adjustment->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No inventory adjustments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $adjustments->links() }}
        </div>
    </div>
@endsection
