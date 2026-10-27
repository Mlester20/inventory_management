@extends(in_array(Auth::user()->role, ['admin', 'admin_staff', 'user'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Delivery Receipt ' . $deliveryReceipt->dr_no)

@section('content')
    @php $returnedByProduct = $deliveryReceipt->returnedByProduct(); @endphp
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('delivery-receipts.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Delivery Receipts
        </a>
        <div class="d-flex gap-2 align-items-center">
            @if($deliveryReceipt->isDraft())
                <a href="{{ route('delivery-receipts.edit', $deliveryReceipt) }}" class="btn btn-primary">
                    <i class="bx bx-edit-alt"></i> Continue Editing
                </a>
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
            @endif
            @php
                $isAdmin = Auth::user()->role === 'admin';
                $hasMoreActions = $deliveryReceipt->isDraft() ? $isAdmin : true;
            @endphp
            @if($hasMoreActions)
                <div class="dropdown">
                    <button type="button" class="btn btn-outline-secondary btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions">
                        <i class="bx bx-dots-vertical-rounded"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        @if($deliveryReceipt->isDraft())
                            <form action="{{ route('delivery-receipts.destroy', $deliveryReceipt) }}" method="POST" class="m-0" onsubmit="return confirmSubmit(this, 'Delete draft {{ $deliveryReceipt->dr_no }}? This can be restored from the trash later.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bx bx-trash me-1"></i> Delete Draft
                                </button>
                            </form>
                        @else
                            @if($canRevise)
                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#reviseModal">
                                    <i class="bx bx-edit me-1"></i> Revise
                                </button>
                            @endif
                            @if($canLink)
                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#linkSalesOrderModal">
                                    <i class="bx bx-link me-1"></i> Link to Sales Order
                                </button>
                            @endif
                            @if($deliveryReceipt->isArchived())
                                <form action="{{ route('delivery-receipts.unarchive', $deliveryReceipt) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="bx bx-undo me-1"></i> Unarchive
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('delivery-receipts.archive', $deliveryReceipt) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="bx bx-archive me-1"></i> Archive
                                    </button>
                                </form>
                            @endif
                            @if($isAdmin && ! $deliveryReceipt->isCancelled())
                                <div class="dropdown-divider"></div>
                                <form action="{{ route('delivery-receipts.cancel', $deliveryReceipt) }}" method="POST" class="m-0" onsubmit="return confirmSubmit(this, 'Cancel/void this Delivery Receipt? The record and its stock history stay, only the status changes.');">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bx bx-block me-1"></i> Cancel/Void
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
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
                        <p class="fw-bold mb-0">{{ $deliveryReceipt->deliverTo()?->customer_name ?? '—' }}</p>
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
                        <p class="mb-0" style="white-space: pre-line;">{{ $deliveryReceipt->deliverTo()?->delivery_address ?? '—' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Description</label>
                        <p class="mb-0">{{ $deliveryReceipt->description ?? '—' }}</p>
                    </div>
                </div>

                @if($revisions->isNotEmpty())
                    <div class="border rounded p-2 mb-3 small">
                        <strong>Revised after posting</strong> — the quantities below are the corrected ones.
                        @foreach($revisions as $revision)
                            <div>
                                {{ $revision->created_at->format('Y-m-d') }}{{ $revision->user ? ' by ' . $revision->user->name : '' }}:
                                {{ collect($revision->metadata['changes'] ?? [])->map(fn ($c) => $c['item'] . ' ' . $c['from'] . ' → ' . $c['to'])->implode('; ') }}.
                                @if(! empty($revision->metadata['reason']))Reason: {{ $revision->metadata['reason'] }}.@endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($deliveryReceipt->splitFrom)
                    @php
                        $parent = $deliveryReceipt->splitFrom;
                        $parentLeft = $parent->items->groupBy('product_batch_id')->map(fn ($rows) => $rows->sum('qty'));
                    @endphp
                    <div class="border rounded p-2 mb-3 small">
                        <strong>Split from {{ $parent->dr_no }}</strong> ({{ $deliveryReceipt->created_at->format('M d, Y') }}) — this receipt carries the part linked to
                        {{ $deliveryReceipt->salesOrder->so_no ?? 'the Sales Order' }}. Original quantities:
                        {{ $deliveryReceipt->items->groupBy('product_batch_id')->map(function ($rows, $batchId) use ($parentLeft, $parent) {
                            $here = $rows->sum('qty');
                            $left = $parentLeft[$batchId] ?? 0;
                            return $rows->first()->productBatch->product->display_description . ': ' . ($here + $left)
                                . ' (' . $here . ' here' . ($left ? ', ' . $left . ' left on ' . $parent->dr_no : '') . ')';
                        })->implode('; ') }}.
                    </div>
                @endif

                @if($deliveryReceipt->splitChildren->isNotEmpty())
                    <div class="border rounded p-2 mb-3 small">
                        <strong>Part of this receipt was split off</strong> — the quantities below are what remains here.
                        @foreach($deliveryReceipt->splitChildren as $child)
                            Moved to {{ $child->dr_no }} ({{ $child->salesOrder->so_no ?? '—' }}, {{ $child->created_at->format('M d, Y') }}):
                            {{ $child->items->groupBy('product_batch_id')->map(fn ($rows) => $rows->first()->productBatch->product->display_description . ' ' . $rows->sum('qty'))->implode(', ') }}{{ ! $loop->last ? ';' : '.' }}
                        @endforeach
                    </div>
                @endif

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
                                                data-price="{{ $linePrice }}" data-class="{{ $lineProduct->taxClassification() }}" data-type="{{ $lineProduct->genericName?->product_type ?? 'goods' }}"
                                                {{ $fullyInvoiced ? 'disabled' : '' }}>
                                        </td>
                                    @endif
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $line->productBatch->product->genericName->generic_name ?? '—' }}</td>
                                    <td>{{ $line->productBatch->product->display_description }}{{ isset($returnedByProduct[$line->productBatch->product_id]) ? ' *' : '' }}</td>
                                    <td>{{ $line->batch_no ?? '—' }}</td>
                                    <td>{{ $line->expiration_date ? $line->expiration_date->format('Y-m-d') : '—' }}</td>
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
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(!$deliveryReceipt->isDraft())
                    <p class="text-muted small mb-3 no-print">
                        Check the lines to invoice, then click Create Invoice. A line's checkbox disables once it's fully invoiced.
                        @include('partials.returned-note', ['returned' => $returnedByProduct])
                    </p>
                @endif

                {{-- Fields hang from the top so Customer PO # and Withholding Tax line up even though
                     the Withholding Tax block is taller (checkbox + hint); the button sits on the input line. --}}
                <div class="row align-items-start">
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
                            @include('admin.partials.prepared-by-select', ['id' => 'dr_invoice_prepared_by', 'selected' => $deliveryReceipt->prepared_by ?? auth()->id()])
                        </div>
                        <div class="col-md-3 no-print">
                            <label for="dr_less_wt" class="form-label small mb-1">Withholding Tax (₱)</label>
                            <input type="number" name="less_wt" id="dr_less_wt" class="form-control form-control-sm"
                                step="0.01" min="0" value="{{ old('less_wt') }}" placeholder="Optional">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="personal_use" id="dr_personal_use" value="1">
                                <label class="form-check-label small" for="dr_personal_use">Personal use (not covered by withholding tax)</label>
                            </div>
                            <div class="form-text" id="drWtHint"></div>
                            <button type="button" class="btn btn-link btn-sm p-0 d-none" id="drApplyWtBtn">Use suggested amount</button>
                        </div>
                        <div class="col-md-3 text-md-end mt-3 mt-md-0 pt-md-4 no-print">
                            <button type="submit" class="btn btn-primary" id="createInvoiceBtn" disabled>
                                <i class="bx bx-receipt"></i> Create Invoice
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </form>

    @if($canRevise)
        <div class="modal fade no-print" id="reviseModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form action="{{ route('delivery-receipts.revise', $deliveryReceipt) }}" method="POST" id="reviseForm">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Revise {{ $deliveryReceipt->dr_no }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">
                                For a receipt that was posted before the goods were picked — an item is missing, or short. Lower the quantity
                                (or set it to 0 to take the line off). What comes off goes back to the Warehouse stock it was taken from, and
                                if the line belongs to a Sales Order, that order's delivered quantity goes down too, so the order is open for it again.
                                A line can't go below what is already invoiced.
                            </p>
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr class="table-header-bg">
                                        <th>Item Description</th>
                                        <th class="text-end">Qty now</th>
                                        <th class="text-end">Invoiced</th>
                                        <th style="width: 120px;">New qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($deliveryReceipt->items as $line)
                                        <tr>
                                            <td>
                                                {{ $line->productBatch->product->display_description }}
                                                @if($line->batch_no)<span class="text-muted small d-block">Lot {{ $line->batch_no }}</span>@endif
                                            </td>
                                            <td class="text-end">{{ $line->qty }}</td>
                                            <td class="text-end">{{ $line->invoiced_qty }}</td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm text-end revise-qty" name="new_qty[{{ $line->id }}]"
                                                    value="{{ $line->qty }}" min="{{ $line->invoiced_qty }}" max="{{ $line->qty }}" data-current="{{ $line->qty }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div id="reviseSummary" class="small mb-3 text-muted">No change yet.</div>
                            <label for="reviseReason" class="form-label">Reason</label>
                            <textarea name="reason" id="reviseReason" class="form-control" rows="2" maxlength="255"
                                placeholder="e.g. Item not available at picking" required></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning" id="reviseSubmit" disabled>Revise Delivery Receipt</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($canLink)
        <div class="modal fade no-print" id="linkSalesOrderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form action="{{ route('delivery-receipts.link-sales-order', $deliveryReceipt) }}" method="POST" id="linkSalesOrderForm">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Link {{ $deliveryReceipt->dr_no }} to a Sales Order</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            @if(empty($linkCandidates))
                                <div class="alert alert-info mb-0">
                                    {{ $deliveryReceipt->customer->customer_name }} has no open Sales Order to link to.
                                </div>
                            @else
                                <p class="text-muted small">
                                    This Delivery Receipt was posted without a Sales Order, so the order's balance and status never caught up.
                                    Linking records what it already delivered against the order — no stock moves, since it was deducted when the receipt was posted.
                                    What you link becomes a Purchase Order delivery (invoicing it uses the Sales Order's price). If anything is left unlinked,
                                    the linked part moves to a new Delivery Receipt and the rest stays here as an Advance Order, free to be linked to another order later.
                                </p>
                                <div class="mb-3">
                                    <label for="linkSalesOrderSelect" class="form-label">Sales Order ({{ $deliveryReceipt->customer->customer_name }})</label>
                                    <select name="sales_order_id" id="linkSalesOrderSelect" class="form-select" required>
                                        <option value="">-- Select Sales Order --</option>
                                        @foreach($linkCandidates as $candidate)
                                            <option value="{{ $candidate['id'] }}">{{ $candidate['so_no'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div id="linkLinesWrap" class="d-none">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr class="table-header-bg">
                                                <th>Delivery Receipt line</th>
                                                <th class="text-end">Qty</th>
                                                <th style="width: 38%;">Sales Order line</th>
                                                <th style="width: 110px;">Qty to link</th>
                                            </tr>
                                        </thead>
                                        <tbody id="linkLinesBody"></tbody>
                                    </table>
                                    <div id="linkSummary" class="small"></div>
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            @if(! empty($linkCandidates))
                                <button type="submit" class="btn btn-primary" id="linkSubmitBtn" disabled>Link Delivery Receipt</button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @php
        $customerReturns = $deliveryReceipt->customerReturns()->with('lines.product')->latest('id')->get();
        $returnItems = $deliveryReceipt->returnItems()->where('is_draft', false)->with('productBatch.product')->latest('id')->get();
    @endphp
    @if($customerReturns->isNotEmpty() || $returnItems->isNotEmpty())
        <div class="card mb-4 no-print">
            <h6 class="card-header">Returned by the customer</h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>Reference</th><th>Date</th><th>Item</th><th class="text-end">Qty</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($returnItems as $returnItem)
                            <tr>
                                <td><a href="{{ route('return-items.index') }}">{{ $returnItem->reference }}</a> <span class="badge bg-secondary">{{ ucfirst($returnItem->status) }}</span></td>
                                <td>{{ $returnItem->return_date ? \Illuminate\Support\Carbon::parse($returnItem->return_date)->format('Y-m-d') : '—' }}</td>
                                <td>{{ $returnItem->productBatch?->product?->display_description ?? '—' }}</td>
                                <td class="text-end">{{ $returnItem->quantity }}</td>
                            </tr>
                        @endforeach
                        @foreach ($customerReturns as $return)
                            @foreach ($return->lines as $returnLine)
                                <tr>
                                    <td><a href="{{ route('inventory-adjustments.show', $return) }}">{{ $return->adjustment_no }}</a></td>
                                    <td>{{ $return->adjustment_date->format('Y-m-d') }}</td>
                                    <td>{{ $returnLine->product->display_description ?? '—' }}</td>
                                    <td class="text-end">{{ $returnLine->qty }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card dr-print-only" id="printableDeliveryNote">
        <div class="card-body p-4 dr-sheet">

            {{-- The letterhead sits in a header group of an outer table, so the printer repeats it
                 (logo, company, DR no., Deliver to) at the top of every page the items run onto. --}}
            <table class="print-repeat-wrap">
            <thead><tr><td>
            @include('partials.print.letterhead', [
                'docTitle' => 'DELIVERY RECEIPT',
                'docNoLabel' => 'D.R No.',
                'docNo' => $deliveryReceipt->dr_no,
                'docNoLabel2' => 'S.O No.',
                'docNo2' => $deliveryReceipt->salesOrder->so_no ?? null,
                'docDate' => $deliveryReceipt->receipt_date->format('m/d/Y'),
                'toHeader' => 'Deliver to',
                'toRows' => [
                    'Name' => $deliveryReceipt->deliverTo()?->customer_name ?? '',
                    'Address' => $deliveryReceipt->deliverTo()?->delivery_address ?? '',
                ],
            ])
            </td></tr></thead>
            <tbody><tr><td>

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
                                <td>{{ $line->productBatch->product->display_description }}{{ isset($returnedByProduct[$line->productBatch->product_id]) ? ' *' : '' }}</td>
                                <td class="text-center">{{ $line->productBatch->product->genericName->unit ?? '—' }}</td>
                                <td class="text-center">{{ $line->batch_no ?? '—' }}</td>
                                <td class="text-center">{{ $line->expiration_date ? $line->expiration_date->format('Y-m-d') : '—' }}</td>
                                <td class="text-end">{{ $line->qty ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('partials.returned-note', ['returned' => $returnedByProduct])

            @include('partials.print.signature-block', [
                'label1' => 'Prepared By', 'value1' => $deliveryReceipt->preparedBy->name ?? '—',
                'label2' => 'Inspected By',
                'label3' => 'Received By',
            ])
            </td></tr></tbody>
            </table>
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
@include('admin.partials.withholding-tax-script')
@if($canRevise)
<script>
    (function () {
        const inputs = [...document.querySelectorAll('.revise-qty')];
        const reason = document.getElementById('reviseReason');
        const summary = document.getElementById('reviseSummary');
        const submit = document.getElementById('reviseSubmit');

        function refresh() {
            let returned = 0;
            let changed = 0;
            let kept = 0;
            let valid = true;

            inputs.forEach(input => {
                const current = parseInt(input.dataset.current, 10);
                const min = parseInt(input.min, 10) || 0;
                const value = input.value === '' ? current : parseInt(input.value, 10);
                const bad = isNaN(value) || value < min || value > current;
                input.classList.toggle('is-invalid', bad);
                if (bad) { valid = false; return; }
                if (value > 0) kept++;
                if (value !== current) { changed++; returned += current - value; }
            });

            if (!valid) {
                summary.className = 'small mb-3 text-danger';
                summary.textContent = 'A quantity is out of range (it can only go down, and not below what is already invoiced).';
            } else if (changed === 0) {
                summary.className = 'small mb-3 text-muted';
                summary.textContent = 'No change yet.';
            } else if (kept === 0) {
                valid = false;
                summary.className = 'small mb-3 text-danger';
                summary.textContent = 'The receipt must keep at least one line. To void the whole receipt, use Cancel/Void.';
            } else {
                summary.className = 'small mb-3 text-success';
                summary.textContent = changed + ' line(s) changed — ' + returned + ' unit(s) go back to the Warehouse.';
            }

            submit.disabled = !(valid && changed > 0 && reason.value.trim() !== '');
        }

        inputs.forEach(input => input.addEventListener('input', refresh));
        reason.addEventListener('input', refresh);
        refresh();
    })();
</script>
@endif
@if($canLink && ! empty($linkCandidates))
@php
    $linkDrLines = $deliveryReceipt->items->map(function ($line) {
        return [
            'id' => $line->id,
            'label' => $line->productBatch->product->display_description . ($line->batch_no ? ' — Lot ' . $line->batch_no : ''),
            'generic_name_id' => $line->productBatch->product->generic_name_id,
            'qty' => $line->qty,
            'invoiced' => $line->invoiced_qty,
        ];
    })->values();
@endphp
<script>
    (function () {
        const SALES_ORDERS = @json($linkCandidates);
        const DR_LINES = @json($linkDrLines);

        const select = document.getElementById('linkSalesOrderSelect');
        const wrap = document.getElementById('linkLinesWrap');
        const body = document.getElementById('linkLinesBody');
        const summary = document.getElementById('linkSummary');
        const submitBtn = document.getElementById('linkSubmitBtn');

        function rowQty(tr) {
            return parseInt(tr.querySelector('.link-qty').value, 10) || 0;
        }

        function recalc(so) {
            const totals = {};
            let anyLinked = false;
            let ok = true;
            const notes = [];

            body.querySelectorAll('tr').forEach(tr => {
                const sel = tr.querySelector('select.link-so-item');
                const qtyInput = tr.querySelector('.link-qty');
                const line = DR_LINES.find(l => String(l.id) === tr.dataset.line);
                const qty = rowQty(tr);
                qtyInput.disabled = !sel.value;
                if (!sel.value) {
                    qtyInput.value = '';
                    return;
                }
                anyLinked = true;
                if (qty < 1 || qty > line.qty) {
                    ok = false;
                    notes.push('<div class="text-danger">' + line.label + ': qty to link must be between 1 and ' + line.qty + '.</div>');
                    return;
                }
                if (qty < line.qty && line.qty - qty < line.invoiced) {
                    ok = false;
                    notes.push('<div class="text-danger">' + line.label + ': ' + line.invoiced + ' already invoiced, so at most ' + (line.qty - line.invoiced) + ' can be linked separately.</div>');
                    return;
                }
                totals[sel.value] = (totals[sel.value] || 0) + qty;
                if (qty < line.qty) {
                    notes.push('<div class="text-muted">' + line.label + ': ' + qty + ' linked, ' + (line.qty - qty) + ' stays on this Advance Order receipt.</div>');
                }
            });

            const leftover = DR_LINES.some(line => {
                const tr = body.querySelector('tr[data-line="' + line.id + '"]');
                const sel = tr.querySelector('select.link-so-item');
                return !sel.value || rowQty(tr) < line.qty;
            });
            if (anyLinked && leftover) {
                notes.push('<div class="text-muted">A new Delivery Receipt will be created for what you link. The rest stays on {{ $deliveryReceipt->dr_no }}.</div>');
            }

            so.items.forEach(item => {
                const linked = totals[item.id] || 0;
                if (!linked) return;
                const over = linked > item.remaining;
                if (over) ok = false;
                notes.unshift('<div class="' + (over ? 'text-danger' : 'text-success') + '">' + item.generic_name + ': linking ' + linked + ' of ' + item.remaining + ' remaining' + (over ? ' — too many' : '') + '</div>');
            });

            summary.innerHTML = notes.join('') || '<div class="text-muted">Pick a Sales Order line for at least one row.</div>';
            submitBtn.disabled = !(ok && anyLinked);
        }

        select.addEventListener('change', function () {
            const so = SALES_ORDERS.find(o => String(o.id) === this.value);
            body.innerHTML = '';
            wrap.classList.toggle('d-none', !so);
            submitBtn.disabled = true;
            if (!so) return;

            DR_LINES.forEach(line => {
                const matches = so.items.filter(item => item.generic_name_id === line.generic_name_id);
                let options = '<option value="">— Do not link this line —</option>';
                matches.forEach(item => {
                    options += '<option value="' + item.id + '">' + item.generic_name + ' (ordered ' + item.qty + ', delivered ' + item.delivered + ', remaining ' + item.remaining + ')</option>';
                });
                const tr = document.createElement('tr');
                tr.dataset.line = line.id;
                tr.innerHTML = '<td>' + line.label + '</td><td class="text-end">' + line.qty + '</td>'
                    + '<td><select class="form-select form-select-sm link-so-item" name="line_map[' + line.id + ']">' + options + '</select></td>'
                    + '<td><input type="number" class="form-control form-control-sm text-end link-qty" name="link_qty[' + line.id + ']" min="1" max="' + line.qty + '" disabled></td>';
                body.appendChild(tr);

                const sel = tr.querySelector('select');
                const qtyInput = tr.querySelector('.link-qty');
                const fillDefault = () => {
                    const item = matches.find(m => String(m.id) === sel.value);
                    qtyInput.disabled = !item;
                    qtyInput.value = item ? Math.min(line.qty, item.remaining) : '';
                };
                if (matches.length === 1) {
                    sel.value = String(matches[0].id);
                    fillDefault();
                }
                sel.addEventListener('change', () => { fillDefault(); recalc(so); });
                qtyInput.addEventListener('input', () => recalc(so));
            });
            recalc(so);
        });
    })();
</script>
@endif
<script>
    function refreshCreateInvoiceBtn() {
        const anyChecked = document.querySelectorAll('.line-checkbox:checked').length > 0;
        document.getElementById('createInvoiceBtn').disabled = !anyChecked;
    }

    document.querySelectorAll('.line-checkbox').forEach(cb => {
        cb.addEventListener('change', refreshCreateInvoiceBtn);
    });

    // Withholding Tax suggestion for the checked lines (Sir's formula, see the
    // withholding-tax partial): each item's Product Type (Goods 1% / Services 2%) sets
    // the rate, and a customer with a Withholding VAT % adds that on the VATable part.
    // Only a pre-filled value — the field stays editable, and once someone types in it
    // their number is left alone (the link brings the suggestion back).
    const DR_CUSTOMER = @json([
        'withholding_vat' => $deliveryReceipt->customer?->withholding_vat_rate !== null ? (float) $deliveryReceipt->customer->withholding_vat_rate : null,
        'walk_in' => (bool) $deliveryReceipt->customer?->isWalkIn(),
    ]);
    const DR_HAS_CUSTOMER = @json($deliveryReceipt->customer !== null);
    const DR_VAT_RATE = {{ \App\Models\Taxes::activeRate() }};
    const wtInput = document.getElementById('dr_less_wt');
    let wtDirty = wtInput ? parseFloat(wtInput.value) > 0 : false;

    function refreshWithholdingSuggestion() {
        if (!wtInput) {
            return;
        }
        const hint = document.getElementById('drWtHint');
        const applyBtn = document.getElementById('drApplyWtBtn');
        hint.textContent = '';
        applyBtn.classList.add('d-none');

        const lines = [];
        document.querySelectorAll('.line-checkbox:checked').forEach(cb => {
            const row = cb.closest('tr');
            const qty = parseFloat(row.querySelector('input[type=number]').value) || 0;
            lines.push({ amount: qty * parseFloat(cb.dataset.price), classification: cb.dataset.class, type: cb.dataset.type });
        });

        const result = WithholdingTax.compute(lines, DR_HAS_CUSTOMER ? DR_CUSTOMER : null, DR_VAT_RATE, document.getElementById('dr_personal_use').checked);
        if (!result) {
            return;
        }
        hint.textContent = result.text;

        if (!wtDirty) {
            wtInput.value = result.total.toFixed(2);
        } else if (Math.abs((parseFloat(wtInput.value) || 0) - result.total) > 0.004) {
            applyBtn.classList.remove('d-none');
        }
    }

    document.querySelectorAll('.line-checkbox, #createInvoiceForm input[type=number][name^="qty"]').forEach(el => {
        el.addEventListener('change', refreshWithholdingSuggestion);
        el.addEventListener('input', refreshWithholdingSuggestion);
    });
    if (wtInput) {
        wtInput.addEventListener('input', () => {
            wtDirty = true;
            refreshWithholdingSuggestion();
        });
        // Ticking or unticking Personal use is an explicit choice, so it takes over the field.
        document.getElementById('dr_personal_use').addEventListener('change', () => {
            wtDirty = false;
            refreshWithholdingSuggestion();
        });
        document.getElementById('drApplyWtBtn').addEventListener('click', () => {
            wtDirty = false;
            refreshWithholdingSuggestion();
        });
    }
</script>
@endsection
