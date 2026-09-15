@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Advance Orders')

@section('content')
    <div class="card mt-3">
        <div class="card-body no-print">
            <form action="{{ route('advance-orders.index') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Customer</label>
                    <input type="text" id="ao_filter_customer_search" class="form-control" list="ao_filter_customer_datalist"
                        placeholder="Search customer, or leave blank for all customers" autocomplete="off"
                        value="{{ optional($customer)->customer_name }}">
                    <datalist id="ao_filter_customer_datalist">
                        @foreach($customers as $c)
                            <option value="{{ $c->customer_name }}"></option>
                        @endforeach
                    </datalist>
                    <input type="hidden" name="customer_id" id="ao_filter_customer_id" value="{{ optional($customer)->id }}">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Delivery Address</label>
                    <input type="text" class="form-control" value="{{ $customer?->delivery_address }}" readonly>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">View</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Print</button>
                </div>
            </form>
        </div>
    </div>

    <form action="{{ route('advance-orders.create-invoice') }}" method="POST" class="mt-4">
        @csrf
        @if($customer)
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title m-0">Advance Orders{{ $customer ? ' — ' . $customer->customer_name : ' — All Customers' }}</h5>
                <small class="text-muted no-print">Check the lines to invoice, then click Create Invoice. A line's checkbox disables once it's fully invoiced.</small>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr class="table-header-bg">
                            <th class="no-print"></th>
                            <th>#</th>
                            @unless($customer)
                                <th>Customer</th>
                            @endunless
                            <th>DR No.</th>
                            <th>Date</th>
                            <th>Generic Description</th>
                            <th>Item Description</th>
                            <th>Lot/Batch No.</th>
                            <th>Expiry Date</th>
                            <th>Remarks</th>
                            <th class="text-end">Qty</th>
                            <th>Unit</th>
                            <th class="text-end">Invoiced</th>
                            <th class="text-end no-print" style="width: 110px;">Qty to Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $prevDrId = null; $groupAlt = false; @endphp
                        @forelse($lines as $i => $line)
                            @php
                                $product = $line->productBatch->product;
                                $generic = $product->genericName;
                                $remaining = $line->remaining_invoiceable_qty;
                                $fullyInvoiced = $remaining <= 0;
                                $linkedInvoice = $line->sales->first()?->invoice;
                                if ($line->delivery_receipt_id !== $prevDrId) {
                                    $groupAlt = ! $groupAlt;
                                    $prevDrId = $line->delivery_receipt_id;
                                }
                            @endphp
                            {{-- Lines sharing a background tint here share the same
                                 Delivery Receipt — checking any of them together
                                 produces exactly one Invoice (see
                                 AdvanceOrderController::createInvoice(), grouped by
                                 delivery_receipt_id); checking lines from a
                                 differently-shaded group produces a separate
                                 Invoice per group. --}}
                            <tr class="{{ $groupAlt ? 'dr-group-alt' : '' }}">
                                <td class="no-print">
                                    <input type="checkbox" class="form-check-input" name="line_ids[]" value="{{ $line->id }}" {{ $fullyInvoiced ? 'disabled' : '' }}>
                                </td>
                                <td>{{ $lines->firstItem() + $i }}</td>
                                @unless($customer)
                                    <td>{{ $line->deliveryReceipt->customer->customer_name ?? '—' }}</td>
                                @endunless
                                <td>
                                    <a href="{{ route('delivery-receipts.show', $line->delivery_receipt_id) }}" title="View this Delivery Receipt">
                                        {{ $line->deliveryReceipt->dr_no }}
                                    </a>
                                </td>
                                <td>{{ $line->deliveryReceipt->receipt_date->format('M d, Y') }}</td>
                                <td>{{ $generic->generic_name }}</td>
                                <td>{{ $product->description ?: $product->item_name }}</td>
                                <td>{{ $line->batch_no ?? '—' }}</td>
                                <td>{{ $line->expiration_date?->format('M d, Y') ?? '—' }}</td>
                                <td>{{ $line->remarks ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('delivery-receipts.show', $line->delivery_receipt_id) }}" title="View the Delivery Receipt this line came from">
                                        {{ $line->qty }}
                                    </a>
                                </td>
                                <td>{{ $generic->unit }}</td>
                                <td class="text-end">
                                    @if($line->invoiced_qty > 0 && $linkedInvoice)
                                        <a href="{{ route('invoices.show', $linkedInvoice) }}" title="View the linked Invoice">
                                            {{ $line->invoiced_qty }}
                                        </a>
                                    @else
                                        {{ $line->invoiced_qty }}
                                    @endif
                                </td>
                                <td class="no-print">
                                    <input type="number" name="qty[{{ $line->id }}]" value="{{ $remaining }}"
                                        min="1" max="{{ $remaining }}" class="form-control form-control-sm text-end"
                                        {{ $fullyInvoiced ? 'disabled' : '' }}>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $customer ? 13 : 14 }}" class="text-center text-muted py-4">No Advance Orders found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($lines->isNotEmpty())
                <div class="card-footer no-print d-flex align-items-end gap-2 flex-wrap">
                    <div>
                        <label for="ao_po_no" class="form-label small mb-1">Customer PO #</label>
                        <input type="text" name="po_no" id="ao_po_no" class="form-control form-control-sm" style="width: 220px;" placeholder="Optional">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-receipt"></i> Create Invoice
                    </button>
                </div>
            @endif
        </div>
    </form>

    @if($lines->hasPages())
        <div class="mt-3 no-print">
            {{ $lines->links() }}
        </div>
    @endif

<style>
    .table-header-bg {
        background-color: #f7f8fa;
    }

    /* Alternates per Delivery Receipt group (not per row) — lines sharing
       a tint here will combine into one Invoice if checked together; a
       differently-tinted block becomes a separate Invoice. */
    .dr-group-alt {
        background-color: #eef2ff;
    }

    @media print {
        .no-print,
        #layout-menu,
        #layout-navbar,
        .content-footer {
            display: none !important;
        }

        .layout-page {
            margin-left: 0 !important;
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
    const AO_FILTER_CUSTOMERS = @json($customers->map(fn ($c) => ['id' => $c->id, 'name' => $c->customer_name])->values());

    document.getElementById('ao_filter_customer_search').addEventListener('input', function () {
        const match = AO_FILTER_CUSTOMERS.find(c => c.name === this.value);
        document.getElementById('ao_filter_customer_id').value = match ? match.id : '';
    });
</script>
@endsection
