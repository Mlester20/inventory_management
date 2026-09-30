@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Sales Order Summary')

@section('content')
<style>
    @include('partials.print.base-print')

    .so-customer-sheet { font-size: 0.7rem; }
    .so-customer-sheet .print-company-detail { font-size: 0.62rem; }
    .so-customer-sheet .print-doc-title { font-size: 1.3rem; }
    .so-customer-sheet .print-doc-page,
    .so-customer-sheet .print-doc-no-row,
    .so-customer-sheet .print-to-row { font-size: 0.66rem; }
    .so-customer-sheet .print-to-header { font-size: 0.7rem; }
    .so-customer-sheet .print-items-table th,
    .so-customer-sheet .print-items-table td { font-size: 0.66rem; padding: 0.2rem 0.35rem; }
    .so-customer-sheet .print-sig-label { font-size: 0.7rem; }
    .so-customer-sheet .print-sig-value { font-size: 0.66rem; }
    .so-customer-sheet .print-items-table tfoot td { background-color: #f5f5f5; }

    #soSummaryPrintHost { display: none; }

    @media print {
        @page { size: portrait; margin: 12mm; }

        body.printing-so-summary > *:not(#soSummaryPrintHost) { display: none !important; }
        body.printing-so-summary #soSummaryPrintHost { display: block !important; }
        .print-items-table tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h4 class="mb-1">Sales Order Summary</h4>
            <p class="text-muted small mb-0">
                Each customer's Sales Orders by status. Archived Sales Orders still count in the S.O count; Qty Ordered leaves out
                cancelled and archived ones, and Undelivered is what open Sales Orders still owe. Click a number to see what is behind it.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form method="GET" action="{{ route('so-summary.index') }}" class="d-flex gap-2">
                <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Search by customer" style="min-width: 220px;">
                <button type="submit" class="btn btn-outline-secondary text-nowrap">Search</button>
                @if($search !== '')
                    <a href="{{ route('so-summary.index') }}" class="btn btn-outline-secondary text-nowrap">Clear</a>
                @endif
            </form>
            @if($customers->isNotEmpty())
                <button type="button" class="btn btn-primary text-nowrap" id="soSummaryPrintBtn"><i class="bx bx-printer"></i> Print</button>
            @endif
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link active" href="{{ route('so-summary.index') }}">S.O Summary</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('so-summary.all-items') }}">S.O Summary — All Customers</a>
        </li>
    </ul>

    <div class="card">
        <h5 class="card-header">Sales Orders</h5>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th class="text-center">S.O Count</th>
                        <th class="text-center">Invoiced / Completed</th>
                        <th class="text-center">Open</th>
                        <th class="text-center">Cancelled</th>
                        <th class="text-center">Archived</th>
                        <th class="text-center">Qty Ordered</th>
                        <th class="text-center">Undelivered</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['customer_name'] }}</td>
                            <td class="text-center">
                                <a href="{{ route('so-summary.orders', ['customer_id' => $row['customer_id']]) }}" title="See the {{ $row['so_count'] }} Sales Orders">{{ number_format($row['so_count']) }}</a>
                            </td>
                            <td class="text-center">{{ number_format($row['completed']) }}</td>
                            <td class="text-center">{{ number_format($row['open']) }}</td>
                            <td class="text-center">{{ number_format($row['cancelled']) }}</td>
                            <td class="text-center">{{ number_format($row['archived']) }}</td>
                            <td class="text-center">
                                @if($row['qty_ordered'] > 0)
                                    <a href="{{ route('so-summary.items', ['customer_id' => $row['customer_id'], 'scope' => 'ordered']) }}" title="Every ordered item">{{ number_format($row['qty_ordered']) }}</a>
                                @else
                                    0
                                @endif
                            </td>
                            <td class="text-center">
                                @if($row['undelivered'] > 0)
                                    <a href="{{ route('so-summary.items', ['customer_id' => $row['customer_id'], 'scope' => 'undelivered']) }}" class="fw-semibold" title="Items still to deliver">{{ number_format($row['undelivered']) }}</a>
                                @else
                                    0
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No Sales Orders{{ $search !== '' ? ' for this search' : '' }}.</td></tr>
                    @endforelse
                </tbody>
                @if($customers->isNotEmpty())
                    <tfoot>
                        <tr class="fw-bold">
                            <td>Total</td>
                            <td class="text-center">{{ number_format($customers->sum('so_count')) }}</td>
                            <td class="text-center">{{ number_format($customers->sum('completed')) }}</td>
                            <td class="text-center">{{ number_format($customers->sum('open')) }}</td>
                            <td class="text-center">{{ number_format($customers->sum('cancelled')) }}</td>
                            <td class="text-center">{{ number_format($customers->sum('archived')) }}</td>
                            <td class="text-center">{{ number_format($customers->sum('qty_ordered')) }}</td>
                            <td class="text-center">{{ number_format($customers->sum('undelivered')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    @if($customers->isNotEmpty())
        <template id="soSummarySheet">
            @include('admin.sales-order-summary.partials.customer-summary-sheet', ['customers' => $customers])
        </template>
    @endif
</div>
@endsection

@section('scripts')
<script>
    document.getElementById('soSummaryPrintBtn')?.addEventListener('click', () => {
        let host = document.getElementById('soSummaryPrintHost');
        if (!host) {
            host = document.createElement('div');
            host.id = 'soSummaryPrintHost';
            document.body.appendChild(host);
        }
        host.replaceChildren(document.getElementById('soSummarySheet').content.cloneNode(true));

        const pending = [...host.querySelectorAll('img')]
            .filter(img => ! img.complete)
            .map(img => new Promise(resolve => { img.onload = img.onerror = resolve; }));

        Promise.race([Promise.all(pending), new Promise(resolve => setTimeout(resolve, 1500))]).then(() => {
            document.body.classList.add('printing-so-summary');
            window.print();
        });
    });
    window.addEventListener('afterprint', () => document.body.classList.remove('printing-so-summary'));
</script>
@endsection
