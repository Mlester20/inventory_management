@extends('layout.app')

@section('title', 'Delivery Receipt ' . $deliveryReceipt->dr_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <a href="{{ route('delivery-receipts.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Delivery Receipts
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="text-muted small">D.R. No.</label>
                    <p class="fw-bold mb-0">{{ $deliveryReceipt->dr_no }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Customer</label>
                    <p class="fw-bold mb-0">{{ $deliveryReceipt->customer->customer_name }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Type</label>
                    <p class="mb-0">
                        <span class="badge bg-{{ $deliveryReceipt->transaction_type === 'purchase_order' ? 'info' : 'secondary' }}">
                            {{ $deliveryReceipt->transaction_type === 'purchase_order' ? 'Purchase Order' : 'Advance Order' }}
                        </span>
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Sales Order</label>
                    <p class="mb-0">
                        @if($deliveryReceipt->salesOrder)
                            <a href="{{ route('sales-orders.show', $deliveryReceipt->salesOrder) }}">{{ $deliveryReceipt->salesOrder->so_no }}</a>
                        @else
                            —
                        @endif
                    </p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Receipt Date</label>
                    <p class="mb-0">{{ $deliveryReceipt->receipt_date->format('M d, Y') }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Prepared By</label>
                    <p class="mb-0">{{ $deliveryReceipt->preparedBy->name ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">Delivered Items</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr class="table-header-bg">
                        <th>Item</th>
                        <th>Batch No.</th>
                        <th>Expiry</th>
                        <th class="text-end">Qty Delivered</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deliveryReceipt->items as $line)
                        <tr>
                            <td>{{ $line->item->item_name }}</td>
                            <td>{{ $line->batch_no ?? '—' }}</td>
                            <td>{{ $line->expiration_date ? $line->expiration_date->format('M d, Y') : '—' }}</td>
                            <td class="text-end">{{ $line->qty }}</td>
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
