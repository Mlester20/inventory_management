@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Sales Order Summary — ' . $customer->customer_name)

@section('content')
@php
    $isUndelivered = $scope === 'undelivered';
@endphp

<style>
    /* The formal sheet is what Print produces; on screen it stays inert in a <template>. */
    @include('partials.print.base-print')

    .undelivered-sheet { font-size: 0.7rem; }
    .undelivered-sheet .print-company-detail { font-size: 0.62rem; }
    .undelivered-sheet .print-doc-title { font-size: 1.3rem; }
    .undelivered-sheet .print-doc-page,
    .undelivered-sheet .print-doc-no-row,
    .undelivered-sheet .print-to-row { font-size: 0.66rem; }
    .undelivered-sheet .print-to-header { font-size: 0.7rem; }
    .undelivered-sheet .print-items-table th,
    .undelivered-sheet .print-items-table td { font-size: 0.66rem; padding: 0.2rem 0.35rem; }
    .undelivered-sheet .print-sig-label { font-size: 0.7rem; }
    .undelivered-sheet .print-sig-value { font-size: 0.66rem; }
    .undelivered-sheet .print-items-table tfoot td { background-color: #f5f5f5; }

    /* The detail table is ten columns wide next to the sidebar: keep it tight so nothing needs side-scrolling. */
    .so-detail { font-size: .8125rem; }
    .so-detail th, .so-detail td { padding: .5rem .55rem; vertical-align: top; }
    .so-detail th { font-size: .7rem; }
    .so-detail .col-customer { width: 12%; }
    .so-detail .col-desc { width: 20%; }
    .so-detail .nowrap { white-space: nowrap; }

    #soSummaryPrintHost { display: none; }

    @media print {
        @page { size: A4 landscape; margin: 10mm; }

        body.printing-so-summary > *:not(#soSummaryPrintHost) { display: none !important; }
        body.printing-so-summary #soSummaryPrintHost { display: block !important; }
        .print-items-table tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <a href="{{ route('so-summary.index') }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="bx bx-arrow-back"></i> Sales Order Summary</a>
            <h4 class="mb-1">Sales Order Summary &mdash; {{ $customer->customer_name }}</h4>
            <p class="text-muted small mb-0">
                {{ $isUndelivered
                    ? 'What this customer\'s open Sales Orders still owe, item by item.'
                    : 'Everything this customer ordered on Sales Orders that are not cancelled or archived, item by item.' }}
                Click an item to trace all of its Delivery Receipts; click a Delivered number to see the Delivery Receipts of that P.O. On hand is the Warehouse stock.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form method="GET" action="{{ route('so-summary.items') }}" class="d-flex gap-2">
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                <input type="hidden" name="scope" value="{{ $scope }}">
                <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Search by P.O / Generic / Item" style="min-width: 240px;">
                <button type="submit" class="btn btn-outline-secondary text-nowrap">Search</button>
                @if($search !== '')
                    <a href="{{ route('so-summary.items', ['customer_id' => $customer->id, 'scope' => $scope]) }}" class="btn btn-outline-secondary text-nowrap">Clear</a>
                @endif
            </form>
            <a href="{{ route('so-summary.items.export', request()->query()) }}" class="btn btn-outline-secondary text-nowrap"><i class="bx bx-download"></i> Excel</a>
            @if($data)
                <button type="button" class="btn btn-primary text-nowrap" id="soSummaryPrintBtn"><i class="bx bx-printer"></i> Print</button>
            @endif
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $isUndelivered ? 'active' : '' }}" href="{{ route('so-summary.items', ['customer_id' => $customer->id, 'scope' => 'undelivered', 'search' => $search ?: null]) }}">Undelivered</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ ! $isUndelivered ? 'active' : '' }}" href="{{ route('so-summary.items', ['customer_id' => $customer->id, 'scope' => 'ordered', 'search' => $search ?: null]) }}">Qty Ordered</a>
        </li>
    </ul>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 so-detail">
                <thead>
                    <tr>
                        <th class="col-customer">Customer</th>
                        <th>PO Ref #</th>
                        <th>PO Date</th>
                        <th>Generic Item</th>
                        <th class="col-desc">Item Description</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Ordered</th>
                        <th class="text-end">Delivered</th>
                        <th class="text-end">Balance</th>
                        <th class="text-end">On Hand</th>
                    </tr>
                </thead>
                <tbody>
                    @if($data)
                        @foreach($data['items'] as $item)
                            @foreach($item['orders'] as $order)
                                <tr>
                                    <td class="fw-semibold">{{ $data['customer_name'] }}</td>
                                    <td class="nowrap"><a href="{{ route('sales-orders.show', $order['so_id']) }}">{{ $order['po_no'] ?: $order['so_no'] }}</a></td>
                                    <td class="nowrap">{{ \Illuminate\Support\Carbon::parse($order['order_date'])->format('M d, Y') }}</td>
                                    <td>
                                        <a href="{{ route('so-summary.deliveries', ['customer_id' => $customer->id, 'generic_name_id' => $item['generic_name_id']]) }}" title="All Delivery Receipts of this item">{{ $item['generic_label'] }}</a>
                                    </td>
                                    <td>{{ $order['product'] }}</td>
                                    <td class="text-end nowrap">{{ $order['price'] !== null ? number_format($order['price'], 2) : '' }}</td>
                                    <td class="text-end nowrap">{{ number_format($order['ordered']) }}</td>
                                    <td class="text-end nowrap">
                                        @if($order['delivered'] > 0)
                                            <a href="{{ route('so-summary.deliveries', ['sales_order_id' => $order['so_id'], 'generic_name_id' => $item['generic_name_id']]) }}" title="Delivery Receipts of this P.O.">{{ number_format($order['delivered']) }}</a>
                                        @else
                                            0
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold nowrap">{{ number_format($order['balance']) }}</td>
                                    <td class="text-end nowrap">{{ number_format($order['on_hand']) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    @else
                        <tr><td colspan="10" class="text-center text-muted py-4">
                            {{ $isUndelivered ? 'Nothing is waiting to be delivered' : 'No ordered items' }}{{ $search !== '' ? ' for this search' : '' }}.
                        </td></tr>
                    @endif
                </tbody>
                @if($data)
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="6" class="text-end">Total</td>
                            <td class="text-end">{{ number_format($data['total_ordered']) }}</td>
                            <td class="text-end">{{ number_format($data['total_delivered']) }}</td>
                            <td class="text-end">{{ number_format($data['total_balance']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    @if($data)
        <template id="soSummarySheet">
            @include('admin.sales-order-summary.partials.sheet', ['customer' => $data, 'period' => $covers])
        </template>
    @endif
</div>
@endsection

@section('scripts')
<script>
    // Print only the formal sheet: copy it to a body-level host, hide everything else, print.
    document.getElementById('soSummaryPrintBtn')?.addEventListener('click', () => {
        let host = document.getElementById('soSummaryPrintHost');
        if (!host) {
            host = document.createElement('div');
            host.id = 'soSummaryPrintHost';
            document.body.appendChild(host);
        }
        host.replaceChildren(document.getElementById('soSummarySheet').content.cloneNode(true));

        // The sheet comes out of a <template>, so its letterhead logo has not loaded yet: wait for it,
        // otherwise the print preview opens without the logo.
        const pending = [...host.querySelectorAll('img')]
            .filter(img => ! img.complete)
            .map(img => new Promise(resolve => { img.onload = img.onerror = resolve; }));

        // ...but never wait long: a logo that will not load must not block printing.
        Promise.race([Promise.all(pending), new Promise(resolve => setTimeout(resolve, 1500))]).then(() => {
            document.body.classList.add('printing-so-summary');
            window.print();
        });
    });
    window.addEventListener('afterprint', () => document.body.classList.remove('printing-so-summary'));
</script>
@endsection
