@extends('layout.app')

@section('title', 'Undelivered Items per Customer')

@section('content')

<style>
    @media print {
        .no-print, .layout-menu, .layout-navbar, footer.content-footer { display: none !important; }
        .layout-page { padding-left: 0 !important; }
        .collapse:not(.show) { display: block !important; }
        .card { break-inside: avoid; }
    }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1">Undelivered Items per Customer</h4>
            <p class="text-muted mb-0">
                What is still owed on each customer's Sales Orders (ordered minus delivered), added up across all
                of their Sales Orders. Quantities only. Draft, cancelled and archived Sales Orders are not included.
            </p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.reports.undelivered-items') }}" class="row g-3">
                <div class="col-md-4">
                    <label for="customer_id" class="form-label">Customer</label>
                    <select name="customer_id" id="customer_id" class="form-select">
                        <option value="">All customers</option>
                        @foreach($customerOptions as $option)
                            <option value="{{ $option->id }}" {{ (int) $filters['customer_id'] === $option->id ? 'selected' : '' }}>{{ $option->customer_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="start_date" class="form-label">SO date from</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $filters['start_date'] }}">
                </div>
                <div class="col-md-2">
                    <label for="end_date" class="form-label">to</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $filters['end_date'] }}">
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply Filter</button>
                    <a href="{{ route('admin.reports.undelivered-items') }}" class="btn btn-outline-secondary" title="Clear all filters">
                        <i class="bx bx-refresh"></i>
                    </a>
                    <a href="{{ route('admin.reports.undelivered-items.export', request()->query()) }}" class="btn btn-outline-success" title="Download as Excel">
                        <i class="bx bx-download"></i> Excel
                    </a>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()" title="Print">
                        <i class="bx bx-printer"></i> Print
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Totals -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">Customers with undelivered items</div>
                <h3 class="mb-0">{{ number_format($totals['customers']) }}</h3>
            </div></div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">Open Sales Orders</div>
                <h3 class="mb-0">{{ number_format($totals['orders']) }}</h3>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">Total undelivered quantity</div>
                <h3 class="mb-0">{{ number_format($totals['balance']) }}</h3>
            </div></div>
        </div>
    </div>

    @if($customers->isEmpty())
        <div class="card"><div class="card-body text-center text-muted py-5">
            Nothing is waiting to be delivered{{ $filters['customer_id'] || $filters['start_date'] || $filters['end_date'] ? ' for this filter' : '' }}.
        </div></div>
    @else
        <div class="mb-3 no-print">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="expandAllBtn">Expand all</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseAllBtn">Collapse all</button>
        </div>

        @foreach($customers as $customer)
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"
                     role="button" data-bs-toggle="collapse" data-bs-target="#cust-{{ $customer['customer_id'] }}" aria-expanded="false">
                    <div>
                        <h5 class="mb-0">{{ $customer['customer_name'] }}</h5>
                        <small class="text-muted">
                            {{ $customer['so_count'] }} Sales Order{{ $customer['so_count'] === 1 ? '' : 's' }} &middot;
                            {{ $customer['item_count'] }} item{{ $customer['item_count'] === 1 ? '' : 's' }}
                        </small>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Undelivered</div>
                        <span class="badge bg-warning fs-6">{{ number_format($customer['total_balance']) }}</span>
                    </div>
                </div>
                <div class="collapse" id="cust-{{ $customer['customer_id'] }}">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Ordered</th>
                                    <th class="text-end">Delivered</th>
                                    <th class="text-end">Undelivered</th>
                                    <th>Sales Orders with a balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customer['items'] as $item)
                                    <tr>
                                        <td class="fw-semibold">{{ $item['generic_label'] }}</td>
                                        <td class="text-end">{{ number_format($item['ordered']) }}</td>
                                        <td class="text-end">{{ number_format($item['delivered']) }}</td>
                                        <td class="text-end"><span class="badge bg-warning">{{ number_format($item['balance']) }}</span></td>
                                        <td>
                                            @foreach($item['orders'] as $order)
                                                <div class="small">
                                                    <a href="{{ route('sales-orders.show', $order['so_id']) }}">{{ $order['so_no'] }}</a>
                                                    <span class="text-muted">
                                                        {{ \Illuminate\Support\Carbon::parse($order['order_date'])->format('M d, Y') }}
                                                        @if($order['product']) &middot; {{ $order['product'] }} @endif
                                                    </span>
                                                    &mdash; {{ number_format($order['delivered']) }} of {{ number_format($order['ordered']) }} delivered,
                                                    <strong>{{ number_format($order['balance']) }} left</strong>
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
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
