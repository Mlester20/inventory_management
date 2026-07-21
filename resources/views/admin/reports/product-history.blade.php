@extends('layout.app')

@section('title', 'Product History Report')

@section('content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1">Product History Report</h4>
            <p class="text-muted">Stock movement ledger for a single item</p>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.reports.product-history') }}" class="row g-3">
                <div class="col-md-4">
                    <label for="item_id" class="form-label">Item</label>
                    <select class="form-select" id="item_id" name="item_id" required>
                        <option value="">-- Select Item --</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" {{ (string) $itemId === (string) $item->id ? 'selected' : '' }}>
                                {{ $item->item_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                    <a href="{{ route('admin.reports.product-history') }}" class="btn btn-outline-secondary" title="Clear all filters">
                        <i class="bx bx-refresh"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Movement Ledger</h5>
        </div>
        <div class="card-body">
            @if (!$itemId)
                <div class="alert alert-info" role="alert">
                    <i class="bx bx-info-circle me-2"></i>
                    Select an item above to view its stock movement history.
                </div>
            @else
                @forelse ($movements as $movement)
                    @if ($loop->first)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr class="table-header-bg">
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th class="text-end">Qty Change</th>
                                    <th class="text-end">Balance After</th>
                                    <th>Remarks</th>
                                    <th>Performed By</th>
                                </tr>
                            </thead>
                            <tbody>
                    @endif
                                <tr>
                                    <td>{{ $movement->created_at->format('M d, Y H:i') }}</td>
                                    <td>
                                        @if ($movement->type === 'in')
                                            <span class="badge bg-success">In (Restock)</span>
                                        @else
                                            <span class="badge bg-danger">Out (Deduction)</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                                    <td class="text-end">{{ $movement->running_balance }}</td>
                                    <td>{{ $movement->remarks ?? '-' }}</td>
                                    <td>{{ $movement->user->name ?? 'System' }}</td>
                                </tr>
                    @if ($loop->last)
                            </tbody>
                        </table>
                    </div>
                    @endif
                @empty
                    <div class="alert alert-info" role="alert">
                        <i class="bx bx-info-circle me-2"></i>
                        No stock movements recorded for this item{{ ($startDate && $endDate) ? ' within the selected date range' : '' }}.
                    </div>
                @endforelse
            @endif
        </div>
    </div>

</div>

<style>
    .table-header-bg {
        background-color: #f7f8fa;
    }
</style>

@endsection
