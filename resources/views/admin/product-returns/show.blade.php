@extends('layout.app')

@section('title', 'Product Return ' . $productReturn->return_no)

@section('content')
    @php
        $status = $productReturn->status();
        $statusBadge = ['approved' => 'success', 'rejected' => 'danger', 'pending' => 'warning', 'partly approved' => 'info'][$status] ?? 'secondary';
        $dr = $productReturn->deliveryReceipt;
        $customer = $productReturn->customer ?? $dr?->deliverTo();
        $isAdmin = Auth::user()->role === 'admin';
        $pendingLines = $productReturn->items->where('status', 'pending');
        $anyRefundable = $pendingLines->contains(fn ($item) => $item->isRefundable());
    @endphp

    <div class="d-flex justify-content-between align-items-center mt-3 mb-3 no-print">
        <a href="{{ route('return-items.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Product Returns
        </a>
        <div class="d-flex gap-2">
            @if($isAdmin && $pendingLines->isNotEmpty())
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                    <i class="bx bx-check-circle"></i> Approve
                </button>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bx bx-x-circle"></i> Reject
                </button>
            @endif
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                <i class="bx bx-printer"></i> Print
            </button>
        </div>
    </div>

    <div class="card mb-4 no-print">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="text-muted small">Reference</label>
                    <p class="fw-bold mb-0">{{ $productReturn->return_no }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Customer</label>
                    <p class="fw-bold mb-0">{{ $customer?->customer_name ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Delivery Receipt</label>
                    <p class="mb-0">
                        @if($dr)
                            <a href="{{ route('delivery-receipts.show', $dr) }}">{{ $dr->dr_no }}</a>
                            @if($dr->salesOrder)
                                · <a href="{{ route('sales-orders.show', $dr->salesOrder) }}">{{ $dr->salesOrder->so_no }}</a>
                            @endif
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Status</label>
                    <p class="mb-0"><span class="badge bg-{{ $statusBadge }}">{{ ucfirst($status) }}</span></p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small">Return Date</label>
                    <p class="mb-0">{{ $productReturn->return_date->format('M d, Y') }}</p>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small">Prepared By</label>
                    <p class="mb-0">{{ $productReturn->preparedBy->name ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 no-print">
        <h5 class="card-header">Returned items</h5>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Item Description</th>
                        <th>Lot/Batch No.</th>
                        <th class="text-end">Qty</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Stock</th>
                        <th>Refund</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productReturn->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                {{ $item->productBatch?->product?->display_description ?? '—' }}
                                @if($item->notes)
                                    <div class="small text-muted">{{ $item->notes }}</div>
                                @endif
                            </td>
                            <td>{{ $item->productBatch?->batch_no ?? '—' }}</td>
                            <td class="text-end">{{ $item->quantity }}</td>
                            <td>{{ \Illuminate\Support\Str::before($item->reason, ' | Rejected:') }}</td>
                            <td>
                                <span class="badge bg-{{ $item->status === 'approved' ? 'success' : ($item->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($item->status) }}</span>
                            </td>
                            <td>{{ $item->stock_disposition ? (\App\Models\ReturnItem::STOCK_DISPOSITIONS[$item->stock_disposition] ?? $item->stock_disposition) : '—' }}</td>
                            <td>
                                @if($item->refund_method)
                                    {{ \App\Models\ReturnItem::REFUND_METHODS[$item->refund_method] ?? $item->refund_method }}
                                    @if($item->refund_amount > 0) ({{ number_format($item->refund_amount, 2) }}) @endif
                                @elseif($item->status === 'pending')
                                    {{ $item->isRefundable() ? 'On approval' : 'None (not invoiced yet)' }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card dr-print-only" id="printableProductReturn">
        <div class="card-body p-4 pr-sheet">
            {{-- Letterhead in the header group of an outer table: the printer repeats it on every page. --}}
            <table class="print-repeat-wrap">
            <thead><tr><td>
            @include('partials.print.letterhead', [
                'docTitle' => 'PRODUCT RETURN',
                'docNoLabel' => 'P.R No.',
                'docNo' => $productReturn->return_no,
                'docNoLabel2' => 'D.R No.',
                'docNo2' => $dr?->dr_no,
                'docNoLabel3' => $dr?->salesOrder ? 'S.O No.' : null,
                'docNo3' => $dr?->salesOrder?->so_no,
                'docDate' => $productReturn->return_date->format('m/d/Y'),
                'toHeader' => 'Customer',
                'toRows' => [
                    'Name' => $customer?->customer_name ?? '',
                    'Address' => $customer?->delivery_address ?? '',
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
                            <th class="text-end" style="width: 7%;">Qty</th>
                            <th style="width: 24%;">Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($productReturn->items as $item)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $item->productBatch?->product?->display_description ?? '—' }}</td>
                                <td class="text-center">{{ $item->productBatch?->product?->genericName?->unit ?? '—' }}</td>
                                <td class="text-center">{{ $item->productBatch?->batch_no ?? '—' }}</td>
                                <td class="text-end">{{ $item->quantity }}</td>
                                <td>{{ \Illuminate\Support\Str::before($item->reason, ' | Rejected:') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('partials.print.signature-block', [
                'label1' => 'Prepared By', 'value1' => $productReturn->preparedBy->name ?? '—',
                'label2' => 'Received By',
                'label3' => 'Returned By (Client)',
            ])
            </td></tr></tbody>
            </table>
        </div>
    </div>

    @if($isAdmin && $pendingLines->isNotEmpty())
        <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form action="{{ route('product-returns.approve', $productReturn) }}" method="POST">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Approve {{ $productReturn->return_no }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            @if($anyRefundable)
                                <div class="mb-3">
                                    <label class="form-label d-block">Refund (for the lines already invoiced)</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="refund_method" id="refundCash" value="cash" checked>
                                        <label class="form-check-label" for="refundCash">Cash Refund — money is handed back now.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="refund_method" id="refundCredit" value="credit">
                                        <label class="form-check-label" for="refundCredit">Store Credit — added to the customer's Available Credit.</label>
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-info py-2 small">No invoice yet on this Delivery Receipt, so <strong>no refund</strong>. The stock goes back to the Warehouse.</div>
                            @endif

                            <label class="form-label">Stock — sellable, or written off (damaged)?</label>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        @foreach($pendingLines as $item)
                                            <tr>
                                                <td>{{ $item->productBatch?->product?->display_description }} <span class="text-muted">× {{ $item->quantity }}</span></td>
                                                <td style="width: 240px;">
                                                    <select class="form-select form-select-sm" name="disposition[{{ $item->id }}]">
                                                        <option value="sellable" @selected($item->suggestedStockDisposition() === 'sellable')>Sellable — back to stock</option>
                                                        <option value="write_off" @selected($item->suggestedStockDisposition() === 'write_off')>Not sellable — write off</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="form-text">Inspect the goods first — the choice is only pre-selected from each line's reason.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Approve</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form action="{{ route('product-returns.reject', $productReturn) }}" method="POST">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Reject {{ $productReturn->return_no }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label">Rejection Reason</label>
                            <textarea class="form-control" name="rejection_reason" rows="3" placeholder="Optional"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

<style>
    @include('partials.print.base-print')

    .dr-print-only {
        display: none;
    }

    .pr-sheet {
        font-size: 0.75rem;
    }

    .pr-sheet .print-company-detail {
        font-size: 0.65rem;
    }

    .pr-sheet .print-doc-title {
        font-size: 1.4rem;
    }

    .pr-sheet .print-doc-page,
    .pr-sheet .print-doc-no-row {
        font-size: 0.68rem;
    }

    .pr-sheet .print-to-header {
        font-size: 0.72rem;
    }

    .pr-sheet .print-to-row {
        font-size: 0.68rem;
    }

    .pr-sheet .print-items-table th,
    .pr-sheet .print-items-table td {
        font-size: 0.7rem;
        padding: 0.2rem 0.35rem;
    }

    @media print {
        .dr-print-only {
            display: block !important;
        }

        #printableProductReturn {
            box-shadow: none !important;
            border: none !important;
        }

        #printableProductReturn .card-body {
            padding: 0 !important;
        }
    }
</style>
@endsection
