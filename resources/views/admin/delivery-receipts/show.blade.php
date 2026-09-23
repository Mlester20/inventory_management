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
                                    <th class="text-end no-print" style="width: 110px;">Qty to Invoice</th>
                                    <th class="no-print" style="width: 120px;" title="Which of the customer's withholding tax rates applies to this line">Sale Type</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveryReceipt->items as $line)
                                @php
                                    $remaining = $line->remaining_invoiceable_qty;
                                    $fullyInvoiced = $remaining <= 0;
                                    // What the invoice will charge for this line, worked out the same way
                                    // DeliveryReceiptService does — used only for the withholding suggestion.
                                    $lineProduct = $line->productBatch->product;
                                    $linePrice = $line->salesOrderItem
                                        ? (float) $line->salesOrderItem->price
                                        : (float) ($lineProduct->{$deliveryReceipt->customer->priceColumn()} ?? $lineProduct->unit_price);
                                @endphp
                                <tr>
                                    @if(!$deliveryReceipt->isDraft())
                                        <td class="no-print">
                                            <input type="checkbox" class="form-check-input line-checkbox" name="line_ids[]" value="{{ $line->id }}"
                                                data-price="{{ $linePrice }}" data-class="{{ $lineProduct->taxClassification() }}"
                                                {{ $fullyInvoiced ? 'disabled' : '' }}>
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
                                        <td class="no-print">
                                            <input type="number" name="qty[{{ $line->id }}]" value="{{ $remaining }}"
                                                min="1" max="{{ $remaining }}" class="form-control form-control-sm text-end"
                                                {{ $fullyInvoiced ? 'disabled' : '' }}>
                                        </td>
                                        <td class="no-print">
                                            <select class="form-select form-select-sm wt-type-select" {{ $fullyInvoiced ? 'disabled' : '' }}>
                                                <option value="goods">Goods</option>
                                                <option value="services">Services</option>
                                            </select>
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
                    <div class="col-md-3">
                        <label class="text-muted small">Prepared By</label>
                        <p class="fw-bold mb-0">{{ $deliveryReceipt->preparedBy->name ?? '—' }}</p>
                    </div>
                    @if(!$deliveryReceipt->isDraft())
                        <div class="col-md-3 no-print">
                            <label for="dr_po_no" class="form-label small mb-1">Customer PO #</label>
                            <input type="text" name="po_no" id="dr_po_no" class="form-control form-control-sm"
                                value="{{ $deliveryReceipt->salesOrder?->po_no }}" placeholder="Optional">
                        </div>
                        <div class="col-md-3 no-print">
                            <label for="dr_less_wt" class="form-label small mb-1">Withholding Tax (₱)</label>
                            <input type="number" name="less_wt" id="dr_less_wt" class="form-control form-control-sm"
                                step="0.01" min="0" value="{{ old('less_wt') }}" placeholder="Optional">
                            <div class="form-text" id="drWtHint"></div>
                            <button type="button" class="btn btn-link btn-sm p-0 d-none" id="drApplyWtBtn">Use suggested amount</button>
                        </div>
                        <div class="col-md-3 text-md-end mt-3 mt-md-0 no-print">
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
        font-size: 0.75rem;
    }

    /* DR-only overrides — the classes below come from the shared
       partials.print.base-print (used by every document type), each with
       its own fixed rem size, so shrinking .dr-sheet's own font-size alone
       doesn't shrink them. Scoped under .dr-sheet so Sales Order/Invoice/
       Purchase Order printouts, which share the same base-print classes,
       are unaffected. */
    .dr-sheet .print-company-detail {
        font-size: 0.65rem;
    }

    .dr-sheet .print-doc-title {
        font-size: 1.4rem;
    }

    .dr-sheet .print-doc-page,
    .dr-sheet .print-doc-no-row {
        font-size: 0.68rem;
    }

    .dr-sheet .print-to-header {
        font-size: 0.72rem;
    }

    .dr-sheet .print-to-row {
        font-size: 0.68rem;
    }

    .dr-sheet .print-sig-label {
        font-size: 0.72rem;
    }

    .dr-sheet .print-sig-value {
        font-size: 0.68rem;
    }

    .dr-sheet .print-items-table th,
    .dr-sheet .print-items-table td {
        font-size: 0.7rem;
        padding: 0.2rem 0.35rem;
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

    // Withholding Tax suggestion for the checked lines (Sir's rule: net of VAT x the
    // customer's rate for that kind of sale). Only a pre-filled value — the field stays
    // editable, and once someone types in it their number is left alone (the link
    // brings the suggestion back). Offered only when every checked line is VATable:
    // how a mixed VAT / VAT-exempt invoice is worked out is still to be confirmed.
    const DR_CUSTOMER = @json([
        'wt_goods' => $deliveryReceipt->customer?->wt_rate_goods !== null ? (float) $deliveryReceipt->customer->wt_rate_goods : null,
        'wt_services' => $deliveryReceipt->customer?->wt_rate_services !== null ? (float) $deliveryReceipt->customer->wt_rate_services : null,
    ]);
    const DR_VAT_RATE = {{ \App\Models\Taxes::activeRate() }};
    const wtInput = document.getElementById('dr_less_wt');
    let wtDirty = wtInput ? parseFloat(wtInput.value) > 0 : false;

    function money(n) {
        return '₱' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function refreshWithholdingSuggestion() {
        if (!wtInput) {
            return;
        }
        const hint = document.getElementById('drWtHint');
        const applyBtn = document.getElementById('drApplyWtBtn');
        hint.textContent = '';
        applyBtn.classList.add('d-none');

        const gross = { goods: 0, services: 0 };
        let allVatable = true, checked = 0;
        document.querySelectorAll('.line-checkbox:checked').forEach(cb => {
            const row = cb.closest('tr');
            const qty = parseFloat(row.querySelector('input[type=number]').value) || 0;
            checked++;
            if (cb.dataset.class === 'vatable') {
                gross[row.querySelector('.wt-type-select').value === 'services' ? 'services' : 'goods'] += qty * parseFloat(cb.dataset.price);
            } else {
                allVatable = false;
            }
        });

        if ((DR_CUSTOMER.wt_goods === null && DR_CUSTOMER.wt_services === null) || checked === 0) {
            return;
        }
        if (!allVatable) {
            hint.textContent = 'Mixed VAT / VAT-exempt lines: enter the withholding tax by hand.';
            return;
        }

        const divisor = 1 + (DR_VAT_RATE / 100);
        let total = 0;
        const parts = [];
        [['goods', 'Goods', DR_CUSTOMER.wt_goods], ['services', 'Services', DR_CUSTOMER.wt_services]].forEach(([key, label, rate]) => {
            if (gross[key] <= 0) {
                return;
            }
            const net = gross[key] / divisor;
            const wt = rate === null ? 0 : Math.round(net * (rate / 100) * 100) / 100;
            total += wt;
            parts.push(rate === null ? `${label}: no rate set` : `${label} ${money(net)} x ${rate}%`);
        });

        const suggested = Math.round(total * 100) / 100;
        hint.textContent = `Suggested ${money(suggested)} (net of VAT x customer rate: ${parts.join(' + ')}).`;

        if (!wtDirty) {
            wtInput.value = suggested.toFixed(2);
        } else if (Math.abs((parseFloat(wtInput.value) || 0) - suggested) > 0.004) {
            applyBtn.classList.remove('d-none');
        }
    }

    document.querySelectorAll('.line-checkbox, .wt-type-select, #createInvoiceForm input[type=number][name^="qty"]').forEach(el => {
        el.addEventListener('change', refreshWithholdingSuggestion);
        el.addEventListener('input', refreshWithholdingSuggestion);
    });
    if (wtInput) {
        wtInput.addEventListener('input', () => {
            wtDirty = true;
            refreshWithholdingSuggestion();
        });
        document.getElementById('drApplyWtBtn').addEventListener('click', () => {
            wtDirty = false;
            refreshWithholdingSuggestion();
        });
    }
</script>
@endsection
