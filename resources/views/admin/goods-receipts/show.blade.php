@extends('layout.app')

@section('title', 'Goods Receipt ' . $goodsReceipt->gr_no)

@section('content')
    @php
        $totalInvoicedQty = (int) $goodsReceipt->items->sum(fn ($line) => (int) $line->invoiced_qty);
        $allLinesInvoiced = $goodsReceipt->items->isNotEmpty()
            && $goodsReceipt->items->every(fn ($line) => (int) $line->invoiced_qty >= (int) $line->qty);
        $invoiceStatus = $totalInvoicedQty <= 0
            ? 'NOT INVOICED'
            : ($allLinesInvoiced ? 'INVOICED' : 'PARTIALLY INVOICED');
        $invoiceStatusColor = [
            'NOT INVOICED' => 'secondary',
            'PARTIALLY INVOICED' => 'warning text-dark',
            'INVOICED' => 'success',
        ][$invoiceStatus];
    @endphp
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('goods-receipts.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Goods Receipts
        </a>
        @if($goodsReceipt->isDraft())
            <div class="d-flex gap-2">
                <a href="{{ route('goods-receipts.edit', $goodsReceipt) }}" class="btn btn-primary">
                    <i class="bx bx-edit-alt"></i> Continue Editing
                </a>
                @if(Auth::user()->role === 'admin')
                    <form action="{{ route('goods-receipts.destroy', $goodsReceipt) }}" method="POST" onsubmit="return confirm('Delete draft {{ $goodsReceipt->gr_no }}? This cannot be undone.');">
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
                @if(Auth::user()->role === 'admin')
                    <a href="{{ route('goods-receipts.revise', $goodsReceipt) }}" class="btn btn-outline-secondary">
                        <i class="bx bx-edit-alt"></i> Edit
                    </a>
                @endif
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bx bx-printer"></i> Print
                </button>
            </div>
        @endif
    </div>

    <div id="printableGoodsReceipt">
    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="text-muted small">G.R. No.</label>
                    <p class="fw-bold mb-0">{{ $goodsReceipt->gr_no }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Status</label>
                    <p class="mb-0">
                        <span class="badge bg-{{ $goodsReceipt->isDraft() ? 'secondary' : 'success' }}">
                            {{ \App\Models\GoodsReceipt::STATUSES[$goodsReceipt->status] ?? $goodsReceipt->status }}
                        </span>
                        @unless($goodsReceipt->isDraft())
                            <span class="badge bg-{{ $invoiceStatusColor }}">{{ $invoiceStatus }}</span>
                        @endunless
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Supplier</label>
                    <p class="fw-bold mb-0">{{ $goodsReceipt->supplier->supplier_name ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Type</label>
                    <p class="mb-0">
                        <span class="badge bg-{{ $goodsReceipt->purchase_order_id ? 'info' : 'secondary' }}">
                            {{ $goodsReceipt->purchase_order_id ? 'Against P.O.' : 'Direct Receipt' }}
                        </span>
                    </p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Purchase Order</label>
                    <p class="mb-0">
                        @if($goodsReceipt->purchaseOrder)
                            <a href="{{ route('purchase-orders.show', $goodsReceipt->purchaseOrder) }}">{{ $goodsReceipt->purchaseOrder->po_no }}</a>
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Receipt Date</label>
                    <p class="mb-0">{{ $goodsReceipt->receipt_date?->format('M d, Y') ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Prepared By</label>
                    <p class="mb-0">{{ $goodsReceipt->preparedBy->name ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    @if(!$goodsReceipt->isDraft())
        <form action="{{ url('/admin/goods-receipts/' . $goodsReceipt->id . '/create-purchase-invoice') }}"
            method="POST" id="createPurchaseInvoiceForm" class="no-print">
            @csrf
    @endif
    <div class="card no-print">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Received Items</h5>
            @if(!$goodsReceipt->isDraft())
                <button type="button" class="btn btn-sm btn-outline-secondary" id="sortBySupplierBtn">
                    Sort by Supplier
                </button>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr class="table-header-bg">
                        @if(!$goodsReceipt->isDraft())
                            <th class="no-print"></th>
                        @endif
                        <th>Item</th>
                        <th>Supplier</th>
                        <th class="text-end">Qty Received</th>
                        @if(!$goodsReceipt->isDraft())
                            <th class="text-end">Invoiced</th>
                            <th>Invoice Status</th>
                            <th class="text-end no-print" style="width: 130px;">Qty to Invoice</th>
                        @endif
                        <th>Unit</th>
                        <th class="text-end">Unit Cost</th>
                        <th>Batch No.</th>
                        <th>Expiry</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($goodsReceipt->items as $line)
                        @php
                            $lineQty = (int) $line->qty;
                            $lineInvoicedQty = (int) $line->invoiced_qty;
                            $lineRemainingQty = max(0, $lineQty - $lineInvoicedQty);
                            $fullyInvoiced = $lineRemainingQty <= 0;
                            $lineInvoiceStatus = $lineInvoicedQty <= 0
                                ? 'Not Invoiced'
                                : ($fullyInvoiced ? 'Invoiced' : 'Partially Invoiced');
                            $lineInvoiceStatusColor = [
                                'Not Invoiced' => 'secondary',
                                'Partially Invoiced' => 'warning text-dark',
                                'Invoiced' => 'success',
                            ][$lineInvoiceStatus];
                            $lineSupplierName = $line->supplier_not_determined
                                ? 'Not Determined'
                                : ($line->supplier?->supplier_name ?? '—');
                        @endphp
                        <tr data-supplier="{{ mb_strtolower($lineSupplierName) }}">
                            @if(!$goodsReceipt->isDraft())
                                <td class="no-print">
                                    <input type="checkbox" class="form-check-input invoice-line-checkbox"
                                        name="line_ids[]" value="{{ $line->id }}" {{ $fullyInvoiced ? 'disabled' : '' }}>
                                </td>
                            @endif
                            <td>{{ $line->productBatch?->product?->display_description ?? $line->product?->display_description ?? '—' }}</td>
                            <td>{{ (bool) $line->supplier_not_determined ? 'Not Determined' : ($line->supplier?->supplier_name ?? '—') }}</td>
                            <td class="text-end">{{ $line->qty ?? '—' }}</td>
                            @if(!$goodsReceipt->isDraft())
                                <td class="text-end">{{ $lineInvoicedQty }}</td>
                                <td><span class="badge bg-{{ $lineInvoiceStatusColor }}">{{ $lineInvoiceStatus }}</span></td>
                                <td class="no-print">
                                    <input type="number" name="qty[{{ $line->id }}]" value="{{ $lineRemainingQty }}"
                                        min="1" max="{{ $lineRemainingQty }}" class="form-control form-control-sm text-end"
                                        {{ $fullyInvoiced ? 'disabled' : '' }}>
                                </td>
                            @endif
                            <td>{{ $line->unit ?? '—' }}</td>
                            <td class="text-end">{{ $line->unit_cost !== null ? number_format($line->unit_cost, 2) : '—' }}</td>
                            <td>{{ $line->batch_no ?? '—' }}</td>
                            <td>{{ $line->expiration_date ? $line->expiration_date->format('Y-m-d') : '—' }}</td>
                            <td>{{ $line->remarks ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(!$goodsReceipt->isDraft())
            <div class="card-body pt-0">
                <button type="submit" class="btn btn-primary" id="createPurchaseInvoiceBtn" disabled>
                    <i class="bx bx-receipt"></i> Create Purchase Invoice
                </button>
            </div>
        @endif
    </div>
    @if(!$goodsReceipt->isDraft())
        </form>
    @endif
    </div>

    <div class="card gr-print-only" id="printableGoodsReceiptSheet">
        <div class="card-body p-4 gr-sheet">

            {{-- Letterhead in the header group of an outer table: the printer repeats it on every page. --}}
            <table class="print-repeat-wrap">
            <thead><tr><td>
            @include('partials.print.letterhead', [
                'docTitle' => 'GOODS RECEIPT',
                'docNoLabel' => 'G.R No.',
                'docNo' => $goodsReceipt->gr_no,
                'docNoLabel2' => 'S.P.O No.',
                'docNo2' => $goodsReceipt->purchaseOrder->po_no ?? null,
                'docDate' => $goodsReceipt->receipt_date?->format('m/d/Y') ?? '—',
                'toHeader' => 'Supplier',
                'toRows' => [
                    'Name' => $goodsReceipt->supplier->supplier_name ?? '—',
                    'Address' => $goodsReceipt->supplier->delivery_address ?? '—',
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
                            <th class="text-end" style="width: 7%;">Qty</th>
                            <th style="width: 10%;">Lot/Batch</th>
                            <th style="width: 9%;">Expiry</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($goodsReceipt->items as $line)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $line->productBatch?->product?->display_description ?? $line->product?->display_description ?? '—' }}</td>
                                <td class="text-center">{{ $line->unit ?? '—' }}</td>
                                <td class="text-end">{{ $line->qty ?? '—' }}</td>
                                <td class="text-center">{{ $line->batch_no ?? '—' }}</td>
                                <td class="text-center">{{ $line->expiration_date ? $line->expiration_date->format('Y-m-d') : '—' }}</td>
                                <td>{{ $line->remarks ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('partials.print.signature-block', [
                'columns' => 1,
                'label1' => 'Prepared By', 'value1' => $goodsReceipt->preparedBy->name ?? '—',
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

    .gr-print-only {
        display: none;
    }

    .gr-sheet {
        font-size: 0.68rem;
    }

    .gr-sheet .print-company-detail {
        font-size: 0.6rem;
    }

    .gr-sheet .print-doc-title {
        font-size: 1.3rem;
    }

    .gr-sheet .print-doc-page,
    .gr-sheet .print-doc-no-row {
        font-size: 0.62rem;
    }

    .gr-sheet .print-to-header {
        font-size: 0.66rem;
    }

    .gr-sheet .print-to-row {
        font-size: 0.62rem;
    }

    .gr-sheet .print-sig-label {
        font-size: 0.66rem;
    }

    .gr-sheet .print-sig-value {
        font-size: 0.6rem;
    }

    .gr-sheet .print-items-table th,
    .gr-sheet .print-items-table td {
        font-size: 0.62rem;
        padding: 0.15rem 0.3rem;
    }

    @media print {
        #printableGoodsReceipt {
            display: none !important;
        }

        .gr-print-only {
            display: block !important;
        }

        #printableGoodsReceiptSheet {
            box-shadow: none !important;
            border: none !important;
        }

        #printableGoodsReceiptSheet .card-body {
            padding: 0 !important;
        }
    }
</style>
@endsection

@if(!$goodsReceipt->isDraft())
@section('scripts')
    <script>
        const invoiceLineCheckboxes = document.querySelectorAll('.invoice-line-checkbox');
        const createPurchaseInvoiceBtn = document.getElementById('createPurchaseInvoiceBtn');

        function refreshCreatePurchaseInvoiceButton() {
            createPurchaseInvoiceBtn.disabled = document.querySelectorAll('.invoice-line-checkbox:checked').length === 0;
        }

        invoiceLineCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', refreshCreatePurchaseInvoiceButton);
        });

        let supplierSortAscending = true;
        document.getElementById('sortBySupplierBtn').addEventListener('click', function () {
            const tbody = document.querySelector('#createPurchaseInvoiceForm table tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort((a, b) => {
                const comparison = a.dataset.supplier.localeCompare(b.dataset.supplier);
                return supplierSortAscending ? comparison : -comparison;
            });
            rows.forEach(row => tbody.appendChild(row));
            supplierSortAscending = !supplierSortAscending;
            this.textContent = supplierSortAscending ? 'Sort by Supplier' : 'Sort by Supplier (reverse)';
        });
    </script>
@endsection
@endif
