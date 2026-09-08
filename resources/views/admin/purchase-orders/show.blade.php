@extends('layout.app')

@section('title', 'Purchase Order ' . $purchaseOrder->po_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Purchase Orders
        </a>
        @if($purchaseOrder->isDraft())
            <div class="d-flex gap-2">
                <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="btn btn-primary">
                    <i class="bx bx-edit-alt"></i> Continue Editing
                </a>
                @if(Auth::user()->role === 'admin')
                    <form action="{{ route('purchase-orders.destroy', $purchaseOrder) }}" method="POST" onsubmit="return confirm('Delete draft {{ $purchaseOrder->po_no }}? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bx bx-trash"></i> Delete Draft
                        </button>
                    </form>
                @endif
            </div>
        @else
            <div class="d-flex gap-2">
                @if($purchaseOrder->status !== 'completed' && $purchaseOrder->status !== 'cancelled')
                    <a href="{{ route('goods-receipts.create', ['purchase_order_id' => $purchaseOrder->id]) }}" class="btn btn-primary">
                        <i class="bx bx-plus"></i> Create Goods Receipt
                    </a>
                @endif
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bx bx-printer"></i> Print
                </button>
            </div>
        @endif
    </div>

    <div id="printablePurchaseOrder" class="no-print">
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
    </div>

    <div class="card po-print-only" id="printablePurchaseOrderSheet">
        <div class="card-body p-4 po-sheet">

            @include('partials.print.letterhead', [
                'docTitle' => 'PURCHASE ORDER',
                'docNoLabel' => 'S.P.O No.',
                'docNo' => $purchaseOrder->po_no,
                'docDate' => $purchaseOrder->order_date->format('m/d/Y'),
                'toHeader' => 'Supplier',
                'toRows' => [
                    'Name' => $purchaseOrder->supplier->supplier_name ?? '—',
                    'Address' => $purchaseOrder->supplier->delivery_address ?? '—',
                ],
            ])

            <div class="table-responsive">
                <table class="table table-bordered table-sm print-items-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th>Generic Description</th>
                            <th>Remarks</th>
                            <th style="width: 7%;">Unit</th>
                            <th class="text-end" style="width: 7%;">Qty</th>
                            <th class="text-end" style="width: 10%;">Unit Cost</th>
                            <th class="text-end" style="width: 12%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchaseOrder->items as $item)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $item->product->item_name ?? $item->genericName->generic_name ?? '—' }}</td>
                                <td>{{ $item->remarks ?? '—' }}</td>
                                <td class="text-center">{{ $item->unit ?? '—' }}</td>
                                <td class="text-end">{{ $item->qty ?? '—' }}</td>
                                <td class="text-end">{{ $item->unit_cost !== null ? number_format($item->unit_cost, 2) : '—' }}</td>
                                <td class="text-end">{{ ($item->qty !== null && $item->unit_cost !== null) ? number_format($item->qty * $item->unit_cost, 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="print-total-row">
                            <td colspan="6" class="text-end fw-bold">TOTAL</td>
                            <td class="text-end fw-bold">₱{{ number_format($purchaseOrder->items->sum(fn($i) => ($i->qty ?? 0) * ($i->unit_cost ?? 0)), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="print-note-box mb-2">
                <div class="print-note-label">Note:</div>
            </div>

            @include('partials.print.signature-block', [
                'label1' => 'Prepared By', 'value1' => $purchaseOrder->preparedBy->name ?? '—',
                'label2' => 'Approved By',
                'columns' => 2,
            ])
        </div>
    </div>

<style>
    @include('partials.print.base-print')

    .table-header-bg {
        background-color: #f7f8fa;
    }
    .table-info {
        background-color: #e7f3ff;
    }

    .po-print-only {
        display: none;
    }

    .po-sheet {
        font-size: 0.85rem;
    }

    @media print {
        #printablePurchaseOrderSheet {
            box-shadow: none !important;
            border: none !important;
        }

        #printablePurchaseOrderSheet .card-body {
            padding: 0 !important;
        }

        .po-print-only {
            display: block !important;
        }

        .table-header-bg,
        .table-info {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
@endsection
