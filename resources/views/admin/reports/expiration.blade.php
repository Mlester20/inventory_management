@extends('layout.app')

@section('title', 'Product Expiration Report')

@section('content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1">Product Expiration Report</h4>
            <p class="text-muted">Track in-stock items nearing or past their expiration date</p>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.reports.expiration') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="days" class="form-label">Expiration Window</label>
                    <select class="form-select" id="days" name="days">
                        @foreach ([7 => 'Next 7 days', 30 => 'Next 30 days', 60 => 'Next 60 days', 90 => 'Next 90 days'] as $value => $label)
                            <option value="{{ $value }}" {{ $days == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
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
                <div class="col-md-3">
                    <label for="supplier_id" class="form-label">Supplier</label>
                    <select class="form-select" id="supplier_id" name="supplier_id">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ (string) $supplierId === (string) $supplier->id ? 'selected' : '' }}>
                                {{ $supplier->supplier_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply Filter</button>
                    <a href="{{ route('admin.reports.expiration') }}" class="btn btn-outline-secondary" title="Clear all filters">
                        <i class="bx bx-refresh"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-12 col-md-6 col-lg-3 mb-4">
            <div class="card bg-light-danger border-danger">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2 small">Expired</span>
                            <h4 class="mb-0">{{ $summary['expired'] }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-danger">
                                <i class="bx bx-x-circle fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3 mb-4">
            <div class="card bg-light-danger border-danger">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2 small">Critical (&le; 7 days)</span>
                            <h4 class="mb-0">{{ $summary['critical'] }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-danger">
                                <i class="bx bx-error fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3 mb-4">
            <div class="card bg-light-warning border-warning">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2 small">Warning (&le; 30 days)</span>
                            <h4 class="mb-0">{{ $summary['warning'] }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-warning">
                                <i class="bx bx-time fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3 mb-4">
            <div class="card bg-light-success border-success">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2 small">OK</span>
                            <h4 class="mb-0">{{ $summary['ok'] }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-success">
                                <i class="bx bx-check-circle fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Expiring Items Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Items Within Selected Window</h5>
        </div>
        <div class="card-body">
            @forelse ($items as $item)
                @if ($loop->first)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr class="table-header-bg">
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Supplier</th>
                                <th>Batch No.</th>
                                <th class="text-end">Qty on Hand</th>
                                <th>Expiration Date</th>
                                <th>Days Remaining</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                @endif
                            @php
                                $statusLabels = ['expired' => 'Expired', 'critical' => 'Critical', 'warning' => 'Warning', 'ok' => 'OK'];
                                $statusColors = ['expired' => 'danger', 'critical' => 'danger', 'warning' => 'warning', 'ok' => 'success'];
                            @endphp
                            <tr>
                                <td><span class="fw-medium">{{ $item->product->item_name }}</span></td>
                                <td>{{ $item->product->category->category_name ?? 'N/A' }}</td>
                                <td>{{ $item->product->supplier->supplier_name ?? 'N/A' }}</td>
                                <td>{{ $item->batch_no ?? '-' }}</td>
                                <td class="text-end">{{ $item->qty }}</td>
                                <td>{{ $item->expiration_date->format('M d, Y') }}</td>
                                <td>
                                    @if ($item->days_remaining < 0)
                                        {{ abs($item->days_remaining) }} day(s) overdue
                                    @else
                                        {{ $item->days_remaining }} day(s)
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$item->status] }} {{ $item->status === 'warning' ? 'text-dark' : '' }}">
                                        {{ $statusLabels[$item->status] }}
                                    </span>
                                </td>
                            </tr>
                @if ($loop->last)
                        </tbody>
                    </table>
                </div>
                @if($items->hasPages())
                    <div class="mt-3">
                        {{ $items->links() }}
                    </div>
                @endif
                @endif
            @empty
                <div class="alert alert-info" role="alert">
                    <i class="bx bx-info-circle me-2"></i>
                    No in-stock items are expiring within the selected window.
                </div>
            @endforelse
        </div>
    </div>

</div>

<style>
    .table-header-bg {
        background-color: #f7f8fa;
    }
    .bg-light-danger {
        background-color: rgba(220, 53, 69, 0.1);
    }
    .bg-light-warning {
        background-color: rgba(255, 193, 7, 0.1);
    }
    .bg-light-success {
        background-color: rgba(40, 167, 69, 0.1);
    }
    .border-danger {
        border-left: 4px solid #dc3545 !important;
    }
    .border-warning {
        border-left: 4px solid #ffc107 !important;
    }
    .border-success {
        border-left: 4px solid #28a745 !important;
    }
</style>

@endsection
