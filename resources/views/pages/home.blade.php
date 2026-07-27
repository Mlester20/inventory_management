@extends('layout.user')

@section('title', 'Dashboard')

@section('content')

<div class="container-xxl flex-grow-1 container-p-y">

    <!-- Greeting + Quick Actions -->
    <div class="row mb-4">
        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h4 class="mb-1">Welcome back, {{ Auth::user()->name }}!</h4>
                <p class="text-muted mb-0">Here's a quick look at your sales activity</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('pos') }}" class="btn btn-primary">
                    <i class="bx bx-cart-add me-1"></i> New Sale
                </a>
                <a href="{{ route('purchases.history') }}" class="btn btn-outline-secondary">
                    <i class="bx bx-history me-1"></i> Sales History
                </a>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row">
        <div class="col-12 col-md-6 col-lg-3 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="text-muted d-block mb-2">Today's Sales</span>
                            <h4 class="mb-0">₱{{ number_format($todayAmount, 2) }}</h4>
                        </div>
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="bx bx-receipt"></i>
                        </span>
                    </div>
                    <div class="text-muted small mt-2">{{ $todayCount }} transaction(s) today</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="text-muted d-block mb-2">This Month</span>
                            <h4 class="mb-0">₱{{ number_format($monthAmount, 2) }}</h4>
                        </div>
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="bx bx-calendar"></i>
                        </span>
                    </div>
                    <div class="text-muted small mt-2">{{ $monthCount }} transaction(s) this month</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="text-muted d-block mb-2">All-Time Transactions</span>
                            <h4 class="mb-0">{{ number_format($allTimeCount) }}</h4>
                        </div>
                        <span class="avatar-initial rounded bg-label-info">
                            <i class="bx bx-shopping-bag"></i>
                        </span>
                    </div>
                    <div class="text-muted small mt-2">Since you started using POS</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3 mb-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column justify-content-between h-100">
                    <div>
                        <span class="text-muted d-block mb-2">Quick Start</span>
                        <p class="mb-0 small">Ready to ring up a customer?</p>
                    </div>
                    <a href="{{ route('pos') }}" class="btn btn-sm btn-primary mt-2">
                        <i class="bx bx-cart-add me-1"></i> Go to POS
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0">Your Recent Transactions</h5>
                    <a href="{{ route('purchases.history') }}" class="small">View all &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Total</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentTransactions as $transaction)
                                <tr>
                                    <td>{{ $transaction->productBatch->product->item_name ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge bg-label-primary">
                                            {{ $transaction->productBatch->product->category->category_name ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ $transaction->quantity_sold }}</td>
                                    <td class="text-end">₱{{ number_format($transaction->total_price, 2) }}</td>
                                    <td>{{ $transaction->purchase_date->format('M d, Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="bx bx-inbox fs-1 mb-2"></i>
                                        <p class="mb-0">No sales yet — head to POS to ring up your first sale.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection
