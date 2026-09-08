@extends('layout.app')

@section('title', 'Goods Receipt ' . $goodsReceipt->gr_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('goods-receipts.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Goods Receipts
        </a>
        @if($goodsReceipt->isDraft())
            <div class="d-flex gap-2">
                <a href="{{ route('goods-receipts.edit', $goodsReceipt) }}" class="btn btn-primary">
                    <i class="bx bx-edit-alt"></i> Continue Editing
                </a>
                <form action="{{ route('goods-receipts.destroy', $goodsReceipt) }}" method="POST" onsubmit="return confirm('Delete draft {{ $goodsReceipt->gr_no }}? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bx bx-trash"></i> Delete Draft
                    </button>
                </form>
            </div>
        @else
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                <i class="bx bx-printer"></i> Print
            </button>
        @endif
    </div>

    <div id="printableGoodsReceipt">
    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="text-muted small">G.R. No.</label>
                    <p class="fw-bold mb-0">{{ $goodsReceipt->gr_no }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Status</label>
                    <p class="mb-0">
                        <span class="badge bg-{{ $goodsReceipt->isDraft() ? 'secondary' : 'success' }}">
                            {{ \App\Models\GoodsReceipt::STATUSES[$goodsReceipt->status] ?? $goodsReceipt->status }}
                        </span>
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Supplier</label>
                    <p class="fw-bold mb-0">{{ $goodsReceipt->supplier->supplier_name ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Type</label>
                    <p class="mb-0">
                        <span class="badge bg-{{ $goodsReceipt->purchase_order_id ? 'info' : 'secondary' }}">
                            {{ $goodsReceipt->purchase_order_id ? 'Against P.O.' : 'Direct Receipt' }}
                        </span>
                    </p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Purchase Order</label>
                    <p class="mb-0">
                        @if($goodsReceipt->purchaseOrder)
                            <a href="{{ route('purchase-orders.show', $goodsReceipt->purchaseOrder) }}">{{ $goodsReceipt->purchaseOrder->po_no }}</a>
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Receipt Date</label>
                    <p class="mb-0">{{ $goodsReceipt->receipt_date?->format('M d, Y') ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Prepared By</label>
                    <p class="mb-0">{{ $goodsReceipt->preparedBy->name ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card no-print">
        <h5 class="card-header">Received Items</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr class="table-header-bg">
                        <th>Item</th>
                        <th class="text-end">Qty Received</th>
                        <th>Unit</th>
                        <th class="text-end">Unit Cost</th>
                        <th>Batch No.</th>
                        <th>Expiry</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($goodsReceipt->items as $line)
                        <tr>
                            <td>{{ $line->productBatch->product->item_name ?? $line->product->item_name ?? '—' }}</td>
                            <td class="text-end">{{ $line->qty ?? '—' }}</td>
                            <td>{{ $line->unit ?? '—' }}</td>
                            <td class="text-end">{{ $line->unit_cost !== null ? number_format($line->unit_cost, 2) : '—' }}</td>
                            <td>{{ $line->batch_no ?? '—' }}</td>
                            <td>{{ $line->expiration_date ? $line->expiration_date->format('M d, Y') : '—' }}</td>
                            <td>{{ $line->remarks ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    </div>

    <div class="card gr-print-only" id="printableGoodsReceiptSheet">
        <div class="card-body p-4 gr-sheet">

            @include('partials.print.letterhead', [
                'docTitle' => 'GOODS RECEIPT',
                'docNoLabel' => 'G.R No.',
                'docNo' => $goodsReceipt->gr_no,
                'docNoLabel2' => 'S.P.O No.',
                'docNo2' => $goodsReceipt->purchaseOrder->po_no ?? null,
                'docDate' => $goodsReceipt->receipt_date?->format('m/d/Y') ?? '—',
                'toHeader' => 'Supplier',
                'toRows' => [
                    'Name' => $goodsReceipt->supplier->supplier_name ?? '—',
                    'Address' => $goodsReceipt->supplier->delivery_address ?? '—',
                ],
            ])

            <div class="table-responsive">
                <table class="table table-bordered table-sm print-items-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th>Item Description</th>
                            <th style="width: 8%;">Unit</th>
                            <th class="text-end" style="width: 7%;">Qty</th>
                            <th style="width: 10%;">Lot/Batch</th>
                            <th style="width: 9%;">Expiry</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($goodsReceipt->items as $line)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $line->productBatch->product->item_name ?? $line->product->item_name ?? '—' }}</td>
                                <td class="text-center">{{ $line->unit ?? '—' }}</td>
                                <td class="text-end">{{ $line->qty ?? '—' }}</td>
                                <td class="text-center">{{ $line->batch_no ?? '—' }}</td>
                                <td class="text-center">{{ $line->expiration_date ? $line->expiration_date->format('M d, Y') : '—' }}</td>
                                <td>{{ $line->remarks ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('partials.print.signature-block', [
                'columns' => 1,
                'label1' => 'Prepared By', 'value1' => $goodsReceipt->preparedBy->name ?? '—',
            ])
        </div>
    </div>

<style>
    @include('partials.print.base-print')

    .table-header-bg {
        background-color: #f7f8fa;
    }

    .gr-print-only {
        display: none;
    }

    .gr-sheet {
        font-size: 0.85rem;
    }

    @media print {
        #printableGoodsReceipt {
            display: none !important;
        }

        .gr-print-only {
            display: block !important;
        }

        #printableGoodsReceiptSheet {
            box-shadow: none !important;
            border: none !important;
        }

        #printableGoodsReceiptSheet .card-body {
            padding: 0 !important;
        }
    }
</style>
@endsection
