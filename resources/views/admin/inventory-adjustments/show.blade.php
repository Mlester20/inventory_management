@extends('layout.app')

@section('title', 'Inventory Adjustment ' . $inventoryAdjustment->adjustment_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('inventory-adjustments.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Inventory Adjustments
        </a>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="bx bx-printer"></i> Print
        </button>
    </div>

    <div class="card" id="printableInventoryAdjustment">
        <div class="card-body p-4 print-sheet">

            <div class="print-letterhead row g-0 pb-2 mb-0">
                <div class="col-4 d-flex align-items-center">
                    <img src="{{ asset('assets/img/favicon/icon.png') }}" alt="SAIMS" class="print-logo me-2">
                    <div>
                        <div class="print-company-name">{{ strtoupper(config('company.name')) }}</div>
                        <div class="print-company-detail">{{ config('company.address') }}</div>
                        <div class="print-company-detail">{{ config('company.proprietor') }} - Proprietor</div>
                        <div class="print-company-detail">VAT Reg Tin: {{ config('company.tin') }}</div>
                        <div class="print-company-detail">Email: {{ config('company.email') }}</div>
                    </div>
                </div>
                <div class="col-8">
                    <table class="table table-bordered table-sm print-to-table mb-0">
                        <tr>
                            <td colspan="2" class="label print-to-header">ADJUSTMENT DETAILS</td>
                            <td rowspan="5" class="print-doc-title">
                                <div class="print-title">INVENTORY ADJUSTMENT</div>
                                <div class="print-no">No. <span>{{ $inventoryAdjustment->adjustment_no }}</span></div>
                                <div class="print-date">Date {{ $inventoryAdjustment->adjustment_date->format('m/d/Y') }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td class="label" style="width: 110px;">Type</td>
                            <td>
                                {{ \App\Models\InventoryAdjustment::TYPES[$inventoryAdjustment->adjustment_type] ?? $inventoryAdjustment->adjustment_type }}
                                <span class="badge bg-{{ $inventoryAdjustment->direction() === 'in' ? 'success' : 'danger' }} ms-1">
                                    {{ $inventoryAdjustment->direction() === 'in' ? 'Stock In' : 'Stock Out' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Prepared By</td>
                            <td>{{ $inventoryAdjustment->preparedBy->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Description</td>
                            <td>{{ $inventoryAdjustment->description ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Note</td>
                            <td>{{ $inventoryAdjustment->note ?? '—' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="print-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm print-items-table mb-0">
                        <thead>
                            <tr>
                                <th>Item Description</th>
                                <th style="width: 12%;">Lot/Batch No.</th>
                                <th style="width: 10%;">Expiry Date</th>
                                <th style="width: 10%;">Location</th>
                                <th style="width: 8%;" class="text-end">Qty</th>
                                <th style="width: 18%;">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inventoryAdjustment->lines as $line)
                                <tr>
                                    <td>{{ $line->product->item_name }}</td>
                                    <td class="text-center">{{ $line->batch_no ?? '—' }}</td>
                                    <td class="text-center">{{ $line->expiration_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="text-center">{{ $line->location->name ?? '—' }}</td>
                                    <td class="text-end">{{ $line->qty }}</td>
                                    <td>{{ $line->remarks ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row mt-4 signature-block">
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Prepared By: {{ $inventoryAdjustment->preparedBy->name ?? '—' }}</div>
                </div>
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Reviewed By: ____________________</div>
                </div>
                <div class="col-4 text-center">
                    <div class="border-top pt-1">Approved By: ____________________</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<style>
    .print-sheet {
        font-size: 0.85rem;
    }

    .print-letterhead {
        border-bottom: 2px solid #333;
    }

    .print-logo {
        width: 56px;
        height: 56px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .print-company-name {
        font-weight: 700;
        font-size: 1rem;
        letter-spacing: 0.3px;
    }

    .print-company-detail {
        font-size: 0.7rem;
        line-height: 1.3;
        color: #333;
    }

    .print-doc-title {
        width: 190px;
        text-align: center;
        vertical-align: middle !important;
    }

    .print-doc-title .print-title {
        font-weight: 700;
        font-size: 1.1rem;
    }

    .print-doc-title .print-no span {
        font-weight: 700;
        color: #d9534f;
    }

    .print-to-table td,
    .print-items-table th,
    .print-items-table td {
        border-color: #333;
        vertical-align: middle;
    }

    .print-to-table td {
        padding: 0.2rem 0.4rem;
        font-size: 0.72rem;
    }

    .print-to-table .label {
        font-weight: 700;
        background-color: #f5f5f5;
    }

    .print-to-header {
        font-weight: 700;
        text-align: center;
        background-color: #eee;
    }

    .print-items-table thead th {
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

        #printableInventoryAdjustment {
            box-shadow: none !important;
            border: none !important;
        }

        #printableInventoryAdjustment .card-body {
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

        .print-items-table thead th,
        .print-to-table .label,
        .print-to-header {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
@endsection
