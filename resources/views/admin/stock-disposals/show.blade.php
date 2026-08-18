@extends('layout.app')

@section('title', 'Stock Disposal ' . $stockDisposal->reference)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('stock-disposals.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Stock Disposals
        </a>
        <button type="button" class="btn btn-outline-primary" onclick="window.print()">
            <i class="bx bx-printer"></i> Print
        </button>
    </div>

    <div class="card mb-4 no-print" id="printableStockDisposal">
        <div class="card-header">
            <h5 class="mb-0">Stock Disposal / Write-off</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="text-muted small">Reference</label>
                    <p class="fw-bold mb-0">{{ $stockDisposal->reference }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Date</label>
                    <p class="mb-0">{{ $stockDisposal->date->format('M d, Y') }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Reason</label>
                    <p class="mb-0">
                        <span class="badge bg-danger">{{ \App\Models\StockDisposal::REASONS[$stockDisposal->reason] ?? $stockDisposal->reason }}</span>
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Prepared By</label>
                    <p class="mb-0">{{ $stockDisposal->preparedBy->name ?? '—' }}</p>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="text-muted small">Remarks</label>
                    <p class="mb-0">{{ $stockDisposal->remarks ?? '—' }}</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr class="table-header-bg">
                            <th>Item</th>
                            <th>Supplier</th>
                            <th>Batch No.</th>
                            <th>Expiry</th>
                            <th>Location</th>
                            <th class="text-end">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stockDisposal->lines as $line)
                            <tr>
                                <td>{{ $line->productBatch->product->item_name }}</td>
                                <td>{{ $line->productBatch->product->supplier->supplier_name ?? '—' }}</td>
                                <td>{{ $line->productBatch->batch_no ?? '—' }}</td>
                                <td>{{ $line->productBatch->expiration_date?->format('M d, Y') ?? '—' }}</td>
                                <td>{{ $line->location->name }}</td>
                                <td class="text-end">{{ $line->qty }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card sd-print-only" id="printableStockDisposalSheet">
        <div class="card-body p-4 sd-sheet">

            <div class="sd-letterhead row g-0 pb-2 mb-0">
                <div class="col-4 d-flex align-items-center">
                    <img src="{{ asset('assets/img/favicon/icon.png') }}" alt="SAIMS" class="sd-logo me-2">
                    <div>
                        <div class="sd-company-name">{{ strtoupper(config('company.name')) }}</div>
                        <div class="sd-company-detail">{{ config('company.address') }}</div>
                        <div class="sd-company-detail">{{ config('company.proprietor') }} - Proprietor</div>
                        <div class="sd-company-detail">VAT Reg Tin: {{ config('company.tin') }}</div>
                        <div class="sd-company-detail">Email: {{ config('company.email') }}</div>
                    </div>
                </div>
                <div class="col-8">
                    <table class="table table-bordered table-sm sd-to-table mb-0">
                        <tr>
                            <td colspan="2" class="label sd-to-header">DISPOSAL DETAILS</td>
                            <td rowspan="4" class="sd-doc-title">
                                <div class="sd-title">STOCK DISPOSAL / WRITE-OFF</div>
                                <div class="sd-no">No. <span>{{ $stockDisposal->reference }}</span></div>
                                <div class="sd-date">Date {{ $stockDisposal->date->format('m/d/Y') }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="label" style="width: 90px;">Reason</td>
                            <td>{{ \App\Models\StockDisposal::REASONS[$stockDisposal->reason] ?? $stockDisposal->reason }}</td>
                        </tr>
                        <tr>
                            <td class="label">Prepared By</td>
                            <td>{{ $stockDisposal->preparedBy->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Remarks</td>
                            <td>{{ $stockDisposal->remarks ?? '—' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <table class="table table-bordered table-sm sd-strip-table mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Reason</th>
                        <th>Prepared By</th>
                        <th>Page</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $stockDisposal->reference }}</td>
                        <td>{{ $stockDisposal->date->format('m/d/Y') }}</td>
                        <td>{{ \App\Models\StockDisposal::REASONS[$stockDisposal->reason] ?? $stockDisposal->reason }}</td>
                        <td>{{ $stockDisposal->preparedBy->name ?? '—' }}</td>
                        <td>1 of 1</td>
                    </tr>
                </tbody>
            </table>

            <div class="table-responsive">
                <table class="table table-bordered table-sm sd-items-table mb-0">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Supplier</th>
                            <th style="width: 10%;">Batch No.</th>
                            <th style="width: 10%;">Expiry</th>
                            <th style="width: 12%;">Location</th>
                            <th class="text-end" style="width: 7%;">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stockDisposal->lines as $line)
                            <tr>
                                <td>{{ $line->productBatch->product->item_name }}</td>
                                <td>{{ $line->productBatch->product->supplier->supplier_name ?? '—' }}</td>
                                <td>{{ $line->productBatch->batch_no ?? '—' }}</td>
                                <td>{{ $line->productBatch->expiration_date?->format('M d, Y') ?? '—' }}</td>
                                <td>{{ $line->location->name }}</td>
                                <td class="text-end">{{ $line->qty }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row mt-4 signature-block">
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Prepared By: {{ $stockDisposal->preparedBy->name ?? '—' }}</div>
                </div>
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Checked By: ____________________</div>
                </div>
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Approved By: ____________________</div>
                </div>
            </div>
        </div>
    </div>

<style>
    .table-header-bg {
        background-color: #f7f8fa;
    }

    .sd-print-only {
        display: none;
    }

    .sd-sheet {
        font-size: 0.85rem;
    }

    .sd-letterhead {
        border-bottom: 2px solid #333;
    }

    .sd-logo {
        width: 56px;
        height: 56px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .sd-company-name {
        font-weight: 700;
        font-size: 1rem;
        letter-spacing: 0.3px;
    }

    .sd-company-detail {
        font-size: 0.7rem;
        line-height: 1.3;
        color: #333;
    }

    .sd-doc-title {
        width: 190px;
        text-align: center;
        vertical-align: middle !important;
    }

    .sd-doc-title .sd-title {
        font-weight: 700;
        font-size: 1rem;
    }

    .sd-doc-title .sd-no span {
        font-weight: 700;
        color: #d9534f;
    }

    .sd-to-table td,
    .sd-strip-table th,
    .sd-strip-table td,
    .sd-items-table th,
    .sd-items-table td {
        border-color: #333;
        vertical-align: middle;
    }

    .sd-to-table td {
        padding: 0.2rem 0.4rem;
        font-size: 0.72rem;
    }

    .sd-to-table .label {
        font-weight: 700;
        background-color: #f5f5f5;
    }

    .sd-to-header {
        font-weight: 700;
        text-align: center;
        background-color: #eee;
    }

    .sd-strip-table th,
    .sd-strip-table td {
        font-size: 0.62rem;
        text-align: center;
        padding: 0.2rem 0.3rem;
        white-space: nowrap;
    }

    .sd-strip-table thead th {
        background-color: #eee;
        font-weight: 700;
    }

    .sd-items-table thead th {
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

        #printableStockDisposal {
            display: none !important;
        }

        .sd-print-only {
            display: block !important;
        }

        #printableStockDisposalSheet {
            box-shadow: none !important;
            border: none !important;
        }

        #printableStockDisposalSheet .card-body {
            padding: 0 !important;
        }

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
        .sd-items-table thead th,
        .sd-to-table .label,
        .sd-to-header,
        .sd-strip-table thead th {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
@endsection
