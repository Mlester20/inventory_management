@extends('layout.app')

@section('title', 'Undelivered Items per Customer')

@section('content')

<style>
    /* Compact customer cards: an open card grows on its own, its neighbours keep their height. */
    .undelivered-card .card-header { padding: .75rem 1rem; }
    .undelivered-card .item-row + .item-row { border-top: 1px solid rgba(0, 0, 0, .06); }
    .undelivered-card .so-line { font-size: .8125rem; }

    @media print {
        .no-print, .layout-menu, .layout-navbar, footer.content-footer { display: none !important; }
        .layout-page { padding-left: 0 !important; }
        .collapse:not(.show) { display: block !important; }
        .undelivered-card { break-inside: avoid; }
        .undelivered-grid > div { flex: 0 0 50% !important; max-width: 50% !important; }
    }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="mb-3">
        <h4 class="mb-1">Undelivered Items per Customer</h4>
        <p class="text-muted mb-0 small">
            What is still owed on each customer's Sales Orders (ordered minus delivered), added up across all of
            their Sales Orders. Quantities only. Draft, cancelled and archived Sales Orders are not included.
        </p>
    </div>

    <!-- Filters -->
    <div class="card mb-3 no-print">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('admin.reports.undelivered-items') }}" class="row g-2 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label for="customer_id" class="form-label mb-1">Customer</label>
                    <select name="customer_id" id="customer_id" class="form-select">
                        <option value="">All customers</option>
                        @foreach($customerOptions as $option)
                            <option value="{{ $option->id }}" {{ (int) $filters['customer_id'] === $option->id ? 'selected' : '' }}>{{ $option->customer_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label for="start_date" class="form-label mb-1">SO date from</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $filters['start_date'] }}">
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label for="end_date" class="form-label mb-1">To</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $filters['end_date'] }}">
                </div>
                <div class="col-lg-4 col-md-12">
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary text-nowrap">Apply Filter</button>
                        <a href="{{ route('admin.reports.undelivered-items') }}" class="btn btn-outline-secondary text-nowrap" title="Clear all filters">
                            <i class="bx bx-refresh"></i> Clear
                        </a>
                        <a href="{{ route('admin.reports.undelivered-items.export', request()->query()) }}" class="btn btn-outline-secondary text-nowrap" title="Download as Excel">
                            <i class="bx bx-download"></i> Excel
                        </a>
                        <button type="button" class="btn btn-outline-secondary text-nowrap" onclick="window.print()" title="Print">
                            <i class="bx bx-printer"></i> Print
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Totals: one slim strip -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row text-center g-0">
                <div class="col-4 border-end">
                    <div class="text-muted small">Customers</div>
                    <div class="h5 mb-0">{{ number_format($totals['customers']) }}</div>
                </div>
                <div class="col-4 border-end">
                    <div class="text-muted small">Open Sales Orders</div>
                    <div class="h5 mb-0">{{ number_format($totals['orders']) }}</div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Undelivered qty</div>
                    <div class="h5 mb-0">{{ number_format($totals['balance']) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($customers->isEmpty())
        <div class="card"><div class="card-body text-center text-muted py-5">
            Nothing is waiting to be delivered{{ $filters['customer_id'] || $filters['start_date'] || $filters['end_date'] ? ' for this filter' : '' }}.
        </div></div>
    @else
        <div class="mb-2 no-print d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="expandAllBtn">Expand all</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseAllBtn">Collapse all</button>
        </div>

        <!-- One compact card per customer, side by side -->
        <div class="row g-3 align-items-start undelivered-grid">
            @foreach($customers as $customer)
                <div class="col-xl-4 col-md-6">
                    <div class="card undelivered-card">
                        <div class="card-header d-flex justify-content-between align-items-center gap-2"
                             role="button" data-bs-toggle="collapse" data-bs-target="#cust-{{ $customer['customer_id'] }}" aria-expanded="false">
                            <div class="text-truncate">
                                <div class="fw-semibold text-truncate" title="{{ $customer['customer_name'] }}">{{ $customer['customer_name'] }}</div>
                                <small class="text-muted">
                                    {{ $customer['so_count'] }} SO{{ $customer['so_count'] === 1 ? '' : 's' }} &middot;
                                    {{ $customer['item_count'] }} item{{ $customer['item_count'] === 1 ? '' : 's' }}
                                </small>
                            </div>
                            <span class="badge bg-warning fs-6 flex-shrink-0" title="Total undelivered quantity">{{ number_format($customer['total_balance']) }}</span>
                        </div>
                        <div class="collapse" id="cust-{{ $customer['customer_id'] }}">
                            <div class="card-body pt-2 pb-2">
                                @foreach($customer['items'] as $item)
                                    <div class="item-row py-2">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="fw-semibold">{{ $item['generic_label'] }}</div>
                                                <small class="text-muted">{{ number_format($item['ordered']) }} ordered &middot; {{ number_format($item['delivered']) }} delivered</small>
                                            </div>
                                            <span class="badge bg-label-warning flex-shrink-0">{{ number_format($item['balance']) }} left</span>
                                        </div>
                                        @foreach($item['orders'] as $order)
                                            <div class="so-line text-muted mt-1">
                                                <a href="{{ route('sales-orders.show', $order['so_id']) }}">{{ $order['so_no'] }}</a>
                                                {{ \Illuminate\Support\Carbon::parse($order['order_date'])->format('M d') }}
                                                @if($order['product']) &middot; {{ $order['product'] }} @endif
                                                &mdash; {{ number_format($order['delivered']) }}/{{ number_format($order['ordered']) }} delivered,
                                                <strong class="text-body">{{ number_format($order['balance']) }} left</strong>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    document.getElementById('expandAllBtn')?.addEventListener('click', () => {
        document.querySelectorAll('.collapse').forEach(el => el.classList.add('show'));
    });
    document.getElementById('collapseAllBtn')?.addEventListener('click', () => {
        document.querySelectorAll('.collapse').forEach(el => el.classList.remove('show'));
    });
</script>
@endsection
