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
                <form action="{{ route('sales-orders.destroy', $salesOrder) }}" method="POST" onsubmit="return confirm('Delete draft {{ $salesOrder->so_no }}? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bx bx-trash"></i> Delete Draft
                    </button>
                </form>
            @else
                @if($salesOrder->status !== 'completed' && $salesOrder->status !== 'cancelled')
                    <a href="{{ route('delivery-receipts.create', ['sales_order_id' => $salesOrder->id]) }}" class="btn btn-primary">
                        <i class="bx bx-plus"></i> Create Delivery Receipt
                    </a>
                @endif
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bx bx-printer"></i> Print
                </button>
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
                            <td>{{ $item->genericName->generic_name ?? '—' }} ({{ $item->genericName->unit ?? '—' }})</td>
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

    <div class="card so-print-only" id="printableSalesOrderSheet">
        <div class="card-body p-4 so-sheet">

            <div class="so-letterhead row g-0 pb-2 mb-0">
                <div class="col-4 d-flex align-items-center">
                    <img src="{{ asset('assets/img/favicon/icon.png') }}" alt="SAIMS" class="so-logo me-2">
                    <div>
                        <div class="so-company-name">{{ strtoupper(config('company.name')) }}</div>
                        <div class="so-company-detail">{{ config('company.address') }}</div>
                        <div class="so-company-detail">{{ config('company.proprietor') }} - Proprietor</div>
                        <div class="so-company-detail">VAT Reg Tin: {{ config('company.tin') }}</div>
                        <div class="so-company-detail">Email: {{ config('company.email') }}</div>
                    </div>
                </div>
                <div class="col-8">
                    <table class="table table-bordered table-sm so-to-table mb-0">
                        <tr>
                            <td colspan="2" class="label so-to-header">ORDER TO</td>
                            <td rowspan="5" class="so-doc-title">
                                <div class="so-title">SALES ORDER</div>
                                <div class="so-no">No. <span>{{ $salesOrder->so_no }}</span></div>
                                <div class="so-date">Date {{ $salesOrder->order_date->format('m/d/Y') }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="label" style="width: 90px;">Name</td>
                            <td>{{ $salesOrder->customer->customer_name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Address</td>
                            <td>{{ $salesOrder->customer?->delivery_address ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">TIN</td>
                            <td>—</td>
                        </tr>
                        <tr>
                            <td class="label">Business Style</td>
                            <td>—</td>
                        </tr>
                    </table>
                </div>
            </div>

            <table class="table table-bordered table-sm so-strip-table mb-0">
                <thead>
                    <tr>
                        <th>S.O. No.</th>
                        <th>Order Date</th>
                        <th>Customer P.O. No.</th>
                        <th>Status</th>
                        <th>Page</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $salesOrder->so_no }}</td>
                        <td>{{ $salesOrder->order_date->format('m/d/Y') }}</td>
                        <td>{{ $salesOrder->po_no ?? '—' }}</td>
                        <td>{{ $salesOrder->isDraft() ? 'Draft' : ucfirst(str_replace('_', ' ', $salesOrder->status)) }}</td>
                        <td>1 of 1</td>
                    </tr>
                </tbody>
            </table>

            <div class="so-body d-flex">
                <div class="table-responsive flex-grow-1">
                    <table class="table table-bordered table-sm so-items-table mb-0">
                        <thead>
                            <tr>
                                <th>Generic Description</th>
                                <th class="text-end" style="width: 8%;">Qty</th>
                                <th class="text-end" style="width: 11%;">Price</th>
                                <th class="text-end" style="width: 11%;">Advance Qty</th>
                                <th class="text-end" style="width: 11%;">Delivered</th>
                                <th class="text-end" style="width: 11%;">Remaining</th>
                                <th class="text-end" style="width: 13%;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($salesOrder->items as $item)
                                <tr>
                                    <td>{{ $item->genericName->generic_name ?? '—' }} ({{ $item->genericName->unit ?? '—' }})</td>
                                    <td class="text-end">{{ $item->qty ?? '—' }}</td>
                                    <td class="text-end">{{ $item->price !== null ? number_format($item->price, 2) : '—' }}</td>
                                    <td class="text-end">{{ $item->advance_order_qty }}</td>
                                    <td class="text-end">{{ $item->delivered_qty }}</td>
                                    <td class="text-end">{{ $item->remaining_qty }}</td>
                                    <td class="text-end">{{ ($item->qty !== null && $item->price !== null) ? number_format($item->qty * $item->price, 2) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <table class="table table-bordered table-sm so-totals mb-0">
                    <tbody>
                        <tr><td>Total Qty</td></tr>
                        <tr><td class="text-end">{{ $salesOrder->items->sum('qty') }}</td></tr>
                        <tr><td>Remaining Qty</td></tr>
                        <tr><td class="text-end">{{ $salesOrder->items->sum('remaining_qty') }}</td></tr>
                        <tr class="total-due-row"><td class="fw-bold">Total Amount</td></tr>
                        <tr class="total-due-row"><td class="text-end fw-bold">₱{{ number_format($salesOrder->items->sum(fn($i) => ($i->qty ?? 0) * ($i->price ?? 0)), 2) }}</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="row mt-4 signature-block">
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Prepared By: {{ $salesOrder->preparedBy->name ?? '—' }}</div>
                </div>
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Noted By: ____________________</div>
                </div>
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Received By: ____________________</div>
                </div>
            </div>
        </div>
    </div>

<style>
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

    .so-letterhead {
        border-bottom: 2px solid #333;
    }

    .so-logo {
        width: 56px;
        height: 56px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .so-company-name {
        font-weight: 700;
        font-size: 1rem;
        letter-spacing: 0.3px;
    }

    .so-company-detail {
        font-size: 0.7rem;
        line-height: 1.3;
        color: #333;
    }

    .so-doc-title {
        width: 190px;
        text-align: center;
        vertical-align: middle !important;
    }

    .so-doc-title .so-title {
        font-weight: 700;
        font-size: 1.1rem;
    }

    .so-doc-title .so-no span {
        font-weight: 700;
        color: #d9534f;
    }

    .so-to-table td,
    .so-strip-table th,
    .so-strip-table td,
    .so-items-table th,
    .so-items-table td,
    .so-totals td {
        border-color: #333;
        vertical-align: middle;
    }

    .so-to-table td {
        padding: 0.2rem 0.4rem;
        font-size: 0.72rem;
    }

    .so-to-table .label {
        font-weight: 700;
        background-color: #f5f5f5;
    }

    .so-to-header {
        font-weight: 700;
        text-align: center;
        background-color: #eee;
    }

    .so-strip-table th,
    .so-strip-table td {
        font-size: 0.62rem;
        text-align: center;
        padding: 0.2rem 0.3rem;
        white-space: nowrap;
    }

    .so-strip-table thead th {
        background-color: #eee;
        font-weight: 700;
    }

    .so-items-table thead th {
        background-color: #eee;
        font-weight: 700;
        text-align: center;
        white-space: nowrap;
    }

    .so-totals {
        width: 170px;
        flex-shrink: 0;
        margin-left: -1px;
    }

    .so-totals td {
        font-size: 0.68rem;
        padding: 0.15rem 0.4rem;
        text-align: center;
    }

    .so-totals td.text-end {
        text-align: right;
    }

    .so-totals tr:nth-child(odd) td {
        background-color: #f5f5f5;
    }

    .so-totals .total-due-row td {
        font-size: 0.85rem;
        background-color: #eee;
    }

    @media print {
        @page {
            size: auto;
            margin: 10mm;
        }

        .no-print,
        #layout-menu,
        #layout-navbar,
        .content-footer {
            display: none !important;
        }

        .layout-page {
            margin-left: 0 !important;
        }

        body {
            font-size: 12px;
        }

        #printableSalesOrder {
            display: none !important;
        }

        .so-print-only {
            display: block !important;
        }

        #printableSalesOrderSheet {
            box-shadow: none !important;
            border: none !important;
        }

        #printableSalesOrderSheet .card-body {
            padding: 0 !important;
        }

        .so-totals,
        .table-responsive,
        .signature-block,
        table,
        tr {
            page-break-inside: avoid;
        }

        .table-sm td,
        .table-bordered td,
        .table-bordered th {
            padding: 0.25rem 0.4rem;
        }

        .table-header-bg,
        .table-info,
        .so-items-table thead th,
        .so-to-table .label,
        .so-to-header,
        .so-strip-table thead th,
        .so-totals .total-due-row td {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
@endsection
