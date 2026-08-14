@extends('layout.app')

@section('title', 'Goods Receipt ' . $goodsReceipt->gr_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <a href="{{ route('goods-receipts.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Goods Receipts
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="text-muted small">G.R. No.</label>
                    <p class="fw-bold mb-0">{{ $goodsReceipt->gr_no }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Supplier</label>
                    <p class="fw-bold mb-0">{{ $goodsReceipt->supplier->supplier_name }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Type</label>
                    <p class="mb-0">
                        <span class="badge bg-{{ $goodsReceipt->purchase_order_id ? 'info' : 'secondary' }}">
                            {{ $goodsReceipt->purchase_order_id ? 'Against P.O.' : 'Direct Receipt' }}
                        </span>
                    </p>
                </div>
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
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Receipt Date</label>
                    <p class="mb-0">{{ $goodsReceipt->receipt_date->format('M d, Y') }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Prepared By</label>
                    <p class="mb-0">{{ $goodsReceipt->preparedBy->name ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
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
                            <td>{{ $line->productBatch->product->item_name }}</td>
                            <td class="text-end">{{ $line->qty }}</td>
                            <td>{{ $line->unit ?? '—' }}</td>
                            <td class="text-end">{{ number_format($line->unit_cost, 2) }}</td>
                            <td>{{ $line->batch_no ?? '—' }}</td>
                            <td>{{ $line->expiration_date ? $line->expiration_date->format('M d, Y') : '—' }}</td>
                            <td>{{ $line->remarks ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

<style>
    .table-header-bg {
        background-color: #f7f8fa;
    }
</style>
@endsection
