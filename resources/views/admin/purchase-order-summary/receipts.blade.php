@extends('layout.app')

@section('title', 'Goods Receipts')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            @if($supplier)
                <a href="{{ route('po-summary.items', ['supplier_id' => $supplier->id, 'scope' => 'ordered']) }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="bx bx-arrow-back"></i> Back to items</a>
            @else
                <a href="{{ route('po-summary.index') }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="bx bx-arrow-back"></i> Purchase Order Summary</a>
            @endif
            <h4 class="mb-1">Goods Receipts</h4>
            <div class="small text-muted">
                @php
                    $what = $genericName ? '<strong>' . e($genericName->generic_name . ($genericName->unit ? ' (' . $genericName->unit . ')' : '')) . '</strong>' : null;
                    $where = match (true) {
                        (bool) $purchaseOrder => 'on <strong>' . e($purchaseOrder->po_no) . '</strong>',
                        (bool) $supplier => 'for <strong>' . e($supplier->supplier_name) . '</strong>, across all of their P.O.s',
                        default => 'across all suppliers',
                    };
                @endphp
                What was actually received {!! $what ? 'of ' . $what . ' ' : '' !!}{!! $where !!}. Draft Goods Receipts are not counted;
                Direct Receipts show too even though they have no Purchase Order behind them.
                @if($genericName && ($supplier || $purchaseOrder))
                    <a href="{{ route('po-summary.receipts', ['generic_name_id' => $genericName->id]) }}">Trace this item for all suppliers</a>
                @endif
            </div>
        </div>
        <form method="GET" action="{{ route('po-summary.receipts') }}" class="d-flex gap-2">
            <input type="hidden" name="supplier_id" value="{{ $supplier?->id }}">
            <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder?->id }}">
            <input type="hidden" name="generic_name_id" value="{{ $genericName?->id }}">
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Search by supplier or G.R" style="min-width: 240px;">
            <button type="submit" class="btn btn-outline-secondary text-nowrap">Search</button>
        </form>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" style="font-size: .8125rem;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Receipt Date</th>
                        <th>Reference</th>
                        <th>P.O No.</th>
                        <th>Supplier</th>
                        <th>Generic Item</th>
                        <th>Item Description</th>
                        <th class="text-end">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($row->receipt_date)->format('M d, Y') }}</td>
                            <td><a href="{{ route('goods-receipts.show', $row->gr_id) }}">{{ $row->gr_no }}</a></td>
                            <td>
                                @if($row->po_id)
                                    <a href="{{ route('purchase-orders.show', $row->po_id) }}">{{ $row->po_no }}</a>
                                @else
                                    <span class="text-muted">Direct Receipt</span>
                                @endif
                            </td>
                            <td class="fw-semibold">{{ $row->supplier_name }}</td>
                            <td>{{ $row->generic_name }}{{ $row->unit ? ' (' . $row->unit . ')' : '' }}</td>
                            <td>{{ $row->product_description ?: ($row->product_brand ?: $row->product_name) }}</td>
                            <td class="text-end fw-bold">{{ number_format($row->qty) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No receipts found.</td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="7" class="text-end">Total received</td>
                            <td class="text-end">{{ number_format($rows->sum('qty')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
