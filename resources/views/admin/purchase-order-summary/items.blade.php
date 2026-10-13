@extends('layout.app')

@section('title', 'Purchase Order Summary — ' . $supplier->supplier_name)

@section('content')
@php
    $isPending = $scope === 'pending';
@endphp

<style>
    @include('partials.print.base-print')

    .pending-sheet { font-size: 0.7rem; }
    .pending-sheet .print-company-detail { font-size: 0.62rem; }
    .pending-sheet .print-doc-title { font-size: 1.3rem; }
    .pending-sheet .print-doc-page,
    .pending-sheet .print-doc-no-row,
    .pending-sheet .print-to-row { font-size: 0.66rem; }
    .pending-sheet .print-to-header { font-size: 0.7rem; }
    .pending-sheet .print-items-table th,
    .pending-sheet .print-items-table td { font-size: 0.66rem; padding: 0.2rem 0.35rem; }
    .pending-sheet .print-sig-label { font-size: 0.7rem; }
    .pending-sheet .print-sig-value { font-size: 0.66rem; }
    .pending-sheet .print-items-table tfoot td { background-color: #f5f5f5; }

    .po-detail { font-size: .8125rem; }
    .po-detail th, .po-detail td { padding: .5rem .55rem; vertical-align: top; }
    .po-detail th { font-size: .7rem; }
    .po-detail .col-supplier { width: 17%; }
    .po-detail .col-desc { width: 17%; }
    .po-detail .supplier-cell { font-weight: 400; font-size: .75rem; line-height: 1.3; }
    .po-detail .nowrap { white-space: nowrap; }

    #poSummaryPrintHost { display: none; }

    @media print {
        @page { size: A4 landscape; margin: 10mm; }

        body.printing-po-summary > *:not(#poSummaryPrintHost) { display: none !important; }
        body.printing-po-summary #poSummaryPrintHost { display: block !important; }
        .print-items-table tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <a href="{{ route('po-summary.index') }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="bx bx-arrow-back"></i> Purchase Order Summary</a>
            <h4 class="mb-1">Purchase Order Summary &mdash; {{ $supplier->supplier_name }}</h4>
            <p class="text-muted small mb-0">
                {{ $isPending
                    ? 'What this supplier\'s open Purchase Orders still owe, item by item.'
                    : 'Everything ordered from this supplier, item by item.' }}
                Click an item to trace all of its Goods Receipts; click a Received number to see the Goods Receipts of that P.O. On hand is split Warehouse / POS.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form method="GET" action="{{ route('po-summary.items') }}" class="d-flex gap-2">
                <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
                <input type="hidden" name="scope" value="{{ $scope }}">
                <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Search by P.O / Generic / Item" style="min-width: 240px;">
                <button type="submit" class="btn btn-outline-secondary text-nowrap">Search</button>
                @if($search !== '')
                    <a href="{{ route('po-summary.items', ['supplier_id' => $supplier->id, 'scope' => $scope]) }}" class="btn btn-outline-secondary text-nowrap">Clear</a>
                @endif
            </form>
            <a href="{{ route('po-summary.items.export', request()->query()) }}" class="btn btn-outline-secondary text-nowrap"><i class="bx bx-download"></i> Excel</a>
            @if($data)
                <button type="button" class="btn btn-primary text-nowrap" id="poSummaryPrintBtn"><i class="bx bx-printer"></i> Print</button>
            @endif
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $isPending ? 'active' : '' }}" href="{{ route('po-summary.items', ['supplier_id' => $supplier->id, 'scope' => 'pending', 'search' => $search ?: null]) }}">Pending</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ ! $isPending ? 'active' : '' }}" href="{{ route('po-summary.items', ['supplier_id' => $supplier->id, 'scope' => 'ordered', 'search' => $search ?: null]) }}">Qty Ordered</a>
        </li>
    </ul>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 po-detail">
                <thead>
                    <tr>
                        <th class="col-supplier">Supplier</th>
                        <th>PO Ref #</th>
                        <th>PO Date</th>
                        <th>Generic Item</th>
                        <th class="col-desc">Item Description</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Ordered</th>
                        <th class="text-end">Received</th>
                        <th class="text-end">Balance</th>
                        <th class="text-end">On Hand (Warehouse)</th>
                        <th class="text-end">On Hand (POS)</th>
                    </tr>
                </thead>
                <tbody>
                    @if($data)
                        @foreach($data['items'] as $item)
                            @foreach($item['orders'] as $order)
                                <tr>
                                    <td class="supplier-cell">{{ $data['supplier_name'] }}</td>
                                    <td class="nowrap"><a href="{{ route('purchase-orders.show', $order['po_id']) }}">{{ $order['po_no'] }}</a></td>
                                    <td class="nowrap">{{ \Illuminate\Support\Carbon::parse($order['order_date'])->format('M d, Y') }}</td>
                                    <td>
                                        <a href="{{ route('po-summary.receipts', ['supplier_id' => $supplier->id, 'generic_name_id' => $item['generic_name_id']]) }}" title="All Goods Receipts of this item">{{ $item['generic_label'] }}</a>
                                    </td>
                                    <td>{{ $order['product'] }}</td>
                                    <td class="text-end nowrap">{{ $order['unit_cost'] !== null ? number_format($order['unit_cost'], 2) : '' }}</td>
                                    <td class="text-end nowrap">{{ number_format($order['ordered']) }}</td>
                                    <td class="text-end nowrap">
                                        @if($order['received'] > 0)
                                            <a href="{{ route('po-summary.receipts', ['purchase_order_id' => $order['po_id'], 'generic_name_id' => $item['generic_name_id']]) }}" title="Goods Receipts of this P.O.">{{ number_format($order['received']) }}</a>
                                        @else
                                            0
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold nowrap">{{ number_format($order['balance']) }}</td>
                                    <td class="text-end nowrap">{{ number_format($order['on_hand']['warehouse']) }}</td>
                                    <td class="text-end nowrap">{{ number_format($order['on_hand']['pos']) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    @else
                        <tr><td colspan="11" class="text-center text-muted py-4">
                            {{ $isPending ? 'Nothing is waiting to be received' : 'No ordered items' }}{{ $search !== '' ? ' for this search' : '' }}.
                        </td></tr>
                    @endif
                </tbody>
                @if($data)
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="6" class="text-end">Total</td>
                            <td class="text-end">{{ number_format($data['total_ordered']) }}</td>
                            <td class="text-end">{{ number_format($data['total_received']) }}</td>
                            <td class="text-end">{{ number_format($data['total_balance']) }}</td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    @if($data)
        <template id="poSummarySheet">
            @include('admin.purchase-order-summary.partials.sheet', ['supplier' => $data, 'period' => $covers])
        </template>
    @endif
</div>
@endsection

@section('scripts')
<script>
    document.getElementById('poSummaryPrintBtn')?.addEventListener('click', () => {
        let host = document.getElementById('poSummaryPrintHost');
        if (!host) {
            host = document.createElement('div');
            host.id = 'poSummaryPrintHost';
            document.body.appendChild(host);
        }
        host.replaceChildren(document.getElementById('poSummarySheet').content.cloneNode(true));

        const pending = [...host.querySelectorAll('img')]
            .filter(img => ! img.complete)
            .map(img => new Promise(resolve => { img.onload = img.onerror = resolve; }));

        Promise.race([Promise.all(pending), new Promise(resolve => setTimeout(resolve, 1500))]).then(() => {
            document.body.classList.add('printing-po-summary');
            window.print();
        });
    });
    window.addEventListener('afterprint', () => document.body.classList.remove('printing-po-summary'));
</script>
@endsection
