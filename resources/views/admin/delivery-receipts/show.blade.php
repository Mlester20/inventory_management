@extends('layout.app')

@section('title', 'Delivery Receipt ' . $deliveryReceipt->dr_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <a href="{{ route('delivery-receipts.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Delivery Receipts
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
                        <span class="badge bg-{{ ['purchase_order' => 'info', 'walk_in' => 'warning'][$deliveryReceipt->transaction_type] ?? 'secondary' }}">
                            {{ \App\Models\DeliveryReceipt::TRANSACTION_TYPES[$deliveryReceipt->transaction_type] ?? $deliveryReceipt->transaction_type }}
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
                <div class="col-md-3">
                    <label class="text-muted small">Delivery Status</label>
                    <p class="mb-0"><span class="badge bg-success">{{ $deliveryReceipt->deliveryStatus() }}</span></p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Invoice Status</label>
                    @php $invoiceStatusColor = ['COMPLETE' => 'success', 'PARTIALLY INVOICED' => 'warning', 'NOT INVOICED' => 'secondary'][$deliveryReceipt->invoice_status] ?? 'secondary'; @endphp
                    <p class="mb-0"><span class="badge bg-{{ $invoiceStatusColor }}">{{ $deliveryReceipt->invoice_status }}</span></p>
                </div>
            </div>
            @if($deliveryReceipt->description)
                <div class="row mt-2">
                    <div class="col-12">
                        <label class="text-muted small">Description</label>
                        <p class="mb-0">{{ $deliveryReceipt->description }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <form action="{{ route('delivery-receipts.create-invoice', $deliveryReceipt) }}" method="POST" id="createInvoiceForm">
        @csrf
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Delivered Items</h5>
                <button type="submit" class="btn btn-primary btn-sm" id="createInvoiceBtn" disabled>
                    <i class="bx bx-receipt"></i> Create Invoice
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr class="table-header-bg">
                            <th></th>
                            <th>Item</th>
                            <th>Batch No.</th>
                            <th>Expiry</th>
                            <th>Remarks</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Invoiced</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliveryReceipt->items as $line)
                            @php $fullyInvoiced = $line->invoiced_qty >= $line->qty; @endphp
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input line-checkbox" name="line_ids[]" value="{{ $line->id }}" {{ $fullyInvoiced ? 'disabled' : '' }}>
                                </td>
                                <td>{{ $line->productBatch->product->item_name }}</td>
                                <td>{{ $line->batch_no ?? '—' }}</td>
                                <td>{{ $line->expiration_date ? $line->expiration_date->format('M d, Y') : '—' }}</td>
                                <td>{{ $line->remarks ?? '—' }}</td>
                                <td class="text-end">{{ $line->qty }}</td>
                                <td class="text-end">
                                    @php $invoiceForLine = $line->sales->first()?->invoice; @endphp
                                    @if($line->invoiced_qty > 0 && $invoiceForLine)
                                        <a href="{{ route('invoices.show', $invoiceForLine) }}">{{ $line->invoiced_qty }}</a>
                                    @else
                                        {{ $line->invoiced_qty }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-muted small">
                Check the lines to invoice, then click Create Invoice. A line's checkbox disables once it's fully invoiced.
            </div>
        </div>
    </form>

<style>
    .table-header-bg {
        background-color: #f7f8fa;
    }
</style>
@endsection

@section('scripts')
<script>
    function refreshCreateInvoiceBtn() {
        const anyChecked = document.querySelectorAll('.line-checkbox:checked').length > 0;
        document.getElementById('createInvoiceBtn').disabled = !anyChecked;
    }

    document.querySelectorAll('.line-checkbox').forEach(cb => {
        cb.addEventListener('change', refreshCreateInvoiceBtn);
    });
</script>
@endsection
