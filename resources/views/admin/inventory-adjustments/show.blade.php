@extends('layout.app')

@section('title', 'Inventory Adjustment ' . $inventoryAdjustment->adjustment_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('inventory-adjustments.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Inventory Adjustments
        </a>
        <div class="d-flex gap-2">
            @if($inventoryAdjustment->isDraft())
                <a href="{{ route('inventory-adjustments.edit', $inventoryAdjustment) }}" class="btn btn-primary">
                    <i class="bx bx-edit-alt"></i> Continue Editing
                </a>
                <form action="{{ route('inventory-adjustments.destroy', $inventoryAdjustment) }}" method="POST" onsubmit="return confirm('Delete draft {{ $inventoryAdjustment->adjustment_no }}? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bx bx-trash"></i> Delete Draft
                    </button>
                </form>
            @else
                <a href="{{ route('inventory-adjustments.edit', $inventoryAdjustment) }}" class="btn btn-outline-secondary">
                    <i class="bx bx-edit-alt"></i> Edit
                </a>
                @if($inventoryAdjustment->reversedBy)
                    <button type="button" class="btn btn-outline-warning" disabled title="Already written off by {{ $inventoryAdjustment->reversedBy->adjustment_no }}">
                        <i class="bx bx-undo"></i> Write-off
                    </button>
                @else
                    <form action="{{ route('inventory-adjustments.write-off', $inventoryAdjustment) }}" method="POST" onsubmit="return confirm('Write off {{ $inventoryAdjustment->adjustment_no }}? This reverses its quantities and takes you to a fresh form for the correct entry.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning">
                            <i class="bx bx-undo"></i> Write-off
                        </button>
                    </form>
                @endif
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="bx bx-printer"></i> Print
                </button>
            @endif
        </div>
    </div>

    @if($inventoryAdjustment->isDraft())
        <div class="alert alert-secondary no-print">
            <i class="bx bx-edit-alt"></i> This is a <strong>draft</strong> — it hasn't affected inventory
            yet. Continue editing it, or delete it, until it's finalized with Save.
        </div>
    @endif

    @if($inventoryAdjustment->reverses)
        <div class="alert alert-warning no-print">
            <i class="bx bx-undo"></i> This is a write-off of
            <a href="{{ route('inventory-adjustments.show', $inventoryAdjustment->reverses) }}">{{ $inventoryAdjustment->reverses->adjustment_no }}</a>.
        </div>
    @endif

    @if($inventoryAdjustment->reversedBy)
        <div class="alert alert-warning no-print">
            <i class="bx bx-undo"></i> This adjustment has been written off by
            <a href="{{ route('inventory-adjustments.show', $inventoryAdjustment->reversedBy) }}">{{ $inventoryAdjustment->reversedBy->adjustment_no }}</a>.
        </div>
    @endif

    {{-- Normal on-screen view — the original simple layout, unchanged. Hidden
         only when printing, where the letterhead version below takes over. --}}
    <div class="card mt-3 no-print">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Inventory Adjustment {{ $inventoryAdjustment->adjustment_no }}</h5>
            <span class="badge bg-{{ $inventoryAdjustment->isDraft() ? 'secondary' : 'success' }}">
                {{ \App\Models\InventoryAdjustment::STATUSES[$inventoryAdjustment->status] ?? $inventoryAdjustment->status }}
            </span>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="fw-bold">Date</label>
                    <p>{{ $inventoryAdjustment->adjustment_date->format('Y-m-d') }}</p>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold">Type</label>
                    <p>{{ \App\Models\InventoryAdjustment::TYPES[$inventoryAdjustment->adjustment_type] ?? $inventoryAdjustment->adjustment_type }}</p>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold">Prepared By</label>
                    <p>{{ $inventoryAdjustment->preparedBy->name ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold">Description</label>
                    <p>{{ $inventoryAdjustment->description ?? '—' }}</p>
                </div>
            </div>

            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th>Lot/Batch No.</th>
                        <th>Expiry Date</th>
                        <th>Location</th>
                        <th>Qty</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($inventoryAdjustment->lines as $line)
                        <tr>
                            <td>{{ $line->product->item_name }}</td>
                            <td>{{ $line->batch_no ?? '—' }}</td>
                            <td>{{ $line->expiration_date?->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $line->location->name ?? '—' }}</td>
                            <td>{{ $line->qty }}</td>
                            <td>{{ $line->remarks ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($inventoryAdjustment->note)
                <div class="mt-3">
                    <label class="fw-bold">Note</label>
                    <p>{{ $inventoryAdjustment->note }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Print-only letterhead layout — invisible on screen (d-none), forced
         visible by Bootstrap's d-print-block only inside @media print. --}}
    <div class="card d-none d-print-block" id="printableInventoryAdjustment">
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
