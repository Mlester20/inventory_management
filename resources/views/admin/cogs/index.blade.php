@extends('layout.app')

@section('title', 'Cost of Goods Sold (COGS)')

@section('content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1">Cost of Goods Sold (COGS)</h4>
            <p class="text-muted">Track inventory costs and profit margins</p>
        </div>
    </div>

    <!-- Date Range Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.cogs.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="startDate" class="form-label">Start Date</label>
                    <input type="date" 
                           class="form-control" 
                           id="startDate" 
                           name="start_date" 
                           value="{{ $startDate }}">
                </div>
                <div class="col-md-3">
                    <label for="endDate" class="form-label">End Date</label>
                    <input type="date" 
                           class="form-control" 
                           id="endDate" 
                           name="end_date" 
                           value="{{ $endDate }}">
                </div>
                <div class="col-md-3">
                    <label for="year" class="form-label">Year (for trend)</label>
                    <select class="form-select" id="year" name="year">
                        @for($y = now()->year; $y >= now()->year - 5; $y--)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply Filter</button>
                    <a href="{{ route('admin.cogs.index') }}" class="btn btn-outline-secondary" title="Clear all filters">
                        <i class="bx bx-refresh"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Metric Cards -->
    <div class="row mb-4">
        <!-- Gross COGS Card -->
        <div class="col-12 col-md-6 col-lg-4 mb-4">
            <div class="card bg-light-primary border-primary">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2 small">Gross COGS</span>
                            <div class="d-flex align-items-baseline">
                                <h4 class="mb-0 me-2">₱{{ number_format($summary['gross_cogs'], 2) }}</h4>
                            </div>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-primary">
                                <i class="bx bx-trending-up fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Return Deductions Card -->
        <div class="col-12 col-md-6 col-lg-4 mb-4">
            <div class="card bg-light-warning border-warning">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2 small">Return Deductions</span>
                            <div class="d-flex align-items-baseline">
                                <h4 class="mb-0 me-2">₱{{ number_format($summary['return_deductions'], 2) }}</h4>
                            </div>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-warning">
                                <i class="bx bx-minus-circle fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Net COGS Card -->
        <div class="col-12 col-md-6 col-lg-4 mb-4">
            <div class="card bg-light-success border-success">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2 small">Net COGS</span>
                            <div class="d-flex align-items-baseline">
                                <h4 class="mb-0 me-2">₱{{ number_format($summary['net_cogs'], 2) }}</h4>
                            </div>
                            <small class="text-muted">After approved returns</small>
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

    <!-- Monthly COGS Trend Chart -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Monthly COGS Trend - {{ $year }}</h5>
        </div>
        <div class="card-body">
            <canvas id="cogsTrendChart" height="80"></canvas>
        </div>
    </div>

    <!-- Per-item COGS Breakdown Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Per-Item COGS Breakdown</h5>
        </div>
        <div class="card-body">
            @forelse($perItem as $item)
                @if($loop->first)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr class="table-header-bg">
                                <th>Item Name</th>
                                <th class="text-end">Qty Sold</th>
                                <th class="text-end">Gross COGS (₱)</th>
                                <th class="text-end">Return Qty</th>
                                <th class="text-end">Return Value (₱)</th>
                                <th class="text-end text-success fw-bold">Net COGS (₱)</th>
                            </tr>
                        </thead>
                        <tbody>
                @endif
                            <tr>
                                <td>
                                    <span class="fw-medium">{{ $item->item_name }}</span>
                                </td>
                                <td class="text-end">{{ intval($item->qty_sold) }}</td>
                                <td class="text-end">{{ number_format($item->gross_cogs, 2) }}</td>
                                <td class="text-end">{{ intval($item->return_qty) }}</td>
                                <td class="text-end">{{ number_format($item->return_value, 2) }}</td>
                                <td class="text-end text-success fw-bold">{{ number_format($item->net_cogs, 2) }}</td>
                            </tr>
                @if($loop->last)
                        </tbody>
                        <tfoot>
                            <tr class="table-info fw-bold">
                                <td>TOTAL</td>
                                <td class="text-end">{{ intval($perItem->sum('qty_sold')) }}</td>
                                <td class="text-end">{{ number_format($perItem->sum('gross_cogs'), 2) }}</td>
                                <td class="text-end">{{ intval($perItem->sum('return_qty')) }}</td>
                                <td class="text-end">{{ number_format($perItem->sum('return_value'), 2) }}</td>
                                <td class="text-end text-success">{{ number_format($perItem->sum('net_cogs'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif
            @empty
                <div class="alert alert-info" role="alert">
                    <i class="bx bx-info-circle me-2"></i>
                    No sales data for this period.
                </div>
            @endforelse
        </div>
    </div>

    <!-- COGS Formula Explainer -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0 d-flex align-items-center justify-content-between">
                <span>COGS Calculation Formula</span>
                <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#formulaExplainer" aria-expanded="false">
                    <i class="bx bx-chevron-down"></i>
                </button>
            </h5>
        </div>
        <div id="formulaExplainer" class="collapse">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <p class="text-muted mb-3">
                            <strong>Cost of Goods Sold (COGS)</strong> represents the direct cost of inventory sold during a period. It's calculated using the following formula:
                        </p>
                        
                        <div class="bg-light p-3 rounded mb-3">
                            <div class="font-monospace small mb-2">
                                <strong>Gross COGS</strong> = SUM(quantity_sold × unit_price) <br>
                                <span class="text-muted">→ from <code>purchases</code> table</span>
                            </div>
                            
                            <div class="font-monospace small mb-2">
                                <strong>Deductions</strong> = SUM(approved returns × unit_price) <br>
                                <span class="text-muted">→ from <code>return_items</code> table where status = 'approved'</span>
                            </div>
                            
                            <hr class="my-2">
                            
                            <div class="font-monospace small">
                                <strong style="color: #28a745;">Net COGS</strong> = Gross COGS − Deductions <br>
                                <span class="text-muted">→ Final COGS after accounting for returns</span>
                            </div>
                        </div>
                        
                        <p class="text-muted small mb-0">
                            <strong>Note:</strong> Return deductions use the current item unit price. If an item was returned after a price change, the current price is used for valuation.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Chart.js for monthly trend -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const monthlyData = @json($monthlyTrend);
        
        const chartCanvas = document.getElementById('cogsTrendChart');
        if (chartCanvas) {
            const ctx = chartCanvas.getContext('2d');
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthlyData.map(d => d.label),
                    datasets: [{
                        label: 'Net COGS (₱)',
                        data: monthlyData.map(d => d.net_cogs),
                        backgroundColor: '#696cff',
                        borderColor: '#696cff',
                        borderRadius: 4,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return '₱' + Number(context.parsed.y).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + Number(value).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                                }
                            }
                        }
                    }
                }
            });
        }
    });
</script>

<style>
    .table-header-bg {
        background-color: #f7f8fa;
    }
    .bg-light-primary {
        background-color: rgba(105, 108, 255, 0.1);
    }
    .bg-light-warning {
        background-color: rgba(255, 193, 7, 0.1);
    }
    .bg-light-success {
        background-color: rgba(40, 167, 69, 0.1);
    }
    .border-primary {
        border-left: 4px solid #696cff !important;
    }
    .border-warning {
        border-left: 4px solid #ffc107 !important;
    }
    .border-success {
        border-left: 4px solid #28a745 !important;
    }
    .table-info {
        background-color: #e7f3ff;
    }
</style>

@endsection
