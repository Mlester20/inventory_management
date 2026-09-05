@extends('layout.app')

@section('title', 'Cost of Goods Sold (COGS)')

@section('content')

@php
    // Status framing for margin health — fixed, reserved meaning, always
    // paired with an icon + label (never color alone).
    $marginStatus = function (float $pct) {
        if ($pct < 0) {
            return ['label' => 'Loss', 'class' => 'danger', 'icon' => 'bx-trending-down'];
        }
        if ($pct < 20) {
            return ['label' => 'Thin margin', 'class' => 'warning', 'icon' => 'bx-error'];
        }
        return ['label' => 'Healthy margin', 'class' => 'success', 'icon' => 'bx-check-circle'];
    };
    $heroStatus = $marginStatus($summary['margin_percent']);
@endphp

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

    <!-- Profitability Hero Banner — the one number this page leads with -->
    <div class="card mb-4 border-{{ $heroStatus['class'] }}">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-12 col-lg-5 mb-3 mb-lg-0">
                    <span class="text-muted d-block mb-1 small">GROSS PROFIT MARGIN</span>
                    <div class="d-flex align-items-baseline gap-3 flex-wrap">
                        <span class="hero-figure">{{ number_format($summary['margin_percent'], 1) }}%</span>
                        <span class="badge bg-{{ $heroStatus['class'] }}">
                            <i class="bx {{ $heroStatus['icon'] }} me-1"></i>{{ $heroStatus['label'] }}
                        </span>
                    </div>
                    <span class="text-muted small">
                        Gross Profit ₱{{ number_format($summary['gross_profit'], 2) }} on Revenue ₱{{ number_format($summary['revenue'], 2) }}
                    </span>
                </div>
                <div class="col-12 col-lg-7">
                    <div class="row g-3">
                        <div class="col-6 col-md-4">
                            <div class="kpi-tile">
                                <span class="text-muted d-block small">Revenue</span>
                                <span class="kpi-value">₱{{ number_format($summary['revenue'], 2) }}</span>
                                <span class="text-muted small d-block">POS + Wholesale, net of refunds</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="kpi-tile">
                                <span class="text-muted d-block small">Net COGS</span>
                                <span class="kpi-value">₱{{ number_format($summary['net_cogs'], 2) }}</span>
                                <span class="text-muted small d-block">Cost of items actually sold</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="kpi-tile">
                                <span class="text-muted d-block small">Return Deductions</span>
                                <span class="kpi-value">₱{{ number_format($summary['return_deductions'], 2) }}</span>
                                <span class="text-muted small d-block">Gross COGS ₱{{ number_format($summary['gross_cogs'], 2) }} minus this</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Revenue vs COGS Trend Chart -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Revenue vs. Cost — {{ $year }}</h5>
            <small class="text-muted">The gap between the bars each month is the profit</small>
        </div>
        <div class="card-body">
            <canvas id="cogsTrendChart" height="80"></canvas>
        </div>
    </div>

    <!-- Per-item COGS Breakdown Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Per-Item Breakdown</h5>
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
                                <th class="text-end">Revenue (₱)</th>
                                <th class="text-end">Net COGS (₱)</th>
                                <th class="text-end">Margin</th>
                                <th class="text-end">Return Qty</th>
                                <th class="text-end">Return Value (₱)</th>
                            </tr>
                        </thead>
                        <tbody>
                @endif
                            @php $rowStatus = $marginStatus($item->margin_percent); @endphp
                            <tr>
                                <td>
                                    <span class="fw-medium">{{ $item->item_name }}</span>
                                </td>
                                <td class="text-end">{{ intval($item->qty_sold) }}</td>
                                <td class="text-end">{{ number_format($item->revenue, 2) }}</td>
                                <td class="text-end">{{ number_format($item->net_cogs, 2) }}</td>
                                <td class="text-end">
                                    <span class="badge bg-{{ $rowStatus['class'] }}">{{ number_format($item->margin_percent, 1) }}%</span>
                                </td>
                                <td class="text-end">{{ intval($item->return_qty) }}</td>
                                <td class="text-end">{{ number_format($item->return_value, 2) }}</td>
                            </tr>
                @if($loop->last)
                        </tbody>
                        <tfoot>
                            <tr class="table-info fw-bold">
                                <td>TOTAL</td>
                                <td class="text-end">{{ intval($perItem->sum('qty_sold')) }}</td>
                                <td class="text-end">{{ number_format($perItem->sum('revenue'), 2) }}</td>
                                <td class="text-end">{{ number_format($perItem->sum('net_cogs'), 2) }}</td>
                                <td class="text-end">
                                    @php
                                        $totalRevenue = (float) $perItem->sum('revenue');
                                        $totalNetCogs = (float) $perItem->sum('net_cogs');
                                        $totalMargin = $totalRevenue > 0 ? (($totalRevenue - $totalNetCogs) / $totalRevenue) * 100 : 0;
                                        $totalStatus = $marginStatus($totalMargin);
                                    @endphp
                                    <span class="badge bg-{{ $totalStatus['class'] }}">{{ number_format($totalMargin, 1) }}%</span>
                                </td>
                                <td class="text-end">{{ intval($perItem->sum('return_qty')) }}</td>
                                <td class="text-end">{{ number_format($perItem->sum('return_value'), 2) }}</td>
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
                <span>How these numbers are calculated</span>
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
                            <strong>Cost of Goods Sold (COGS)</strong> represents the direct cost of inventory sold during a period, combined across <strong>both</strong> sales channels — POS and Wholesale (Invoices). Here's the actual computation for the period currently shown above:
                        </p>

                        <div class="bg-light p-3 rounded mb-3">
                            <div class="font-monospace small mb-2">
                                <strong>Gross COGS</strong> = SUM(qty × unit_cost) <br>
                                POS ₱{{ number_format($summary['pos_cogs'], 2) }} + Wholesale ₱{{ number_format($summary['wholesale_cogs'], 2) }}
                                = <strong>₱{{ number_format($summary['gross_cogs'], 2) }}</strong> <br>
                                <span class="text-muted">→ POS: from <code>purchases</code>. Wholesale: from <code>sales</code> joined to non-cancelled <code>invoices</code>.</span>
                            </div>

                            <div class="font-monospace small mb-2">
                                <strong>Return Deductions</strong> = SUM(approved returns × unit_cost) = <strong>₱{{ number_format($summary['return_deductions'], 2) }}</strong> <br>
                                <span class="text-muted">→ from <code>return_items</code> where status = 'approved'</span>
                            </div>

                            <div class="font-monospace small mb-2">
                                <strong class="text-success">Net COGS</strong> = ₱{{ number_format($summary['gross_cogs'], 2) }} − ₱{{ number_format($summary['return_deductions'], 2) }}
                                = <strong class="text-success">₱{{ number_format($summary['net_cogs'], 2) }}</strong>
                            </div>

                            <hr class="my-2">

                            <div class="font-monospace small mb-2">
                                <strong>Revenue</strong> = POS ₱{{ number_format($summary['pos_revenue'], 2) }} + Wholesale ₱{{ number_format($summary['wholesale_revenue'], 2) }} − Refunds ₱{{ number_format($summary['return_refunds'], 2) }}
                                = <strong>₱{{ number_format($summary['revenue'], 2) }}</strong> <br>
                                <span class="text-muted">→ POS: <code>purchases.total_price</code>. Wholesale: <code>sales.amount</code>. Refunds: <code>return_items.refund_amount</code> where status = 'approved'.</span>
                            </div>

                            <div class="font-monospace small">
                                <strong class="text-success">Gross Profit</strong> = ₱{{ number_format($summary['revenue'], 2) }} − ₱{{ number_format($summary['net_cogs'], 2) }}
                                = <strong class="text-success">₱{{ number_format($summary['gross_profit'], 2) }}</strong>
                                (<strong class="text-success">{{ number_format($summary['margin_percent'], 1) }}%</strong> of Revenue)
                            </div>
                        </div>

                        <p class="text-muted small mb-1">
                            <strong>Margin colors:</strong>
                            <span class="badge bg-success">≥ 20%</span> Healthy ·
                            <span class="badge bg-warning">0–19.9%</span> Thin ·
                            <span class="badge bg-danger">below 0%</span> Loss
                        </p>

                        <p class="text-muted small mb-0">
                            <strong>Note:</strong> Cost uses <code>products.unit_cost</code> — the product's current/latest cost (updated on every Goods Receipt), not a per-batch historical snapshot, since batches don't store their own cost. A cancelled Invoice's sales are excluded entirely (both cost and revenue) — they no longer count as a valid transaction.
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
            const peso = (value) => '₱' + Number(value).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthlyData.map(d => d.label),
                    datasets: [
                        {
                            label: 'Revenue',
                            data: monthlyData.map(d => d.revenue),
                            backgroundColor: '#03c3ec',
                            borderRadius: 4,
                            maxBarThickness: 24,
                        },
                        {
                            label: 'Net COGS',
                            data: monthlyData.map(d => d.net_cogs),
                            backgroundColor: '#ffc107',
                            borderRadius: 4,
                            maxBarThickness: 24,
                        },
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'end',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + '₱' + Number(context.parsed.y).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                },
                                afterBody: function(items) {
                                    if (items.length < 2) return '';
                                    const revenue = items.find(i => i.dataset.label === 'Revenue')?.parsed.y ?? 0;
                                    const cogs = items.find(i => i.dataset.label === 'Net COGS')?.parsed.y ?? 0;
                                    return 'Profit: ' + peso(revenue - cogs);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: { callback: (value) => peso(value) }
                        },
                        x: {
                            grid: { display: false }
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
    .bg-light-info {
        background-color: rgba(3, 195, 236, 0.1);
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
    .border-info {
        border-left: 4px solid #03c3ec !important;
    }
    .border-danger {
        border-left: 4px solid #dc3545 !important;
    }
    .table-info {
        background-color: #e7f3ff;
    }
    .hero-figure {
        font-size: 3rem;
        font-weight: 600;
        line-height: 1;
    }
    .kpi-tile {
        padding: 0.75rem;
        border-radius: 0.5rem;
        background-color: #f7f8fa;
        height: 100%;
    }
    .kpi-value {
        display: block;
        font-size: 1.15rem;
        font-weight: 600;
    }
</style>

@endsection
