@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Delivery Receipt ' . $deliveryReceipt->dr_no)

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('delivery-receipts.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Delivery Receipts
        </a>
        <div class="d-flex gap-2">
            @if($deliveryReceipt->isDraft())
                <a href="{{ route('delivery-receipts.edit', $deliveryReceipt) }}" class="btn btn-primary">
                    <i class="bx bx-edit-alt"></i> Continue Editing
                </a>
                @if(Auth::user()->role === 'admin')
                    <form action="{{ route('delivery-receipts.destroy', $deliveryReceipt) }}" method="POST" onsubmit="return confirmSubmit(this, 'Delete draft {{ $deliveryReceipt->dr_no }}? This can be restored from the trash later.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bx bx-trash"></i> Delete Draft
                        </button>
                    </form>
                @endif
            @else
                @if($deliveryReceipt->status !== 'delivered' && ! $deliveryReceipt->isCancelled())
                    <form action="{{ route('delivery-receipts.mark-delivered', $deliveryReceipt) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="bx bx-check-circle"></i> Mark as Delivered
                        </button>
                    </form>
                @endif
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bx bx-printer"></i> Print
                </button>
                @if($deliveryReceipt->isArchived())
                    <form action="{{ route('delivery-receipts.unarchive', $deliveryReceipt) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="bx bx-undo"></i> Unarchive
                        </button>
                    </form>
                @else
                    <form action="{{ route('delivery-receipts.archive', $deliveryReceipt) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="bx bx-archive"></i> Archive
                        </button>
                    </form>
                @endif
                @if(Auth::user()->role === 'admin' && ! $deliveryReceipt->isCancelled())
                    <form action="{{ route('delivery-receipts.cancel', $deliveryReceipt) }}" method="POST" onsubmit="return confirmSubmit(this, 'Cancel/void this Delivery Receipt? The record and its stock history stay, only the status changes.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bx bx-block"></i> Cancel/Void
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    @if ($deliveryReceipt->cancellation_note)
        <div class="alert alert-warning">
            <i class="bx bx-error-circle me-1"></i> {{ $deliveryReceipt->cancellation_note }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger no-print">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('delivery-receipts.create-invoice', $deliveryReceipt) }}" method="POST" id="createInvoiceForm" class="no-print">
        @csrf
        <div class="card" id="printableDeliveryReceipt">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Delivery Note</h5>
                <div class="d-flex gap-2">
                    <span class="badge bg-{{ ['purchase_order' => 'info', 'walk_in' => 'warning'][$deliveryReceipt->transaction_type] ?? 'secondary' }}">
                        {{ \App\Models\DeliveryReceipt::TRANSACTION_TYPES[$deliveryReceipt->transaction_type] ?? ($deliveryReceipt->transaction_type ?? '—') }}
                    </span>
                    @if($deliveryReceipt->isDraft())
                        <span class="badge bg-secondary">DRAFT</span>
                    @else
                        <span class="badge bg-{{ $deliveryReceipt->status === 'delivered' ? 'success' : 'warning text-dark' }}">
                            {{ \App\Models\DeliveryReceipt::STATUSES[$deliveryReceipt->status] ?? $deliveryReceipt->status }}
                        </span>
                        @php $invoiceStatusColor = ['COMPLETE' => 'success', 'PARTIALLY INVOICED' => 'warning', 'NOT INVOICED' => 'secondary'][$deliveryReceipt->invoice_status] ?? 'secondary'; @endphp
                        <span class="badge bg-{{ $invoiceStatusColor }}">{{ $deliveryReceipt->invoice_status }}</span>
                    @endif
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
                        <p class="fw-bold mb-0">{{ $deliveryReceipt->customer->customer_name ?? '—' }}</p>
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
                                @if(!$deliveryReceipt->isDraft())
                                    <th class="no-print"></th>
                                @endif
                                <th>#</th>
                                <th>Generic Description</th>
                                <th>Item Description</th>
                                <th>Lot/Batch No.</th>
                                <th>Expiry Date</th>
                                <th>Remarks</th>
                                <th class="text-end">Qty</th>
                                <th>Unit</th>
                                @if(!$deliveryReceipt->isDraft())
                                    <th class="text-end">Invoiced</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveryReceipt->items as $line)
                                @php $fullyInvoiced = $line->invoiced_qty >= $line->qty; @endphp
                                <tr>
                                    @if(!$deliveryReceipt->isDraft())
                                        <td class="no-print">
                                            <input type="checkbox" class="form-check-input line-checkbox" name="line_ids[]" value="{{ $line->id }}" {{ $fullyInvoiced ? 'disabled' : '' }}>
                                        </td>
                                    @endif
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $line->productBatch->product->genericName->generic_name ?? '—' }}</td>
                                    <td>{{ $line->productBatch->product->item_name }}</td>
                                    <td>{{ $line->batch_no ?? '—' }}</td>
                                    <td>{{ $line->expiration_date ? $line->expiration_date->format('M d, Y') : '—' }}</td>
                                    <td style="white-space: pre-line;">{{ $line->remarks ?? '—' }}</td>
                                    <td class="text-end">{{ $line->qty ?? '—' }}</td>
                                    <td>{{ $line->productBatch->product->genericName->unit ?? '—' }}</td>
                                    @if(!$deliveryReceipt->isDraft())
                                        <td class="text-end">
                                            @php $invoiceForLine = $line->sales->first()?->invoice; @endphp
                                            @if($line->invoiced_qty > 0 && $invoiceForLine)
                                                <a href="{{ route('invoices.show', $invoiceForLine) }}">{{ $line->invoiced_qty }}</a>
                                            @else
                                                {{ $line->invoiced_qty }}
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(!$deliveryReceipt->isDraft())
                    <p class="text-muted small mb-3 no-print">
                        Check the lines to invoice, then click Create Invoice. A line's checkbox disables once it's fully invoiced.
                    </p>
                @endif

                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label class="text-muted small">Prepared By</label>
                        <p class="fw-bold mb-0">{{ $deliveryReceipt->preparedBy->name ?? '—' }}</p>
                    </div>
                    @if(!$deliveryReceipt->isDraft())
                        <div class="col-md-8 text-md-end mt-3 mt-md-0 no-print">
                            <button type="submit" class="btn btn-primary" id="createInvoiceBtn" disabled>
                                <i class="bx bx-receipt"></i> Create Invoice
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <div class="card dr-print-only" id="printableDeliveryNote">
        <div class="card-body p-4 dr-sheet">

            @include('partials.print.letterhead', [
                'docTitle' => 'DELIVERY RECEIPT',
                'docNoLabel' => 'D.R No.',
                'docNo' => $deliveryReceipt->dr_no,
                'docNoLabel2' => 'S.O No.',
                'docNo2' => $deliveryReceipt->salesOrder->so_no ?? null,
                'docDate' => $deliveryReceipt->receipt_date->format('m/d/Y'),
                'toHeader' => 'Deliver to',
                'toRows' => [
                    'Name' => $deliveryReceipt->customer->customer_name ?? '',
                    'Address' => $deliveryReceipt->customer->delivery_address ?? '',
                ],
            ])

            <div class="table-responsive">
                <table class="table table-bordered table-sm print-items-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th>Item Description</th>
                            <th style="width: 8%;">Unit</th>
                            <th style="width: 12%;">Lot/Batch No.</th>
                            <th style="width: 10%;">Expiry Date</th>
                            <th class="text-end" style="width: 7%;">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliveryReceipt->items as $line)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $line->productBatch->product->item_name }}</td>
                                <td class="text-center">{{ $line->productBatch->product->genericName->unit ?? '—' }}</td>
                                <td class="text-center">{{ $line->batch_no ?? '—' }}</td>
                                <td class="text-center">{{ $line->expiration_date ? $line->expiration_date->format('M d, Y') : '—' }}</td>
                                <td class="text-end">{{ $line->qty ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('partials.print.signature-block', [
                'label1' => 'Prepared By', 'value1' => $deliveryReceipt->preparedBy->name ?? '—',
                'label2' => 'Inspected By',
                'label3' => 'Received By',
            ])
        </div>
    </div>

<style>
    @include('partials.print.base-print')

    .table-header-bg {
        background-color: #f7f8fa;
    }

    .dr-print-only {
        display: none;
    }

    .dr-sheet {
        font-size: 0.85rem;
    }

    @media print {
        #printableDeliveryReceipt {
            box-shadow: none !important;
            border: none !important;
        }

        .dr-print-only {
            display: block !important;
        }

        #printableDeliveryNote {
            box-shadow: none !important;
            border: none !important;
        }

        #printableDeliveryNote .card-body {
            padding: 0 !important;
        }

        .table-header-bg {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
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
