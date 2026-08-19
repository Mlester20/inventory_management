@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Delivery Receipt ' . $deliveryReceipt->dr_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('delivery-receipts.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Delivery Receipts
        </a>
        <div class="d-flex gap-2">
            @if($deliveryReceipt->isDraft())
                <a href="{{ route('delivery-receipts.edit', $deliveryReceipt) }}" class="btn btn-primary">
                    <i class="bx bx-edit-alt"></i> Continue Editing
                </a>
                <form action="{{ route('delivery-receipts.destroy', $deliveryReceipt) }}" method="POST" onsubmit="return confirm('Delete draft {{ $deliveryReceipt->dr_no }}? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bx bx-trash"></i> Delete Draft
                    </button>
                </form>
            @else
                @if($deliveryReceipt->status !== 'delivered')
                    <form action="{{ route('delivery-receipts.mark-delivered', $deliveryReceipt) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="bx bx-check-circle"></i> Mark as Delivered
                        </button>
                    </form>
                @endif
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bx bx-printer"></i> Print
                </button>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger no-print">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('delivery-receipts.create-invoice', $deliveryReceipt) }}" method="POST" id="createInvoiceForm" class="no-print">
        @csrf
        <div class="card" id="printableDeliveryReceipt">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Delivery Note</h5>
                <div class="d-flex gap-2">
                    <span class="badge bg-{{ ['purchase_order' => 'info', 'walk_in' => 'warning'][$deliveryReceipt->transaction_type] ?? 'secondary' }}">
                        {{ \App\Models\DeliveryReceipt::TRANSACTION_TYPES[$deliveryReceipt->transaction_type] ?? ($deliveryReceipt->transaction_type ?? '—') }}
                    </span>
                    @if($deliveryReceipt->isDraft())
                        <span class="badge bg-secondary">DRAFT</span>
                    @else
                        <span class="badge bg-{{ $deliveryReceipt->status === 'delivered' ? 'success' : 'warning text-dark' }}">
                            {{ \App\Models\DeliveryReceipt::STATUSES[$deliveryReceipt->status] ?? $deliveryReceipt->status }}
                        </span>
                        @php $invoiceStatusColor = ['COMPLETE' => 'success', 'PARTIALLY INVOICED' => 'warning', 'NOT INVOICED' => 'secondary'][$deliveryReceipt->invoice_status] ?? 'secondary'; @endphp
                        <span class="badge bg-{{ $invoiceStatusColor }}">{{ $deliveryReceipt->invoice_status }}</span>
                    @endif
                </div>
            </div>

            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="text-muted small">Delivery Date</label>
                        <p class="fw-bold mb-0">{{ $deliveryReceipt->receipt_date->format('M d, Y') }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Reference</label>
                        <p class="fw-bold mb-0">{{ $deliveryReceipt->dr_no }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Customer</label>
                        <p class="fw-bold mb-0">{{ $deliveryReceipt->customer->customer_name ?? '—' }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Sales Order Number</label>
                        <p class="mb-0">
                            @if($deliveryReceipt->salesOrder)
                                <a href="{{ route('sales-orders.show', $deliveryReceipt->salesOrder) }}">{{ $deliveryReceipt->salesOrder->so_no }}</a>
                            @else
                                —
                            @endif
                        </p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Delivery Address</label>
                        <p class="mb-0" style="white-space: pre-line;">{{ $deliveryReceipt->customer->delivery_address ?? '—' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Description</label>
                        <p class="mb-0">{{ $deliveryReceipt->description ?? '—' }}</p>
                    </div>
                </div>

                <hr>

                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle">
                        <thead>
                            <tr class="table-header-bg">
                                @if(!$deliveryReceipt->isDraft())
                                    <th class="no-print"></th>
                                @endif
                                <th>#</th>
                                <th>Generic Description</th>
                                <th>Item Description</th>
                                <th>Lot/Batch No.</th>
                                <th>Expiry Date</th>
                                <th>Remarks</th>
                                <th class="text-end">Qty</th>
                                <th>Unit</th>
                                @if(!$deliveryReceipt->isDraft())
                                    <th class="text-end">Invoiced</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveryReceipt->items as $line)
                                @php $fullyInvoiced = $line->invoiced_qty >= $line->qty; @endphp
                                <tr>
                                    @if(!$deliveryReceipt->isDraft())
                                        <td class="no-print">
                                            <input type="checkbox" class="form-check-input line-checkbox" name="line_ids[]" value="{{ $line->id }}" {{ $fullyInvoiced ? 'disabled' : '' }}>
                                        </td>
                                    @endif
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $line->productBatch->product->genericName->generic_name ?? '—' }}</td>
                                    <td>{{ $line->productBatch->product->item_name }}</td>
                                    <td>{{ $line->batch_no ?? '—' }}</td>
                                    <td>{{ $line->expiration_date ? $line->expiration_date->format('M d, Y') : '—' }}</td>
                                    <td>{{ $line->remarks ?? '—' }}</td>
                                    <td class="text-end">{{ $line->qty ?? '—' }}</td>
                                    <td>{{ $line->productBatch->product->genericName->unit ?? '—' }}</td>
                                    @if(!$deliveryReceipt->isDraft())
                                        <td class="text-end">
                                            @php $invoiceForLine = $line->sales->first()?->invoice; @endphp
                                            @if($line->invoiced_qty > 0 && $invoiceForLine)
                                                <a href="{{ route('invoices.show', $invoiceForLine) }}">{{ $line->invoiced_qty }}</a>
                                            @else
                                                {{ $line->invoiced_qty }}
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(!$deliveryReceipt->isDraft())
                    <p class="text-muted small mb-3 no-print">
                        Check the lines to invoice, then click Create Invoice. A line's checkbox disables once it's fully invoiced.
                    </p>
                @endif

                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label class="text-muted small">Prepared By</label>
                        <p class="fw-bold mb-0">{{ $deliveryReceipt->preparedBy->name ?? '—' }}</p>
                    </div>
                    @if(!$deliveryReceipt->isDraft())
                        <div class="col-md-8 text-md-end mt-3 mt-md-0 no-print">
                            <button type="submit" class="btn btn-primary" id="createInvoiceBtn" disabled>
                                <i class="bx bx-receipt"></i> Create Invoice
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <div class="card dr-print-only" id="printableDeliveryNote">
        <div class="card-body p-4 dr-sheet">

            <div class="dr-letterhead row g-0 pb-2 mb-0">
                <div class="col-4 d-flex align-items-center">
                    <img src="{{ asset('assets/img/favicon/icon.png') }}" alt="SAIMS" class="dr-logo me-2">
                    <div>
                        <div class="dr-company-name">{{ strtoupper(config('company.name')) }}</div>
                        <div class="dr-company-detail">{{ config('company.address') }}</div>
                        <div class="dr-company-detail">{{ config('company.proprietor') }} - Proprietor</div>
                        <div class="dr-company-detail">VAT Reg Tin: {{ config('company.tin') }}</div>
                        <div class="dr-company-detail">Email: {{ config('company.email') }}</div>
                    </div>
                </div>
                <div class="col-8">
                    <table class="table table-bordered table-sm dr-to-table mb-0">
                        <tr>
                            <td colspan="2" class="label dr-to-header">DELIVER TO</td>
                            <td rowspan="5" class="dr-doc-title">
                                <div class="dr-title">DELIVERY RECEIPT</div>
                                <div class="dr-no">No. <span>{{ $deliveryReceipt->dr_no }}</span></div>
                                <div class="dr-date">Date {{ $deliveryReceipt->receipt_date->format('m/d/Y') }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="label" style="width: 90px;">Name</td>
                            <td>{{ $deliveryReceipt->customer->customer_name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Address</td>
                            <td style="white-space: pre-line;">{{ $deliveryReceipt->customer->delivery_address ?? '—' }}</td>
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

            <table class="table table-bordered table-sm dr-strip-table mb-0">
                <thead>
                    <tr>
                        <th>D.R. No.</th>
                        <th>Delivery Date</th>
                        <th>Sales Order No.</th>
                        <th>Status</th>
                        <th>Page</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $deliveryReceipt->dr_no }}</td>
                        <td>{{ $deliveryReceipt->receipt_date->format('m/d/Y') }}</td>
                        <td>{{ $deliveryReceipt->salesOrder->so_no ?? '—' }}</td>
                        <td>{{ \App\Models\DeliveryReceipt::STATUSES[$deliveryReceipt->status] ?? $deliveryReceipt->status }}</td>
                        <td>1 of 1</td>
                    </tr>
                </tbody>
            </table>

            <div class="table-responsive">
                <table class="table table-bordered table-sm dr-items-table mb-0">
                    <thead>
                        <tr>
                            <th>Generic Description</th>
                            <th>Item Description</th>
                            <th style="width: 10%;">Lot/Batch No.</th>
                            <th style="width: 9%;">Expiry Date</th>
                            <th>Remarks</th>
                            <th class="text-end" style="width: 7%;">Qty</th>
                            <th style="width: 8%;">Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliveryReceipt->items as $line)
                            <tr>
                                <td>{{ $line->productBatch->product->genericName->generic_name ?? '—' }}</td>
                                <td>{{ $line->productBatch->product->item_name }}</td>
                                <td class="text-center">{{ $line->batch_no ?? '—' }}</td>
                                <td class="text-center">{{ $line->expiration_date ? $line->expiration_date->format('M d, Y') : '—' }}</td>
                                <td>{{ $line->remarks ?? '—' }}</td>
                                <td class="text-end">{{ $line->qty ?? '—' }}</td>
                                <td>{{ $line->productBatch->product->genericName->unit ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row mt-4 signature-block">
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Prepared By: {{ $deliveryReceipt->preparedBy->name ?? '—' }}</div>
                </div>
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Delivered By: ____________________</div>
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

    .dr-print-only {
        display: none;
    }

    .dr-sheet {
        font-size: 0.85rem;
    }

    .dr-letterhead {
        border-bottom: 2px solid #333;
    }

    .dr-logo {
        width: 56px;
        height: 56px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .dr-company-name {
        font-weight: 700;
        font-size: 1rem;
        letter-spacing: 0.3px;
    }

    .dr-company-detail {
        font-size: 0.7rem;
        line-height: 1.3;
        color: #333;
    }

    .dr-doc-title {
        width: 190px;
        text-align: center;
        vertical-align: middle !important;
    }

    .dr-doc-title .dr-title {
        font-weight: 700;
        font-size: 1.1rem;
    }

    .dr-doc-title .dr-no span {
        font-weight: 700;
        color: #d9534f;
    }

    .dr-to-table td,
    .dr-strip-table th,
    .dr-strip-table td,
    .dr-items-table th,
    .dr-items-table td {
        border-color: #333;
        vertical-align: middle;
    }

    .dr-to-table td {
        padding: 0.2rem 0.4rem;
        font-size: 0.72rem;
    }

    .dr-to-table .label {
        font-weight: 700;
        background-color: #f5f5f5;
    }

    .dr-to-header {
        font-weight: 700;
        text-align: center;
        background-color: #eee;
    }

    .dr-strip-table th,
    .dr-strip-table td {
        font-size: 0.62rem;
        text-align: center;
        padding: 0.2rem 0.3rem;
        white-space: nowrap;
    }

    .dr-strip-table thead th {
        background-color: #eee;
        font-weight: 700;
    }

    .dr-items-table thead th {
        background-color: #eee;
        font-weight: 700;
        text-align: center;
        white-space: nowrap;
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

        #printableDeliveryReceipt {
            box-shadow: none !important;
            border: none !important;
        }

        .dr-print-only {
            display: block !important;
        }

        #printableDeliveryNote {
            box-shadow: none !important;
            border: none !important;
        }

        #printableDeliveryNote .card-body {
            padding: 0 !important;
        }

        table,
        tr {
            page-break-inside: avoid;
        }

        .table-header-bg {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .dr-items-table thead th,
        .dr-to-table .label,
        .dr-to-header,
        .dr-strip-table thead th {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
@endsection

@section('scripts')
<script>
    function refreshCreateInvoiceBtn() {
        const anyChecked = document.querySelectorAll('.line-checkbox:checked').length > 0;
        document.getElementById('createInvoiceBtn').disabled = !anyChecked;
    }

    document.querySelectorAll('.line-checkbox').forEach(cb => {
        cb.addEventListener('change', refreshCreateInvoiceBtn);
    });
</script>
@endsection
