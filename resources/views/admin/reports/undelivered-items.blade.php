@extends('layout.app')

@section('title', 'Undelivered Items per Customer')

@section('content')

<style>
    /* Compact customer cards; the details open in a modal that shows the printable sheet. */
    .undelivered-card { cursor: pointer; }
    .undelivered-card .customer-name { white-space: normal; overflow-wrap: anywhere; line-height: 1.3; }
    .undelivered-card:hover { box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .08); }

    /* The sheet takes the same shared print styles as the other documents' prints. */
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

    /* Print: only the sheet of the customer whose modal is open (copied into #undeliveredPrintHost). */
    #undeliveredPrintHost { display: none; }

    @media print {
        @page { size: A4 landscape; margin: 10mm; }

        body.printing-undelivered > *:not(#undeliveredPrintHost) { display: none !important; }
        body.printing-undelivered #undeliveredPrintHost { display: block !important; }
        .print-items-table tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="mb-3">
        <h4 class="mb-1">Undelivered Items per Customer</h4>
        <p class="text-muted mb-0 small">
            What is still owed on each customer's Sales Orders (ordered minus delivered), added up across all of
            their Sales Orders. Quantities only. Draft, cancelled and archived Sales Orders are not included.
            Click a customer to open its sheet, which you can print. On hand is the Warehouse stock.
        </p>
    </div>

    <!-- Filters -->
    <div class="card mb-3 no-print">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('admin.reports.undelivered-items') }}" class="d-flex flex-wrap align-items-end gap-3">
                <div style="flex: 1 1 260px; max-width: 380px;">
                    <label for="customer_id" class="form-label mb-1">Customer</label>
                    <select name="customer_id" id="customer_id" class="form-select">
                        <option value="">All customers</option>
                        @foreach($customerOptions as $option)
                            <option value="{{ $option->id }}" {{ (int) $filters['customer_id'] === $option->id ? 'selected' : '' }}>{{ $option->customer_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex: 0 1 160px;">
                    <label for="start_date" class="form-label mb-1">SO date from</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $filters['start_date'] }}">
                </div>
                <div style="flex: 0 1 160px;">
                    <label for="end_date" class="form-label mb-1">To</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $filters['end_date'] }}">
                </div>
                <div class="d-flex gap-2 flex-nowrap">
                    <button type="submit" class="btn btn-primary text-nowrap">Apply Filter</button>
                    <a href="{{ route('admin.reports.undelivered-items') }}" class="btn btn-outline-secondary text-nowrap" title="Clear all filters">
                        <i class="bx bx-refresh"></i> Clear
                    </a>
                    <a href="{{ route('admin.reports.undelivered-items.export', request()->query()) }}" class="btn btn-outline-secondary text-nowrap" title="Download all as Excel">
                        <i class="bx bx-download"></i> Excel
                    </a>
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
        <!-- One compact card per customer, side by side; a click opens the details in the modal below -->
        <div class="row g-3 undelivered-grid">
            @foreach($customers as $customer)
                <div class="col-xl-4 col-md-6">
                    <div class="card undelivered-card h-100" role="button"
                         data-bs-toggle="modal" data-bs-target="#undeliveredModal"
                         data-customer="{{ $customer['customer_name'] }}" data-customer-id="{{ $customer['customer_id'] }}" data-details="details-{{ $customer['customer_id'] }}">
                        <div class="card-body py-3">
                            <div class="fw-semibold customer-name mb-2">{{ $customer['customer_name'] }}</div>
                            <div class="d-flex justify-content-between align-items-end">
                                <small class="text-muted">
                                    {{ $customer['so_count'] }} SO{{ $customer['so_count'] === 1 ? '' : 's' }} &middot;
                                    {{ $customer['item_count'] }} item{{ $customer['item_count'] === 1 ? '' : 's' }}
                                </small>
                                <div class="text-end">
                                    <div class="text-muted" style="font-size: .7rem;">Undelivered</div>
                                    <span class="badge bg-warning fs-6" title="Total undelivered quantity">{{ number_format($customer['total_balance']) }}</span>
                                </div>
                            </div>
                        </div>

                        <template id="details-{{ $customer['customer_id'] }}">
                            @include('admin.reports.partials.undelivered-sheet', ['customer' => $customer, 'period' => $period])
                        </template>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- The clicked customer's sheet: what you see here is what gets printed -->
        <div class="modal fade" id="undeliveredModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title mb-0" id="undeliveredModalTitle">Undelivered Items</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="undeliveredModalBody"></div>
                    <div class="modal-footer py-2">
                        <a href="#" class="btn btn-outline-secondary text-nowrap" id="undeliveredExcelLink" title="Download this customer as Excel">
                            <i class="bx bx-download"></i> Excel
                        </a>
                        <button type="button" class="btn btn-primary text-nowrap" id="undeliveredPrintBtn">
                            <i class="bx bx-printer"></i> Print
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    const undeliveredModal = document.getElementById('undeliveredModal');
    const exportBase = @json(route('admin.reports.undelivered-items.export'));
    const filterQuery = @json(request()->only(['start_date', 'end_date']));

    // Show the clicked customer's sheet (kept inert in a <template> inside its card).
    undeliveredModal?.addEventListener('show.bs.modal', (event) => {
        const card = event.relatedTarget;
        document.getElementById('undeliveredModalTitle').textContent = card.getAttribute('data-customer');
        const template = document.getElementById(card.getAttribute('data-details'));
        document.getElementById('undeliveredModalBody').replaceChildren(template.content.cloneNode(true));

        const params = new URLSearchParams({ ...filterQuery, customer_id: card.getAttribute('data-customer-id') });
        document.getElementById('undeliveredExcelLink').href = exportBase + '?' + params.toString();
    });

    // Print only this customer's sheet: copy it to a body-level host, hide everything else, print.
    document.getElementById('undeliveredPrintBtn')?.addEventListener('click', () => {
        let host = document.getElementById('undeliveredPrintHost');
        if (!host) {
            host = document.createElement('div');
            host.id = 'undeliveredPrintHost';
            document.body.appendChild(host);
        }
        host.innerHTML = document.getElementById('undeliveredModalBody').innerHTML;
        document.body.classList.add('printing-undelivered');
        window.print();
    });
    window.addEventListener('afterprint', () => document.body.classList.remove('printing-undelivered'));
</script>
@endsection
