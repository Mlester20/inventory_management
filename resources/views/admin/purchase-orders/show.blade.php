@extends('layout.app')

@section('title', 'Purchase Order ' . $purchaseOrder->po_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Purchase Orders
        </a>
        @if($purchaseOrder->isDraft())
            <div class="d-flex gap-2">
                <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="btn btn-primary">
                    <i class="bx bx-edit-alt"></i> Continue Editing
                </a>
                <form action="{{ route('purchase-orders.destroy', $purchaseOrder) }}" method="POST" onsubmit="return confirm('Delete draft {{ $purchaseOrder->po_no }}? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bx bx-trash"></i> Delete Draft
                    </button>
                </form>
            </div>
        @elseif($purchaseOrder->status !== 'completed' && $purchaseOrder->status !== 'cancelled')
            <a href="{{ route('goods-receipts.create', ['purchase_order_id' => $purchaseOrder->id]) }}" class="btn btn-primary">
                <i class="bx bx-plus"></i> Create Goods Receipt
            </a>
        @endif
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="text-muted small">P.O. No.</label>
                    <p class="fw-bold mb-0">{{ $purchaseOrder->po_no }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Supplier</label>
                    <p class="fw-bold mb-0">{{ $purchaseOrder->supplier->supplier_name ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Order Date</label>
                    <p class="mb-0">{{ $purchaseOrder->order_date->format('M d, Y') }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Status</label>
                    <p class="mb-0">
                        @if($purchaseOrder->isDraft())
                            <span class="badge bg-secondary">DRAFT</span>
                        @else
                            <span class="badge bg-{{ ['open' => 'warning', 'partially_received' => 'info', 'completed' => 'success', 'cancelled' => 'danger'][$purchaseOrder->status] ?? 'secondary' }}">
                                {{ ucfirst(str_replace('_', ' ', $purchaseOrder->status)) }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Prepared By</label>
                    <p class="mb-0">{{ $purchaseOrder->preparedBy->name ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <h5 class="card-header">Line Items</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr class="table-header-bg">
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th>Unit</th>
                        <th class="text-end">Unit Cost</th>
                        <th>Remarks</th>
                        <th class="text-end">Received</th>
                        <th class="text-end">Remaining</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseOrder->items as $item)
                        <tr>
                            <td>{{ $item->product->item_name ?? $item->genericName->generic_name ?? '—' }}</td>
                            <td class="text-end">{{ $item->qty ?? '—' }}</td>
                            <td>{{ $item->unit ?? '—' }}</td>
                            <td class="text-end">{{ $item->unit_cost !== null ? number_format($item->unit_cost, 2) : '—' }}</td>
                            <td>{{ $item->remarks ?? '—' }}</td>
                            <td class="text-end">{{ $item->received_qty }}</td>
                            <td class="text-end">
                                <span class="badge bg-{{ $item->remaining_qty > 0 ? 'warning' : 'success' }}">
                                    {{ $item->remaining_qty }}
                                </span>
                            </td>
                            <td class="text-end">{{ ($item->qty !== null && $item->unit_cost !== null) ? number_format($item->qty * $item->unit_cost, 2) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-info fw-bold">
                        <td colspan="7">TOTAL</td>
                        <td class="text-end">{{ number_format($purchaseOrder->items->sum(fn($i) => $i->qty * $i->unit_cost), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">Goods Receipts</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr class="table-header-bg">
                        <th>G.R. No.</th>
                        <th>Receipt Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrder->goodsReceipts as $goodsReceipt)
                        <tr>
                            <td>{{ $goodsReceipt->gr_no }}</td>
                            <td>{{ $goodsReceipt->receipt_date->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('goods-receipts.show', $goodsReceipt) }}" class="btn btn-sm btn-info">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">No Goods Receipts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

<style>
    .table-header-bg {
        background-color: #f7f8fa;
    }
    .table-info {
        background-color: #e7f3ff;
    }
</style>
@endsection
