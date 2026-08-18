@extends('layout.app')

@section('title', 'Purchase Invoice ' . $purchaseInvoice->invoice_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <a href="{{ route('purchase-invoices.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Purchase Invoices
        </a>
        @if($purchaseInvoice->isDraft())
            <div class="d-flex gap-2">
                <a href="{{ route('purchase-invoices.edit', $purchaseInvoice) }}" class="btn btn-primary">
                    <i class="bx bx-edit-alt"></i> Continue Editing
                </a>
                <form action="{{ route('purchase-invoices.destroy', $purchaseInvoice) }}" method="POST" onsubmit="return confirm('Delete draft Purchase Invoice? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bx bx-trash"></i> Delete Draft
                    </button>
                </form>
            </div>
        @endif
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="text-muted small">Invoice No.</label>
                    <p class="fw-bold mb-0">{{ $purchaseInvoice->invoice_no ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Supplier</label>
                    <p class="fw-bold mb-0">{{ $purchaseInvoice->supplier->supplier_name ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Invoice Date</label>
                    <p class="mb-0">{{ $purchaseInvoice->invoice_date?->format('M d, Y') ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Status</label>
                    <p class="mb-0">
                        <span class="badge bg-{{ $purchaseInvoice->isDraft() ? 'secondary' : 'success' }}">
                            {{ \App\Models\PurchaseInvoice::STATUSES[$purchaseInvoice->status] ?? $purchaseInvoice->status }}
                        </span>
                    </p>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="text-muted small">Purchase Order</label>
                    <p class="mb-0">
                        @if($purchaseInvoice->purchaseOrder)
                            <a href="{{ route('purchase-orders.show', $purchaseInvoice->purchaseOrder) }}">{{ $purchaseInvoice->purchaseOrder->po_no }}</a>
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Goods Receipt</label>
                    <p class="mb-0">
                        @if($purchaseInvoice->goodsReceipt)
                            <a href="{{ route('goods-receipts.show', $purchaseInvoice->goodsReceipt) }}">{{ $purchaseInvoice->goodsReceipt->gr_no }}</a>
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Amount</label>
                    <p class="fw-bold mb-0">{{ $purchaseInvoice->amount !== null ? number_format($purchaseInvoice->amount, 2) : '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">VAT Amount</label>
                    <p class="mb-0">{{ $purchaseInvoice->vat_amount !== null ? number_format($purchaseInvoice->vat_amount, 2) : '—' }}</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Prepared By</label>
                    <p class="mb-0">{{ $purchaseInvoice->preparedBy->name ?? '—' }}</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <label class="text-muted small">Remarks</label>
                    <p class="mb-0">{{ $purchaseInvoice->remarks ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
