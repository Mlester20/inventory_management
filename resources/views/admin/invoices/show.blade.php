@extends('layout.app')

@section('title', 'Invoice ' . $invoice->sales_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Invoices
        </a>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="bx bx-printer"></i> Print
        </button>
    </div>

    <div class="card" id="printableInvoice">
        <div class="card-body p-4 invoice-sheet">

            <div class="invoice-letterhead row g-0 pb-2 mb-0">
                <div class="col-4 d-flex align-items-center">
                    <img src="{{ asset('assets/img/favicon/icon.png') }}" alt="SAIMS" class="invoice-logo me-2">
                    <div>
                        <div class="invoice-company-name">{{ strtoupper(config('company.name')) }}</div>
                        <div class="invoice-company-detail">{{ config('company.address') }}</div>
                        <div class="invoice-company-detail">{{ config('company.proprietor') }} - Proprietor</div>
                        <div class="invoice-company-detail">VAT Reg Tin: {{ config('company.tin') }}</div>
                        <div class="invoice-company-detail">Email: {{ config('company.email') }}</div>
                    </div>
                </div>
                <div class="col-8">
                    <table class="table table-bordered table-sm invoice-to-table mb-0">
                        <tr>
                            <td colspan="2" class="label invoice-to-header">INVOICE TO</td>
                            <td rowspan="5" class="invoice-doc-title">
                                <div class="invoice-title">SALES INVOICE</div>
                                <div class="invoice-no">No. <span>{{ $invoice->sales_no }}</span></div>
                                <div class="invoice-date">Date {{ $invoice->created_at->format('m/d/Y') }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="label" style="width: 90px;">Name</td>
                            <td>{{ $invoice->customer_name }}</td>
                        </tr>
                        <tr>
                            <td class="label">Address</td>
                            <td>—</td>
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

            <table class="table table-bordered table-sm invoice-strip-table mb-0">
                <thead>
                    <tr>
                        <th>Sale No.</th>
                        <th>Sales Date</th>
                        <th>Payment Term</th>
                        <th>Due Date</th>
                        <th>Customer Class</th>
                        <th>Customer PO No.</th>
                        <th>Order Date</th>
                        <th>OSCA/PWD ID No. &amp; Signature</th>
                        <th>Page</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $invoice->sales_no }}</td>
                        <td>{{ $invoice->created_at->format('m/d/Y') }}</td>
                        <td>—</td>
                        <td>—</td>
                        <td>—</td>
                        <td>{{ $invoice->po_no ?? '—' }}</td>
                        <td>—</td>
                        <td>{{ $invoice->osca_no ?? '—' }}</td>
                        <td>1 of 1</td>
                    </tr>
                </tbody>
            </table>

            <div class="invoice-body d-flex">
                <div class="table-responsive flex-grow-1">
                    <table class="table table-bordered table-sm invoice-items-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 6%;">Qty</th>
                                <th style="width: 7%;">Unit</th>
                                <th>Item Description</th>
                                <th style="width: 10%;">Batch No.</th>
                                <th style="width: 9%;">Expiry</th>
                                <th class="text-end" style="width: 9%;">Unit Price</th>
                                <th class="text-end" style="width: 9%;">Discount</th>
                                <th class="text-end" style="width: 8%;">VAT</th>
                                <th class="text-end" style="width: 10%;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->sales as $sale)
                                <tr>
                                    <td class="text-center">{{ $sale->qty }}</td>
                                    <td class="text-center">{{ $sale->unit ?? '—' }}</td>
                                    <td>{{ $sale->item->item_name ?? $sale->desc }}</td>
                                    <td class="text-center">{{ $sale->batch_no ?? '—' }}</td>
                                    <td class="text-center">{{ $sale->exp ? $sale->exp->format('Y-m') : '—' }}</td>
                                    <td class="text-end">{{ number_format($sale->price, 2) }}</td>
                                    <td class="text-end">{{ number_format($sale->dis, 2) }}</td>
                                    <td class="text-end">{{ number_format($sale->vat, 2) }}</td>
                                    <td class="text-end">{{ number_format($sale->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <table class="table table-bordered table-sm invoice-totals mb-0">
                    <tbody>
                        <tr><td>Vatable Sales</td></tr>
                        <tr><td class="text-end">{{ number_format($invoice->vat_sales, 2) }}</td></tr>
                        <tr><td>Vat-Exempt Sales</td></tr>
                        <tr><td class="text-end">{{ number_format($invoice->vatex_sales, 2) }}</td></tr>
                        <tr><td>Zero-Rated Sales</td></tr>
                        <tr><td class="text-end">{{ number_format($invoice->zero_sales, 2) }}</td></tr>
                        <tr><td>Vat Amount</td></tr>
                        <tr><td class="text-end">{{ number_format($invoice->vat_amount, 2) }}</td></tr>
                        <tr><td class="fw-bold">Total Sales (Vat Inclusive)</td></tr>
                        <tr><td class="text-end fw-bold">{{ number_format($invoice->total_sales, 2) }}</td></tr>
                        <tr><td>Less: Vat</td></tr>
                        <tr><td class="text-end">{{ number_format($invoice->less_vat, 2) }}</td></tr>
                        <tr><td>Amount Net of Vat</td></tr>
                        <tr><td class="text-end">{{ number_format($invoice->amount_net, 2) }}</td></tr>
                        <tr><td>Less: SC/PWD Discount</td></tr>
                        <tr><td class="text-end">{{ number_format($invoice->less_sc, 2) }}</td></tr>
                        <tr><td>Less: Withholding Tax</td></tr>
                        <tr><td class="text-end">{{ number_format($invoice->less_wt, 2) }}</td></tr>
                        <tr><td>Add: Vat</td></tr>
                        <tr><td class="text-end">{{ number_format($invoice->add_vat, 2) }}</td></tr>
                        <tr class="total-due-row"><td class="fw-bold">Total Invoice Amount</td></tr>
                        <tr class="total-due-row"><td class="text-end fw-bold">₱{{ number_format($invoice->amount_due, 2) }}</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="row mt-4 signature-block">
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Prepared By: {{ $invoice->preparedBy->name ?? '—' }}</div>
                </div>
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Approved By: {{ $invoice->approved_by ?? '—' }}</div>
                </div>
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Received By: ____________________</div>
                </div>
            </div>

            <div class="invoice-disclaimer mt-4">
                This sales invoice is made under agreement that above goods will not be sold for transshipment to either
                contraries or trades without written authorization of SAIMS Inventory &amp; General Merchandise. Violation
                of this condition shall entitle to latter collect from buyer and/or penalty and/or liquidated damages
                and amount equivalent to 100% of value goods purchased. This invoice shall be valid for five (5) years
                from the date of issue.
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<style>
    .invoice-sheet {
        font-size: 0.85rem;
    }

    .invoice-letterhead {
        border-bottom: 2px solid #333;
    }

    .invoice-logo {
        width: 56px;
        height: 56px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .invoice-company-name {
        font-weight: 700;
        font-size: 1rem;
        letter-spacing: 0.3px;
    }

    .invoice-company-detail {
        font-size: 0.7rem;
        line-height: 1.3;
        color: #333;
    }

    .invoice-doc-title {
        width: 190px;
        text-align: center;
        vertical-align: middle !important;
    }

    .invoice-doc-title .invoice-title {
        font-weight: 700;
        font-size: 1.1rem;
    }

    .invoice-doc-title .invoice-no span {
        font-weight: 700;
        color: #d9534f;
    }

    .invoice-to-table td,
    .invoice-strip-table th,
    .invoice-strip-table td,
    .invoice-items-table th,
    .invoice-items-table td,
    .invoice-totals td {
        border-color: #333;
        vertical-align: middle;
    }

    .invoice-to-table td {
        padding: 0.2rem 0.4rem;
        font-size: 0.72rem;
    }

    .invoice-to-table .label {
        font-weight: 700;
        background-color: #f5f5f5;
    }

    .invoice-to-header {
        font-weight: 700;
        text-align: center;
        background-color: #eee;
    }

    .invoice-strip-table th,
    .invoice-strip-table td {
        font-size: 0.62rem;
        text-align: center;
        padding: 0.2rem 0.3rem;
        white-space: nowrap;
    }

    .invoice-strip-table thead th {
        background-color: #eee;
        font-weight: 700;
    }

    .invoice-items-table thead th {
        background-color: #eee;
        font-weight: 700;
        text-align: center;
        white-space: nowrap;
    }

    .invoice-totals {
        width: 170px;
        flex-shrink: 0;
        margin-left: -1px;
    }

    .invoice-totals td {
        font-size: 0.68rem;
        padding: 0.15rem 0.4rem;
        text-align: center;
    }

    .invoice-totals td.text-end {
        text-align: right;
    }

    .invoice-totals tr:nth-child(odd) td {
        background-color: #f5f5f5;
    }

    .invoice-totals .total-due-row td {
        font-size: 0.85rem;
        background-color: #eee;
    }

    .invoice-disclaimer {
        font-size: 0.7rem;
        color: #6c757d;
        text-align: justify;
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

        #printableInvoice {
            box-shadow: none !important;
            border: none !important;
        }

        #printableInvoice .card-body {
            padding: 0 !important;
        }

        .invoice-totals,
        .table-responsive,
        .signature-block,
        table,
        tr {
            page-break-inside: avoid;
        }

        h4 {
            font-size: 1.1rem;
        }

        .table-sm td,
        .table-bordered td,
        .table-bordered th {
            padding: 0.25rem 0.4rem;
        }

        .invoice-items-table thead th,
        .invoice-to-table .label,
        .invoice-to-header,
        .invoice-strip-table thead th,
        .invoice-totals .total-due-row td {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
@endsection
