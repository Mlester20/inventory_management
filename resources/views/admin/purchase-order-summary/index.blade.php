@extends('layout.app')

@section('title', 'Purchase Order Summary')

@section('content')
<style>
    @include('partials.print.base-print')

    .po-supplier-sheet { font-size: 0.7rem; }
    .po-supplier-sheet .print-company-detail { font-size: 0.62rem; }
    .po-supplier-sheet .print-doc-title { font-size: 1.3rem; }
    .po-supplier-sheet .print-doc-page,
    .po-supplier-sheet .print-doc-no-row,
    .po-supplier-sheet .print-to-row { font-size: 0.66rem; }
    .po-supplier-sheet .print-to-header { font-size: 0.7rem; }
    .po-supplier-sheet .print-items-table th,
    .po-supplier-sheet .print-items-table td { font-size: 0.66rem; padding: 0.2rem 0.35rem; }
    .po-supplier-sheet .print-sig-label { font-size: 0.7rem; }
    .po-supplier-sheet .print-sig-value { font-size: 0.66rem; }
    .po-supplier-sheet .print-items-table tfoot td { background-color: #f5f5f5; }

    #poSummaryPrintHost { display: none; }

    @media print {
        @page { size: portrait; margin: 12mm; }

        body.printing-po-summary > *:not(#poSummaryPrintHost) { display: none !important; }
        body.printing-po-summary #poSummaryPrintHost { display: block !important; }
        .print-items-table tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h4 class="mb-1">Purchase Order Summary</h4>
            <p class="text-muted small mb-0">
                Each supplier's Purchase Orders by status. Qty Ordered is every line on a non-draft Purchase Order;
                Pending is what open/partially-received Purchase Orders still owe. Click a number to see what is behind it.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form method="GET" action="{{ route('po-summary.index') }}" class="d-flex gap-2">
                <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Search by supplier" style="min-width: 220px;">
                <button type="submit" class="btn btn-outline-secondary text-nowrap">Search</button>
                @if($search !== '')
                    <a href="{{ route('po-summary.index') }}" class="btn btn-outline-secondary text-nowrap">Clear</a>
                @endif
            </form>
            @if($suppliers->isNotEmpty())
                <button type="button" class="btn btn-primary text-nowrap" id="poSummaryPrintBtn"><i class="bx bx-printer"></i> Print</button>
            @endif
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link active" href="{{ route('po-summary.index') }}">P.O Summary</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('po-summary.all-items') }}">P.O Summary — All Suppliers</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('po-summary.by-item') }}">P.O Summary — By Item</a>
        </li>
    </ul>

    <div class="card">
        <h5 class="card-header">Purchase Orders</h5>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th class="text-center">P.O Count</th>
                        <th class="text-center">Completed</th>
                        <th class="text-center">Partially Received</th>
                        <th class="text-center">Open</th>
                        <th class="text-center">Qty Ordered</th>
                        <th class="text-center">Pending</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['supplier_name'] }}</td>
                            <td class="text-center">
                                <a href="{{ route('po-summary.orders', ['supplier_id' => $row['supplier_id']]) }}" title="See the {{ $row['po_count'] }} Purchase Orders">{{ number_format($row['po_count']) }}</a>
                            </td>
                            <td class="text-center">{{ number_format($row['completed']) }}</td>
                            <td class="text-center">{{ number_format($row['partially_received']) }}</td>
                            <td class="text-center">{{ number_format($row['open']) }}</td>
                            <td class="text-center">
                                @if($row['qty_ordered'] > 0)
                                    <a href="{{ route('po-summary.items', ['supplier_id' => $row['supplier_id'], 'scope' => 'ordered']) }}" title="Every ordered item">{{ number_format($row['qty_ordered']) }}</a>
                                @else
                                    0
                                @endif
                            </td>
                            <td class="text-center">
                                @if($row['pending'] > 0)
                                    <a href="{{ route('po-summary.items', ['supplier_id' => $row['supplier_id'], 'scope' => 'pending']) }}" class="fw-semibold" title="Items still to receive">{{ number_format($row['pending']) }}</a>
                                @else
                                    0
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No Purchase Orders{{ $search !== '' ? ' for this search' : '' }}.</td></tr>
                    @endforelse
                </tbody>
                @if($suppliers->isNotEmpty())
                    <tfoot>
                        <tr class="fw-bold">
                            <td>Total</td>
                            <td class="text-center">{{ number_format($suppliers->sum('po_count')) }}</td>
                            <td class="text-center">{{ number_format($suppliers->sum('completed')) }}</td>
                            <td class="text-center">{{ number_format($suppliers->sum('partially_received')) }}</td>
                            <td class="text-center">{{ number_format($suppliers->sum('open')) }}</td>
                            <td class="text-center">{{ number_format($suppliers->sum('qty_ordered')) }}</td>
                            <td class="text-center">{{ number_format($suppliers->sum('pending')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    @if($suppliers->isNotEmpty())
        <template id="poSummarySheet">
            @include('admin.purchase-order-summary.partials.supplier-summary-sheet', ['suppliers' => $suppliers])
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
