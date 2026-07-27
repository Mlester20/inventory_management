@extends(Auth::user()->role === 'admin' ? 'layout.app' : 'layout.user')

@section('title', 'Delivery Receipt ' . $deliveryReceipt->dr_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <a href="{{ route('delivery-receipts.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Delivery Receipts
        </a>
        @if($deliveryReceipt->status !== 'delivered')
            <form action="{{ route('delivery-receipts.mark-delivered', $deliveryReceipt) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="bx bx-check-circle"></i> Mark as Delivered
                </button>
            </form>
        @endif
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

    <form action="{{ route('delivery-receipts.create-invoice', $deliveryReceipt) }}" method="POST" id="createInvoiceForm">
        @csrf
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Delivery Note</h5>
                <div class="d-flex gap-2">
                    <span class="badge bg-{{ ['purchase_order' => 'info', 'walk_in' => 'warning'][$deliveryReceipt->transaction_type] ?? 'secondary' }}">
                        {{ \App\Models\DeliveryReceipt::TRANSACTION_TYPES[$deliveryReceipt->transaction_type] ?? $deliveryReceipt->transaction_type }}
                    </span>
                    <span class="badge bg-{{ $deliveryReceipt->status === 'delivered' ? 'success' : 'warning text-dark' }}">
                        {{ \App\Models\DeliveryReceipt::STATUSES[$deliveryReceipt->status] ?? $deliveryReceipt->status }}
                    </span>
                    @php $invoiceStatusColor = ['COMPLETE' => 'success', 'PARTIALLY INVOICED' => 'warning', 'NOT INVOICED' => 'secondary'][$deliveryReceipt->invoice_status] ?? 'secondary'; @endphp
                    <span class="badge bg-{{ $invoiceStatusColor }}">{{ $deliveryReceipt->invoice_status }}</span>
                </div>
            </div>

            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="text-muted small">Delivery Date</label>
                        <p class="fw-bold mb-0">{{ $deliveryReceipt->receipt_date->format('M d, Y') }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Reference</label>
                        <p class="fw-bold mb-0">{{ $deliveryReceipt->dr_no }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Customer</label>
                        <p class="fw-bold mb-0">{{ $deliveryReceipt->customer->customer_name }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Sales Order Number</label>
                        <p class="mb-0">
                            @if($deliveryReceipt->salesOrder)
                                <a href="{{ route('sales-orders.show', $deliveryReceipt->salesOrder) }}">{{ $deliveryReceipt->salesOrder->so_no }}</a>
                            @else
                                —
                            @endif
                        </p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Delivery Address</label>
                        <p class="mb-0" style="white-space: pre-line;">{{ $deliveryReceipt->customer->delivery_address ?? '—' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Description</label>
                        <p class="mb-0">{{ $deliveryReceipt->description ?? '—' }}</p>
                    </div>
                </div>

                <hr>

                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle">
                        <thead>
                            <tr class="table-header-bg">
                                <th></th>
                                <th>#</th>
                                <th>Generic Description</th>
                                <th>Item Description</th>
                                <th>Lot/Batch No.</th>
                                <th>Expiry Date</th>
                                <th>Remarks</th>
                                <th class="text-end">Qty</th>
                                <th>Unit</th>
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
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $line->productBatch->product->genericName->generic_name ?? '—' }}</td>
                                    <td>{{ $line->productBatch->product->item_name }}</td>
                                    <td>{{ $line->batch_no ?? '—' }}</td>
                                    <td>{{ $line->expiration_date ? $line->expiration_date->format('M d, Y') : '—' }}</td>
                                    <td>{{ $line->remarks ?? '—' }}</td>
                                    <td class="text-end">{{ $line->qty }}</td>
                                    <td>{{ $line->productBatch->product->genericName->unit ?? '—' }}</td>
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
                <p class="text-muted small mb-3">
                    Check the lines to invoice, then click Create Invoice. A line's checkbox disables once it's fully invoiced.
                </p>

                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label class="text-muted small">Prepared By</label>
                        <p class="fw-bold mb-0">{{ $deliveryReceipt->preparedBy->name ?? '—' }}</p>
                    </div>
                    <div class="col-md-8 text-md-end mt-3 mt-md-0">
                        <button type="submit" class="btn btn-primary" id="createInvoiceBtn" disabled>
                            <i class="bx bx-receipt"></i> Create Invoice
                        </button>
                    </div>
                </div>
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
