@extends('layout.app')

@section('title', 'Purchase Summary Report')

@section('content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1">Purchase Summary Report</h4>
            <p class="text-muted">Aggregate POS checkout transactions (see note below on the "purchases" table)</p>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.reports.purchase-summary') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}">
                </div>
                <div class="col-md-3">
                    <label for="category_id" class="form-label">Category</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ (string) $categoryId === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->category_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply Filter</button>
                    <a href="{{ route('admin.reports.purchase-summary') }}" class="btn btn-outline-secondary" title="Clear all filters">
                        <i class="bx bx-refresh"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="alert alert-secondary">
        <i class="bx bx-info-circle me-2"></i>
        This app's <code>purchases</code> table records POS checkout/sale transactions, not money paid to suppliers —
        there is no supplier-side procurement (Purchase Order / Goods Receipt) tracking in this system yet.
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-12 col-md-4">
            <div class="card bg-light-primary border-primary">
                <div class="card-body">
                    <span class="text-muted d-block mb-2 small">Total Amount</span>
                    <h4 class="mb-0">₱{{ number_format($total_amount, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card">
                <div class="card-body">
                    <span class="text-muted d-block mb-2 small">Total Transactions</span>
                    <h4 class="mb-0">{{ number_format($total_transactions) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card">
                <div class="card-body">
                    <span class="text-muted d-block mb-2 small">Total Qty</span>
                    <h4 class="mb-0">{{ number_format($total_qty) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Breakdown by Item -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Breakdown by Item</h5>
        </div>
        <div class="card-body">
            @forelse ($by_item as $row)
                @if ($loop->first)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr class="table-header-bg">
                                <th>Item Name</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                @endif
                            <tr>
                                <td>{{ $row->item_name }}</td>
                                <td class="text-end">{{ intval($row->qty) }}</td>
                                <td class="text-end">{{ number_format($row->amount, 2) }}</td>
                            </tr>
                @if ($loop->last)
                        </tbody>
                    </table>
                </div>
                @endif
            @empty
                <div class="alert alert-info" role="alert">
                    <i class="bx bx-info-circle me-2"></i>
                    No purchase data for this period.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Breakdown by Category -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Breakdown by Category</h5>
        </div>
        <div class="card-body">
            @forelse ($by_category as $row)
                @if ($loop->first)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr class="table-header-bg">
                                <th>Category</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                @endif
                            <tr>
                                <td>{{ $row->category_name }}</td>
                                <td class="text-end">{{ intval($row->qty) }}</td>
                                <td class="text-end">{{ number_format($row->amount, 2) }}</td>
                            </tr>
                @if ($loop->last)
                        </tbody>
                    </table>
                </div>
                @endif
            @empty
                <div class="alert alert-info" role="alert">
                    <i class="bx bx-info-circle me-2"></i>
                    No purchase data for this period.
                </div>
            @endforelse
        </div>
    </div>

</div>

<style>
    .table-header-bg {
        background-color: #f7f8fa;
    }
    .bg-light-primary {
        background-color: rgba(105, 108, 255, 0.1);
    }
    .border-primary {
        border-left: 4px solid #696cff !important;
    }
</style>

@endsection
