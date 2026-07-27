@extends('layout.app')

@section('title', 'Dashboard');

@section('content')

    <!-- Greeting Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1">Welcome Back, {{ Auth::user()->name }}!</h4>
            <p class="text-muted mb-0">Here's your analytics overview</p>
        </div>
    </div>

    <!-- Low Stock Alert Banner (unchanged logic/content) -->
    @if($lowStockCount > 0)
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bx bx-error-circle me-2 fs-5"></i>
        <div>
            <strong>{{ $lowStockCount }} item(s) are low on stock!</strong>
            <ul class="mb-0 mt-1">
                @foreach($lowStockItems as $item)
                <li>{{ $item->item_name }} — Current: <strong>{{ $item->on_hand_qty }}</strong> / Threshold: <strong>{{ $item->low_stock_threshold }}</strong></li>
                @endforeach
            </ul>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <!-- Key Stat Cards: Sales / Purchases / Orders / Customers + Inventory, one unified grid -->
    <div class="row">
        <div class="col-6 col-lg-3 mb-4">
            <div class="card dash-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="dash-stat-icon"><i class="bx bx-receipt"></i></span>
                        @include('admin.partials.dash-trend-badge', ['trend' => $salesOverview['trend']])
                    </div>
                    <h4 class="mb-0">₱{{ number_format($salesOverview['month']['total_amount'], 2) }}</h4>
                    <span class="text-muted small d-block">Total Sales</span>
                    <div class="text-muted small mt-1">
                        {{ $salesOverview['today']['total_transactions'] > 0 ? '₱' . number_format($salesOverview['today']['total_amount'], 2) . ' Today' : 'No sales today' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3 mb-4">
            <div class="card dash-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="dash-stat-icon"><i class="bx bx-cart-alt"></i></span>
                        @include('admin.partials.dash-trend-badge', ['trend' => $purchasesOverview['trend']])
                    </div>
                    <h4 class="mb-0">₱{{ number_format($purchasesOverview['month']['total_amount'], 2) }}</h4>
                    <span class="text-muted small d-block">Total Purchases</span>
                    <div class="text-muted small mt-1">
                        {{ $purchasesOverview['today']['total_transactions'] > 0 ? '₱' . number_format($purchasesOverview['today']['total_amount'], 2) . ' Today' : 'No purchases today' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3 mb-4">
            <div class="card dash-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="dash-stat-icon"><i class="bx bx-cart"></i></span>
                        @include('admin.partials.dash-trend-badge', ['trend' => $customerOrderStats['orders_trend']])
                    </div>
                    <h4 class="mb-0">{{ number_format($customerOrderStats['orders_this_month']) }}</h4>
                    <span class="text-muted small d-block">Total Orders</span>
                    <div class="text-muted small mt-1">This month</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3 mb-4">
            <div class="card dash-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="dash-stat-icon"><i class="bx bx-group"></i></span>
                        @include('admin.partials.dash-trend-badge', ['trend' => $customerOrderStats['customers_trend']])
                    </div>
                    <h4 class="mb-0">{{ number_format($customerOrderStats['total_customers']) }}</h4>
                    <span class="text-muted small d-block">Total Customers</span>
                    <div class="text-muted small mt-1">All time</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3 mb-4">
            <div class="card dash-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="dash-stat-icon"><i class="bx bx-package"></i></span>
                    </div>
                    <h4 class="mb-0">{{ $totalItems }}</h4>
                    <span class="text-muted small d-block">Total Items</span>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3 mb-4">
            <div class="card dash-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="dash-stat-icon"><i class="bx bx-box"></i></span>
                    </div>
                    <h4 class="mb-0">₱{{ number_format($inventorySnapshot['total_value'], 2) }}</h4>
                    <span class="text-muted small d-block">Inventory Value</span>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3 mb-4">
            <div class="card dash-stat-card {{ $lowStockCount > 0 ? 'dash-alert-card' : '' }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="dash-stat-icon {{ $lowStockCount > 0 ? 'dash-stat-icon-danger' : '' }}"><i class="bx bx-trending-down"></i></span>
                    </div>
                    <h4 class="mb-0">{{ $lowStockCount }}</h4>
                    <span class="text-muted small d-block">Low Stock</span>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3 mb-4">
            <div class="card dash-stat-card {{ $inventorySnapshot['out_of_stock_count'] > 0 ? 'dash-alert-card' : '' }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="dash-stat-icon {{ $inventorySnapshot['out_of_stock_count'] > 0 ? 'dash-stat-icon-danger' : '' }}"><i class="bx bx-x-circle"></i></span>
                    </div>
                    <h4 class="mb-0">{{ $inventorySnapshot['out_of_stock_count'] }}</h4>
                    <span class="text-muted small d-block">Out of Stock</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Hero chart (Sales vs POS Revenue vs Expenses) + 5-day comparison -->
    <div class="row">
        <div class="col-12 col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-3">
                        <div>
                            <span class="text-muted d-block mb-1">
                                <span class="dash-legend-dot" style="background-color:#696cff"></span> Total Sales
                            </span>
                            <div class="d-flex align-items-baseline gap-2">
                                <h5 class="mb-0">₱{{ number_format($salesOverview['month']['total_amount'], 2) }}</h5>
                                @include('admin.partials.dash-trend-badge', ['trend' => $salesOverview['trend']])
                            </div>
                        </div>
                        <div>
                            <span class="text-muted d-block mb-1">
                                <span class="dash-legend-dot" style="background-color:#8592a3"></span> POS Revenue
                            </span>
                            <h5 class="mb-0">₱{{ number_format($totalRevenue, 2) }}</h5>
                            <span class="text-muted small">this year</span>
                        </div>
                        <div class="text-md-end">
                            <span class="text-muted d-block mb-1">
                                <span class="dash-legend-dot" style="background-color:#ffab00"></span> Expenses
                            </span>
                            <h5 class="mb-0">₱{{ number_format($expensesOverview['month']['total_amount'], 2) }}</h5>
                            @include('admin.partials.dash-trend-badge', ['trend' => $expensesOverview['trend'], 'invert' => true])
                        </div>
                    </div>
                    <div id="monthlySalesTrendChart" style="height: 260px;"></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title m-0">Last 5 Days</h5>
                    <small class="text-muted">Sales &amp; Purchases</small>
                </div>
                <div class="card-body">
                    <div id="last5DaysChart" style="height: 260px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Alert & Recent Invoices -->
    <div class="row">
        <div class="col-12 col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title m-0">Stock Alert</h5>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Supplier</th>
                                <th class="text-end">Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lowStockItems->take(5) as $item)
                            <tr>
                                <td>{{ $item->item_name }}</td>
                                <td>{{ $item->barcode ?? $item->code ?? '—' }}</td>
                                <td>{{ $item->supplier->supplier_name ?? '—' }}</td>
                                <td class="text-end text-danger fw-semibold">{{ $item->on_hand_qty }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No low-stock items right now.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title m-0">Recent Invoices</h5>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Invoice Number</th>
                                <th>Name</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentInvoices as $invoice)
                            <tr>
                                <td><a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->sales_no }}</a></td>
                                <td>{{ $invoice->customer_name }}</td>
                                <td class="text-end">₱{{ number_format($invoice->total_sales, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No invoices yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Expired Products: logic/data untouched, container restyled to match -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0">Expired Products</h5>
                    <a href="{{ route('admin.reports.expiration') }}" class="btn btn-sm btn-outline-secondary">View Full Report</a>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-danger me-2">{{ $expiredProducts['expired_count'] }}</span>
                        <span class="text-muted">batch(es) already expired and still in stock</span>
                    </div>
                    @forelse ($expiredProducts['items'] as $batch)
                        <div class="d-flex justify-content-between align-items-center py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div>
                                <div class="fw-semibold">{{ $batch->product->item_name ?? 'N/A' }}</div>
                                <div class="text-muted small">Batch {{ $batch->batch_no ?? '—' }} · Qty {{ $batch->qty }}</div>
                            </div>
                            <span class="badge bg-danger">{{ $batch->expiration_date->format('M d, Y') }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No expired batches in stock.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Pending Action Items -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title m-0">Pending Action Items</h5>
                </div>
                @php
                    $totalPending = $pendingActionItems['dr_for_delivery'] + $pendingActionItems['dr_not_fully_invoiced'] + $pendingActionItems['so_in_progress'] + $pendingActionItems['po_awaiting_receipt'];
                @endphp
                @if($totalPending > 0)
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="{{ route('delivery-receipts.index') }}" class="text-body">Deliveries — For Delivery</a>
                            <span class="badge {{ $pendingActionItems['dr_for_delivery'] > 0 ? 'bg-label-warning' : 'bg-label-secondary' }}">{{ $pendingActionItems['dr_for_delivery'] }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="{{ route('delivery-receipts.index') }}" class="text-body">Deliveries — Not Fully Invoiced</a>
                            <span class="badge {{ $pendingActionItems['dr_not_fully_invoiced'] > 0 ? 'bg-label-warning' : 'bg-label-secondary' }}">{{ $pendingActionItems['dr_not_fully_invoiced'] }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="{{ route('sales-orders.index') }}" class="text-body">Sales Orders In Progress</a>
                            <span class="badge {{ $pendingActionItems['so_in_progress'] > 0 ? 'bg-label-warning' : 'bg-label-secondary' }}">{{ $pendingActionItems['so_in_progress'] }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="{{ route('purchase-orders.index') }}" class="text-body">Purchase Orders Awaiting Receipt</a>
                            <span class="badge {{ $pendingActionItems['po_awaiting_receipt'] > 0 ? 'bg-label-warning' : 'bg-label-secondary' }}">{{ $pendingActionItems['po_awaiting_receipt'] }}</span>
                        </li>
                    </ul>
                @else
                    <div class="card-body">
                        <div class="d-flex align-items-center text-muted">
                            <i class="bx bx-check-circle fs-4 me-2 text-success"></i>
                            All caught up — nothing pending right now.
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Point of Sale -->
    <span class="dash-section-label">Point of Sale</span>
    <div class="row">
        <div class="col-6 col-md-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2">POS Transactions</span>
                            <h4 class="mb-0">{{ $totalPurchases }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-secondary">
                                <i class="bx bx-shopping-bag fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2">POS Revenue</span>
                            <h4 class="mb-0">₱{{ number_format($totalRevenue, 2) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-secondary">
                                <i class="bx bx-store fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0">Recent POS Sales</h5>
                    <small class="text-muted">Latest 10</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Qty Sold</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPurchases as $purchase)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $purchase->productBatch->product->item_name ?? 'N/A' }}</td>
                                <td>{{ $purchase->productBatch->product->category->category_name ?? 'N/A' }}</td>
                                <td>{{ $purchase->quantity_sold }}</td>
                                <td>₱{{ number_format($purchase->unit_price, 2) }}</td>
                                <td>₱{{ number_format($purchase->total_price, 2) }}</td>
                                <td>{{ \Carbon\Carbon::parse($purchase->purchase_date)->format('M d, Y') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bx bx-inbox fs-4 mb-2"></i>
                                    <p class="mb-0">No purchases yet.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <span class="dash-section-label">Recent Activity</span>
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0">Latest Invoices, Delivery Receipts &amp; Goods Receipts</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Reference</th>
                                <th>Customer / Supplier</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentActivity as $activity)
                            <tr>
                                <td><span class="badge bg-{{ $activity['badge'] }}">{{ $activity['type'] }}</span></td>
                                <td>{{ $activity['reference'] }}</td>
                                <td>{{ $activity['detail'] }}</td>
                                <td>{{ $activity['amount'] !== null ? '₱' . number_format($activity['amount'], 2) : '—' }}</td>
                                <td>{{ $activity['at']->format('M d, Y h:i A') }}</td>
                                <td><a href="{{ $activity['url'] }}" class="btn btn-sm btn-info">View</a></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No recent activity.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<style>
    /* Dashboard-only design system: section labels, compact stat cards with
       an icon chip, and one semantic accent rule (red = needs attention,
       green = positive trend, everything else neutral) layered on top of
       the existing Sneat/Bootstrap classes already used across this app. */
    .dash-section-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #8592a3;
        margin: 0.5rem 0 0.75rem;
    }
    .dash-stat-card .card-body {
        padding: 1.25rem;
    }
    .dash-stat-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.5rem;
        background-color: rgba(105,108,255,0.08);
        color: #696cff;
        font-size: 1.1rem;
    }
    .dash-alert-card {
        border-color: rgba(220,53,69,0.35);
    }
    .dash-stat-icon-danger {
        background-color: rgba(220,53,69,0.08);
        color: #dc3545;
    }
    .dash-legend-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 4px;
    }
</style>

@endsection

@section('scripts')
    <script>
        // Wait for ApexCharts to be loaded before initializing the hero chart —
        // Invoice-based Sales vs. POS Revenue vs. Expenses plotted together for a direct comparison
        function initMonthlySalesTrendChart() {
            if (typeof ApexCharts === 'undefined') {
                setTimeout(initMonthlySalesTrendChart, 100);
                return;
            }

            const monthlySalesTrend = @json($monthlySalesTrend);
            const monthlyRevenue = @json($monthlyRevenue);
            const monthlyExpensesTrend = @json($monthlyExpensesTrend);
            const salesData = Array.from({ length: 12 }, (_, i) => monthlySalesTrend[i + 1] || 0);
            const posRevenueData = Array.from({ length: 12 }, (_, i) => monthlyRevenue[i + 1] || 0);
            const expensesData = Array.from({ length: 12 }, (_, i) => monthlyExpensesTrend[i + 1] || 0);

            const options = {
                chart: {
                    type: 'line',
                    height: 260,
                    toolbar: { show: false }
                },
                series: [
                    { name: 'Total Sales', data: salesData },
                    { name: 'POS Revenue', data: posRevenueData },
                    { name: 'Expenses', data: expensesData }
                ],
                stroke: {
                    curve: 'smooth',
                    width: [3, 2, 2],
                    dashArray: [0, 4, 2]
                },
                xaxis: {
                    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                },
                colors: ['#696cff', '#8592a3', '#ffab00'],
                dataLabels: { enabled: false },
                legend: { show: false },
                yaxis: {
                    labels: {
                        formatter: val => '₱' + val.toLocaleString()
                    }
                },
                tooltip: {
                    y: {
                        formatter: val => '₱' + val.toLocaleString()
                    }
                }
            };

            new ApexCharts(document.querySelector("#monthlySalesTrendChart"), options).render();
        }

        // Wait for ApexCharts to be loaded before initializing the last-5-days comparison chart
        function initLast5DaysChart() {
            if (typeof ApexCharts === 'undefined') {
                setTimeout(initLast5DaysChart, 100);
                return;
            }

            const last5Days = @json($last5DaysComparison);

            const options = {
                chart: {
                    type: 'bar',
                    height: 260,
                    toolbar: { show: false }
                },
                plotOptions: {
                    bar: { columnWidth: '55%', borderRadius: 4 }
                },
                series: [
                    { name: 'Purchases', data: last5Days.map(d => d.purchases) },
                    { name: 'Sales', data: last5Days.map(d => d.sales) }
                ],
                xaxis: {
                    categories: last5Days.map(d => d.label)
                },
                colors: ['#e6dfff', '#696cff'],
                dataLabels: { enabled: false },
                legend: { position: 'top', horizontalAlign: 'right' },
                yaxis: {
                    labels: {
                        formatter: val => '₱' + val.toLocaleString()
                    }
                },
                tooltip: {
                    y: {
                        formatter: val => '₱' + val.toLocaleString()
                    }
                }
            };

            new ApexCharts(document.querySelector("#last5DaysChart"), options).render();
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', initMonthlySalesTrendChart);
        document.addEventListener('DOMContentLoaded', initLast5DaysChart);
    </script>
@endsection
