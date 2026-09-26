@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Sales Orders — ' . $customer->customer_name)

@section('content')
@php
    $badge = ['ARCHIVED' => 'bg-dark', 'COMPLETED' => 'bg-success', 'CANCELLED' => 'bg-danger', 'OPEN' => 'bg-warning'];
@endphp
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <a href="{{ route('so-summary.index') }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="bx bx-arrow-back"></i> Sales Order Summary</a>
            <h4 class="mb-1">Sales Orders &mdash; {{ $customer->customer_name }}</h4>
            <p class="text-muted small mb-0">Every Sales Order of this customer, including the cancelled and archived ones.</p>
        </div>
        <form method="GET" action="{{ route('so-summary.orders') }}" class="d-flex gap-2">
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Search by S.O / P.O" style="min-width: 220px;">
            <button type="submit" class="btn btn-outline-secondary text-nowrap">Search</button>
            @if($search !== '')
                <a href="{{ route('so-summary.orders', ['customer_id' => $customer->id]) }}" class="btn btn-outline-secondary text-nowrap">Clear</a>
            @endif
        </form>
    </div>

    <div class="card">
        <h5 class="card-header">Sales Orders ({{ $orders->count() }})</h5>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>S.O. No.</th>
                        <th>Customer</th>
                        <th>P.O. No.</th>
                        <th>Order Date</th>
                        <th>Notes</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php $status = \App\Services\SalesOrderSummaryService::statusLabel($order); @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><a href="{{ route('sales-orders.show', $order) }}">{{ $order->so_no }}</a></td>
                            <td>{{ $order->customer->customer_name ?? '' }}</td>
                            <td>{{ $order->po_no ?: '—' }}</td>
                            <td>{{ $order->order_date?->format('M d, Y') }}</td>
                            <td class="text-muted">{{ \Illuminate\Support\Str::limit((string) $order->notes, 40) ?: '—' }}</td>
                            <td><span class="badge {{ $badge[$status] }}">{{ $status }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('sales-orders.show', $order) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                <a href="{{ route('so-summary.deliveries', ['sales_order_id' => $order->id]) }}" class="btn btn-sm btn-outline-secondary" title="Delivery Receipts of this Sales Order">Deliveries</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No Sales Orders{{ $search !== '' ? ' for this search' : '' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
