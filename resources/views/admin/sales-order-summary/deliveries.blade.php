@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Delivery Receipts')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            @if($customer)
                <a href="{{ route('so-summary.items', ['customer_id' => $customer->id, 'scope' => 'ordered']) }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="bx bx-arrow-back"></i> Back to items</a>
            @else
                <a href="{{ route('so-summary.index') }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="bx bx-arrow-back"></i> Sales Order Summary</a>
            @endif
            <h4 class="mb-1">Delivery Receipts</h4>
            <div class="small text-muted">
                @php
                    $what = $genericName ? '<strong>' . e($genericName->generic_name . ($genericName->unit ? ' (' . $genericName->unit . ')' : '')) . '</strong>' : null;
                    $where = match (true) {
                        (bool) $salesOrder => 'on <strong>' . e($salesOrder->so_no) . '</strong>' . ($salesOrder->po_no ? ' (P.O. ' . e($salesOrder->po_no) . ')' : ''),
                        (bool) $customer => 'for <strong>' . e($customer->customer_name) . '</strong>, across all of their P.O.s',
                        default => 'across all customers',
                    };
                @endphp
                What was actually delivered {!! $what ? 'of ' . $what . ' ' : '' !!}{!! $where !!}. Draft and cancelled Delivery Receipts are not counted.
                @if($genericName && ($customer || $salesOrder))
                    <a href="{{ route('so-summary.deliveries', ['generic_name_id' => $genericName->id]) }}">Trace this item for all customers</a>
                @endif
            </div>
        </div>
        <form method="GET" action="{{ route('so-summary.deliveries') }}" class="d-flex gap-2">
            <input type="hidden" name="customer_id" value="{{ $customer?->id }}">
            <input type="hidden" name="sales_order_id" value="{{ $salesOrder?->id }}">
            <input type="hidden" name="generic_name_id" value="{{ $genericName?->id }}">
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Search by customer or D.R" style="min-width: 240px;">
            <button type="submit" class="btn btn-outline-secondary text-nowrap">Search</button>
        </form>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" style="font-size: .8125rem;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Delivery Date</th>
                        <th>Reference</th>
                        <th>Sales Order No.</th>
                        <th>P.O No.</th>
                        <th>Customer</th>
                        <th>Generic Item</th>
                        <th>Item Description</th>
                        <th class="text-end">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($row->receipt_date)->format('M d, Y') }}</td>
                            <td><a href="{{ route('delivery-receipts.show', $row->dr_id) }}">{{ $row->dr_no }}</a></td>
                            <td><a href="{{ route('sales-orders.show', $row->so_id) }}">{{ $row->so_no }}</a></td>
                            <td>{{ $row->po_no ?: '—' }}</td>
                            <td class="fw-semibold">{{ $row->customer_name }}</td>
                            <td>{{ $row->generic_name }}{{ $row->unit ? ' (' . $row->unit . ')' : '' }}</td>
                            <td>{{ $row->product_description ?: ($row->product_brand ?: $row->product_name) }}</td>
                            <td class="text-end fw-bold">{{ number_format($row->qty) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No deliveries found.</td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="8" class="text-end">Total delivered</td>
                            <td class="text-end">{{ number_format($rows->sum('qty')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
