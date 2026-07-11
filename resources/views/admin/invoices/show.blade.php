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
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h4 class="mb-0">SALES INVOICE</h4>
                <div class="text-muted">SAIMS Inventory Management</div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="fw-bold" style="width: 140px;">Customer Name:</td>
                            <td>{{ $invoice->customer_name }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">P.O. No.:</td>
                            <td>{{ $invoice->po_no ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">SC/PWD ID No.:</td>
                            <td>{{ $invoice->osca_no ?? '—' }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="fw-bold" style="width: 140px;">Sales Invoice No.:</td>
                            <td>{{ $invoice->sales_no }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Date:</td>
                            <td>{{ $invoice->created_at->format('F d, Y') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="border rounded p-3 mb-4 summary-box">
                <h6 class="fw-bold mb-3">Sales &amp; VAT Summary</h6>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td>VATable Sales</td>
                                <td class="text-end">₱{{ number_format($invoice->vat_sales, 2) }}</td>
                            </tr>
                            <tr>
                                <td>VAT-Exempt Sales</td>
                                <td class="text-end">₱{{ number_format($invoice->vatex_sales, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Zero-Rated Sales</td>
                                <td class="text-end">₱{{ number_format($invoice->zero_sales, 2) }}</td>
                            </tr>
                            <tr>
                                <td>VAT Amount</td>
                                <td class="text-end">₱{{ number_format($invoice->vat_amount, 2) }}</td>
                            </tr>
                            <tr class="fw-bold border-top">
                                <td>Total Sales</td>
                                <td class="text-end">₱{{ number_format($invoice->total_sales, 2) }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td>Less: VAT</td>
                                <td class="text-end">₱{{ number_format($invoice->less_vat, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Amount Net of VAT</td>
                                <td class="text-end">₱{{ number_format($invoice->amount_net, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Less: SC/PWD Discount</td>
                                <td class="text-end">₱{{ number_format($invoice->less_sc, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Less: Withholding Tax</td>
                                <td class="text-end">₱{{ number_format($invoice->less_wt, 2) }}</td>
                            </tr>
                            <tr class="fw-bold fs-5 border-top">
                                <td>Amount Due</td>
                                <td class="text-end">₱{{ number_format($invoice->amount_due, 2) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Description</th>
                            <th>Qty</th>
                            <th>Unit</th>
                            <th>Batch No.</th>
                            <th>Expiry</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Discount</th>
                            <th class="text-end">VAT</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->sales as $sale)
                            <tr>
                                <td>{{ $sale->item->item_name ?? '—' }}</td>
                                <td>{{ $sale->desc }}</td>
                                <td>{{ $sale->qty }}</td>
                                <td>{{ $sale->unit ?? '—' }}</td>
                                <td>{{ $sale->batch_no ?? '—' }}</td>
                                <td>{{ $sale->exp ? $sale->exp->format('M d, Y') : '—' }}</td>
                                <td class="text-end">₱{{ number_format($sale->price, 2) }}</td>
                                <td class="text-end">₱{{ number_format($sale->dis, 2) }}</td>
                                <td class="text-end">₱{{ number_format($sale->vat, 2) }}</td>
                                <td class="text-end">₱{{ number_format($sale->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row mt-5 signature-block">
                <div class="col-md-6 text-center">
                    <div class="border-top pt-1">Prepared By: {{ $invoice->preparedBy->name ?? '—' }}</div>
                </div>
                <div class="col-md-6 text-center">
                    <div class="border-top pt-1">Approved By: {{ $invoice->approved_by ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<style>
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

        .summary-box,
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
    }
</style>
@endsection
