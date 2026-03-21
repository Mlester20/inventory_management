@extends('layout.app')

@section('title', 'Dashboard');

@section('content')

    <!-- Low Stock Alert Banner -->
    @if($lowStockCount > 0)
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bx bx-error-circle me-2 fs-5"></i>
        <div>
            <strong>{{ $lowStockCount }} item(s) are low on stock!</strong>
            <ul class="mb-0 mt-1">
                @foreach($lowStockItems as $item)
                <li>{{ $item->item_name }} — Current: <strong>{{ $item->quantity }}</strong> / Threshold: <strong>{{ $item->low_stock_threshold }}</strong></li>
                @endforeach
            </ul>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <!-- Summary Stat Cards -->
    <div class="row">
        <!-- Total Items Card -->
        <div class="col-12 col-md-6 col-lg-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2">Total Items</span>
                            <div class="d-flex align-items-baseline">
                                <h4 class="mb-0 me-2">{{ $totalItems }}</h4>
                            </div>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="bx bx-package fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Stock Card -->
        <div class="col-12 col-md-6 col-lg-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2">Total Revenue</span>
                            <div class="d-flex align-items-baseline">
                                <h4 class="mb-0 me-2">₱{{ number_format($totalRevenue, 2) }}</h4>
                            </div>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="bx bx-store fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts Card -->
        <div class="col-12 col-md-6 col-lg-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2">Low Stock Alerts</span>
                            <div class="d-flex align-items-baseline">
                                <h4 class="mb-0 me-2">{{ $lowStockCount }}</h4>
                                @if($lowStockCount > 0)
                                    <span class="badge bg-danger">Alert</span>
                                @endif
                            </div>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="bx bx-alert fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Purchases Card -->
        <div class="col-12 col-md-6 col-lg-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-2">Total Sales</span>
                            <div class="d-flex align-items-baseline">
                                <h4 class="mb-0 me-2">{{ $totalPurchases }}</h4>
                            </div>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-info">
                                <i class="bx bx-shopping-bag fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Monthly Revenue Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title m-0">Monthly Revenue</h5>
                    <small class="text-muted">Current Year {{ now()->year }}</small>
                </div>
                <div class="card-body">
                    <div id="totalRevenueChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Overview Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0">Stock Overview</h5>
                    <small class="text-muted">Current stock vs thresholds</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Supplier</th>
                                <th>Current Stock</th>
                                <th>Low Stock Threshold</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stockItems as $item)
                            <tr>
                                <td>{{ $item->item_name }}</td>
                                <td>{{ $item->category->category_name ?? 'N/A' }}</td>
                                <td>{{ $item->supplier->supplier_name ?? 'N/A' }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ $item->low_stock_threshold }}</td>
                                <td>
                                    @if($item->quantity <= $item->low_stock_threshold)
                                        <span class="badge bg-danger">Low Stock</span>
                                    @else
                                        <span class="badge bg-success">OK</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bx bx-inbox fs-4 mb-2"></i>
                                    <p class="mb-0">No items available</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Purchases Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0">Recent Sales</h5>
                    <small class="text-muted">Latest 10 sales</small>
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
                                <td>{{ $purchase->item->item_name ?? 'N/A' }}</td>
                                <td>{{ $purchase->item->category->category_name ?? 'N/A' }}</td>
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

@endsection

@section('scripts')
    <script>
        // Wait for ApexCharts to be loaded before initializing the chart
        function initRevenueChart() {
            if (typeof ApexCharts === 'undefined') {
                setTimeout(initRevenueChart, 100);
                return;
            }

            const monthlyRevenue = @json($monthlyRevenue);
            const revenueData = Array.from({ length: 12 }, (_, i) => monthlyRevenue[i + 1] || 0);

            const options = {
                chart: {
                    type: 'line',
                    height: 300,
                    toolbar: { show: true }
                },
                series: [{
                    name: 'Revenue (₱)',
                    data: revenueData
                }],
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                markers: {
                    size: 5
                },
                xaxis: {
                    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                },
                colors: ['#696cff'],
                dataLabels: {
                    enabled: false
                },
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

            new ApexCharts(document.querySelector("#totalRevenueChart"), options).render();
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', initRevenueChart);
    </script>
@endsection