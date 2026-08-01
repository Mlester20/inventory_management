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

    <div class="card mb-4" id="printableStockDisposal">
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

<style>
    .table-header-bg {
        background-color: #f7f8fa;
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
            box-shadow: none !important;
            border: none !important;
        }

        table,
        tr {
            page-break-inside: avoid;
        }

        .table-header-bg {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
@endsection
