@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Sales Order ' . $salesOrder->so_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('sales-orders.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Sales Orders
        </a>
        <div class="d-flex gap-2">
            @if($salesOrder->isDraft())
                <a href="{{ route('sales-orders.edit', $salesOrder) }}" class="btn btn-primary">
                    <i class="bx bx-edit-alt"></i> Continue Editing
                </a>
                @if(Auth::user()->role === 'admin')
                    <form action="{{ route('sales-orders.destroy', $salesOrder) }}" method="POST" onsubmit="return confirmSubmit(this, 'Delete draft {{ $salesOrder->so_no }}? This can be restored from the trash later.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bx bx-trash"></i> Delete Draft
                        </button>
                    </form>
                @endif
            @else
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#notesModal">
                    <i class="bx bx-note"></i> Add Notes
                </button>
                @if($salesOrder->status !== 'completed' && $salesOrder->status !== 'cancelled')
                    <a href="{{ route('delivery-receipts.create', ['sales_order_id' => $salesOrder->id]) }}" class="btn btn-primary">
                        <i class="bx bx-plus"></i> Create Delivery Receipt
                    </a>
                @endif
                <button type="button" class="btn btn-outline-primary" onclick="printSalesOrder()">
                    <i class="bx bx-printer"></i> Print
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="printSalesOrderList()">
                    <i class="bx bx-list-ul"></i> Print (Packing List)
                </button>
                @if($salesOrder->isArchived())
                    <form action="{{ route('sales-orders.unarchive', $salesOrder) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="bx bx-undo"></i> Unarchive
                        </button>
                    </form>
                @else
                    <form action="{{ route('sales-orders.archive', $salesOrder) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="bx bx-archive"></i> Archive
                        </button>
                    </form>
                @endif
                @if(Auth::user()->role === 'admin' && ! $salesOrder->isCancelled())
                    <form action="{{ route('sales-orders.cancel', $salesOrder) }}" method="POST" onsubmit="return confirmSubmit(this, 'Cancel/void this Sales Order? The record and its delivery history stay, only the status changes.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bx bx-block"></i> Cancel/Void
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <div id="printableSalesOrder">
    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="text-muted small">S.O. No.</label>
                    <p class="fw-bold mb-0">{{ $salesOrder->so_no }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Customer</label>
                    <p class="fw-bold mb-0">{{ $salesOrder->customer->customer_name ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Customer P.O. #</label>
                    <p class="fw-bold mb-0">{{ $salesOrder->po_no ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Status</label>
                    <p class="mb-0">
                        @if($salesOrder->isDraft())
                            <span class="badge bg-secondary">DRAFT</span>
                        @else
                            <span class="badge bg-{{ ['open' => 'warning', 'partially_delivered' => 'info', 'completed' => 'success', 'cancelled' => 'danger'][$salesOrder->status] ?? 'secondary' }}">
                                {{ ucfirst(str_replace('_', ' ', $salesOrder->status)) }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Order Date</label>
                    <p class="mb-0">{{ $salesOrder->order_date->format('M d, Y') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <h5 class="card-header">Line Items</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr class="table-header-bg">
                        <th>Generic Description</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Advance Qty</th>
                        <th class="text-end">Delivered</th>
                        <th class="text-end">Remaining</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($salesOrder->items as $item)
                        <tr>
                            <td>
                                {{ $item->genericName->generic_name ?? '—' }} ({{ $item->genericName->unit ?? '—' }})
                                @if($item->remarks)
                                    <div class="text-muted small">{{ $item->remarks }}</div>
                                @endif
                            </td>
                            <td class="text-end">{{ $item->qty ?? '—' }}</td>
                            <td class="text-end">{{ $item->price !== null ? number_format($item->price, 2) : '—' }}</td>
                            <td class="text-end">{{ $item->advance_order_qty }}</td>
                            <td class="text-end">{{ $item->delivered_qty }}</td>
                            <td class="text-end">
                                <span class="badge bg-{{ $item->remaining_qty > 0 ? 'warning' : 'success' }}">
                                    {{ $item->remaining_qty }}
                                </span>
                            </td>
                            <td class="text-end">{{ ($item->qty !== null && $item->price !== null) ? number_format($item->qty * $item->price, 2) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-info fw-bold">
                        <td colspan="6">TOTAL</td>
                        <td class="text-end">{{ number_format($salesOrder->items->sum(fn($i) => ($i->qty ?? 0) * ($i->price ?? 0)), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="border-top pt-1">Prepared By: {{ $salesOrder->preparedBy->name ?? '—' }}</div>
        </div>
    </div>
    </div>

    <div class="card mb-4 no-print">
        <h5 class="card-header">Notes</h5>
        <div class="card-body">
            @if($salesOrder->notes)
                <p class="mb-0" style="white-space: pre-line;">{{ $salesOrder->notes }}</p>
            @else
                <p class="text-muted mb-0">No notes yet.</p>
            @endif
        </div>
    </div>

    <div class="card no-print">
        <h5 class="card-header">Delivery Receipts</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr class="table-header-bg">
                        <th>D.R. No.</th>
                        <th>Receipt Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesOrder->deliveryReceipts as $deliveryReceipt)
                        <tr>
                            <td>{{ $deliveryReceipt->dr_no }}</td>
                            <td>{{ $deliveryReceipt->receipt_date->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('delivery-receipts.show', $deliveryReceipt) }}" class="btn btn-sm btn-info">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">No Delivery Receipts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade no-print" id="notesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('sales-orders.update-notes', $salesOrder) }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Notes — {{ $salesOrder->so_no }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <textarea name="notes" class="form-control" rows="5" placeholder="e.g. technical specifications, special instructions...">{{ $salesOrder->notes }}</textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Notes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card so-print-only" id="printableSalesOrderSheet">
        <div class="card-body p-4 so-sheet">

            @include('partials.print.letterhead', [
                'docTitle' => 'SALES ORDER',
                'docNoLabel' => 'S.O No.',
                'docNo' => $salesOrder->so_no,
                'docNoLabel2' => 'P.O No.',
                'docNo2' => $salesOrder->po_no ?? null,
                'docDate' => $salesOrder->order_date->format('m/d/Y'),
                'toHeader' => 'Customer',
                'toRows' => [
                    'Name' => $salesOrder->customer->customer_name ?? '',
                    'Address' => $salesOrder->customer?->delivery_address ?? '',
                ],
            ])

            <div class="table-responsive">
                <table class="table table-bordered table-sm print-items-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th>Generic Description</th>
                            <th>Remarks</th>
                            <th style="width: 7%;">Unit</th>
                            <th class="text-end" style="width: 7%;">Qty</th>
                            <th class="text-end" style="width: 10%;">Unit Cost</th>
                            <th class="text-end" style="width: 12%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($salesOrder->items as $item)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $item->genericName->generic_name ?? '—' }}</td>
                                <td>{{ $item->remarks ?? '—' }}</td>
                                <td class="text-center">{{ $item->genericName->unit ?? '—' }}</td>
                                <td class="text-end">{{ $item->qty ?? '—' }}</td>
                                <td class="text-end">{{ $item->price !== null ? number_format($item->price, 2) : '—' }}</td>
                                <td class="text-end">{{ ($item->qty !== null && $item->price !== null) ? number_format($item->qty * $item->price, 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('partials.print.sales-totals-footer', [
                'totalAmountDue' => number_format($salesOrder->items->sum(fn($i) => ($i->qty ?? 0) * ($i->price ?? 0)), 2),
                'preparedByValue' => $salesOrder->preparedBy->name ?? '',
            ])
        </div>
    </div>

    {{--
        Second print variant of this same Sales Order: a quantity-only
        "packing list" for warehouse/preparation staff (Order Qty/Advance/
        Balance/Remarks/Qty, no pricing at all) — matches the "Sales Order
        List" page in the client-approved final layout. Toggled via the
        body.print-mode-list class (see printSalesOrderList() below) so
        only one of the two print sheets is visible at print time.
    --}}
    <div class="card so-print-only" id="printableSalesOrderListSheet">
        <div class="card-body p-4 so-sheet">

            <div class="row g-0 print-list-header">
                <div class="col-3">
                    <img src="{{ asset(config('company.logo')) }}" alt="{{ config('company.name') }}" class="print-logo-full mb-2">
                </div>
                <div class="col-6">
                    <table class="table table-bordered table-sm print-list-customer-table mb-0">
                        <tr><td colspan="2" class="fw-bold">Customer</td></tr>
                        <tr>
                            <td class="fw-bold" style="width: 90px;">Name</td>
                            <td>{{ $salesOrder->customer->customer_name ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Address</td>
                            <td>{{ $salesOrder->customer?->delivery_address ?? '' }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-3 text-end">
                    <div class="print-doc-title">Sales Order List</div>
                    <div class="print-doc-page">Page {{ '___' }} of {{ '___' }}</div>
                    <div class="print-doc-no-row">
                        <span class="print-doc-no-label">S.O No.:</span>
                        <span class="print-doc-no-value">{{ $salesOrder->so_no }}</span>
                    </div>
                    <div class="print-doc-no-row">
                        <span class="print-doc-no-label">P.O No.:</span>
                        <span class="print-doc-no-value">{{ $salesOrder->po_no ?? '' }}</span>
                    </div>
                    <div class="print-doc-no-row">
                        <span class="print-doc-no-label">Date:</span>
                        <span class="print-doc-no-value">{{ $salesOrder->order_date->format('m/d/Y') }}</span>
                    </div>
                </div>
            </div>

            <div class="print-company-detail">{{ config('company.address') }}</div>
            <div class="print-company-detail">{{ config('company.proprietor') }} -Proprietor</div>
            <div class="print-company-detail">VAT Reg Tin: {{ config('company.tin') }}</div>
            <div class="print-company-detail mb-2">Email: {{ config('company.email') }}</div>

            <div class="table-responsive">
                <table class="table table-bordered table-sm print-items-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th>Generic Description</th>
                            <th style="width: 8%;">Unit</th>
                            <th class="text-end" style="width: 9%;">Order Qty</th>
                            <th class="text-end" style="width: 8%;">Adv</th>
                            <th class="text-end" style="width: 9%;">Balance</th>
                            <th>Remarks</th>
                            <th class="text-end" style="width: 8%;">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($salesOrder->items as $item)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $item->genericName->generic_name ?? '—' }}</td>
                                <td class="text-center">{{ $item->genericName->unit ?? '—' }}</td>
                                <td class="text-end">{{ $item->qty ?? '—' }}</td>
                                <td class="text-end">{{ $item->advance_order_qty }}</td>
                                <td class="text-end">{{ $item->remaining_qty }}</td>
                                <td>{{ $item->remarks ?? '—' }}</td>
                                <td class="text-end"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('partials.print.signature-block', [
                'columns' => 1,
                'label1' => 'Prepared By', 'value1' => $salesOrder->preparedBy->name ?? '—',
            ])
        </div>
    </div>

<style>
    @include('partials.print.base-print')

    .table-header-bg {
        background-color: #f7f8fa;
    }
    .table-info {
        background-color: #e7f3ff;
    }

    .so-print-only {
        display: none;
    }

    .so-sheet {
        font-size: 0.85rem;
    }

    .print-list-customer-table td {
        border-color: #333;
        font-size: 0.78rem;
        padding: 0.25rem 0.5rem;
        vertical-align: middle;
    }

    @media print {
        #printableSalesOrder {
            display: none !important;
        }

        .so-print-only {
            display: block !important;
        }

        #printableSalesOrderSheet,
        #printableSalesOrderListSheet {
            box-shadow: none !important;
            border: none !important;
        }

        #printableSalesOrderSheet .card-body,
        #printableSalesOrderListSheet .card-body {
            padding: 0 !important;
        }

        .table-header-bg,
        .table-info,
        .print-list-customer-table td:first-child {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Only one of the two print sheets shows at a time, chosen by
           whichever "Print" button was clicked (see printSalesOrder() /
           printSalesOrderList() below). */
        body.print-mode-list #printableSalesOrderSheet {
            display: none !important;
        }

        body:not(.print-mode-list) #printableSalesOrderListSheet {
            display: none !important;
        }
    }
</style>
@endsection

@section('scripts')
<script>
    function printSalesOrder() {
        document.body.classList.remove('print-mode-list');
        window.print();
    }

    function printSalesOrderList() {
        document.body.classList.add('print-mode-list');
        window.print();
    }
</script>
@endsection
