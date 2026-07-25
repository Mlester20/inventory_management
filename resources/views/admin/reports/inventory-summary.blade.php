@extends('layout.app')

@section('title', 'Inventory Summary Report')

@section('content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1">Inventory Summary Report</h4>
            <p class="text-muted">Current stock on hand and its total value</p>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.reports.inventory-summary') }}" class="row g-3">
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
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="low_stock_only" name="low_stock_only" value="1" {{ $lowStockOnly ? 'checked' : '' }}>
                        <label class="form-check-label" for="low_stock_only">Low stock only</label>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply Filter</button>
                    <a href="{{ route('admin.reports.inventory-summary') }}" class="btn btn-outline-secondary" title="Clear all filters">
                        <i class="bx bx-refresh"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Grand Total Card -->
    <div class="row mb-4">
        <div class="col-12 col-md-4">
            <div class="card bg-light-primary border-primary">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2 small">Total Inventory Value</span>
                            <h4 class="mb-0">₱{{ number_format($grandTotal, 2) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-primary">
                                <i class="bx bx-package fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="content-left">
                        <span class="text-muted d-block mb-2 small">Items Listed</span>
                        <h4 class="mb-0">{{ $items->total() }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="content-left">
                        <span class="text-muted d-block mb-2 small">Low Stock Items</span>
                        <h4 class="mb-0">{{ $lowStockCount }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Current Stock</h5>
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
                                <th class="text-end">Qty on Hand</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                @endif
                            <tr>
                                <td><span class="fw-medium">{{ $item->item_name }}</span></td>
                                <td>{{ $item->category->category_name ?? 'N/A' }}</td>
                                <td>{{ $item->supplier->supplier_name ?? 'N/A' }}</td>
                                <td class="text-end">{{ $item->on_hand_qty }}</td>
                                <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                                <td class="text-end">{{ number_format($item->total_value, 2) }}</td>
                                <td>
                                    @if ($item->is_low_stock)
                                        <span class="badge bg-warning text-dark">Low Stock</span>
                                    @else
                                        <span class="badge bg-success">In Stock</span>
                                    @endif
                                </td>
                            </tr>
                @if ($loop->last)
                        </tbody>
                        <tfoot>
                            <tr class="table-info fw-bold">
                                <td colspan="5">GRAND TOTAL</td>
                                <td class="text-end">{{ number_format($grandTotal, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
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
                    No items match the selected filters.
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
    .table-info {
        background-color: #e7f3ff;
    }
</style>

@endsection
