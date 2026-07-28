@extends(Auth::user()->role === 'admin' ? 'layout.app' : 'layout.user')

@section('title', 'Sales Order ' . $salesOrder->so_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('sales-orders.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Sales Orders
        </a>
        <div class="d-flex gap-2">
            @if($salesOrder->status !== 'completed' && $salesOrder->status !== 'cancelled')
                <a href="{{ route('delivery-receipts.create', ['sales_order_id' => $salesOrder->id]) }}" class="btn btn-primary">
                    <i class="bx bx-plus"></i> Create Delivery Receipt
                </a>
            @endif
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                <i class="bx bx-printer"></i> Print
            </button>
        </div>
    </div>

    <div id="printableSalesOrder">
    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="text-muted small">S.O. No.</label>
                    <p class="fw-bold mb-0">{{ $salesOrder->so_no }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Customer</label>
                    <p class="fw-bold mb-0">{{ $salesOrder->customer->customer_name }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Customer P.O. #</label>
                    <p class="fw-bold mb-0">{{ $salesOrder->po_no ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Status</label>
                    <p class="mb-0">
                        <span class="badge bg-{{ ['open' => 'warning', 'partially_delivered' => 'info', 'completed' => 'success', 'cancelled' => 'danger'][$salesOrder->status] ?? 'secondary' }}">
                            {{ ucfirst(str_replace('_', ' ', $salesOrder->status)) }}
                        </span>
                    </p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Order Date</label>
                    <p class="mb-0">{{ $salesOrder->order_date->format('M d, Y') }}</p>
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
                        <th>Generic Description</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Advance Qty</th>
                        <th class="text-end">Delivered</th>
                        <th class="text-end">Remaining</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($salesOrder->items as $item)
                        <tr>
                            <td>{{ $item->genericName->generic_name }} ({{ $item->genericName->unit }})</td>
                            <td class="text-end">{{ $item->qty }}</td>
                            <td class="text-end">{{ number_format($item->price, 2) }}</td>
                            <td class="text-end">{{ $item->advance_order_qty }}</td>
                            <td class="text-end">{{ $item->delivered_qty }}</td>
                            <td class="text-end">
                                <span class="badge bg-{{ $item->remaining_qty > 0 ? 'warning' : 'success' }}">
                                    {{ $item->remaining_qty }}
                                </span>
                            </td>
                            <td class="text-end">{{ number_format($item->qty * $item->price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-info fw-bold">
                        <td colspan="6">TOTAL</td>
                        <td class="text-end">{{ number_format($salesOrder->items->sum(fn($i) => $i->qty * $i->price), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="border-top pt-1">Prepared By: {{ $salesOrder->preparedBy->name ?? '—' }}</div>
        </div>
    </div>
    </div>

    <div class="card no-print">
        <h5 class="card-header">Delivery Receipts</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr class="table-header-bg">
                        <th>D.R. No.</th>
                        <th>Receipt Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesOrder->deliveryReceipts as $deliveryReceipt)
                        <tr>
                            <td>{{ $deliveryReceipt->dr_no }}</td>
                            <td>{{ $deliveryReceipt->receipt_date->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('delivery-receipts.show', $deliveryReceipt) }}" class="btn btn-sm btn-info">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">No Delivery Receipts yet.</td>
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

        #printableSalesOrder .card {
            box-shadow: none !important;
            border: none !important;
        }

        table,
        tr {
            page-break-inside: avoid;
        }

        .table-header-bg,
        .table-info {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
@endsection
