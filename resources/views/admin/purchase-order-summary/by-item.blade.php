@extends('layout.app')

@section('title', 'Purchase Order Summary — By Item')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link" href="{{ route('po-summary.index') }}">P.O Summary</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('po-summary.all-items') }}">P.O Summary — All Suppliers</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="{{ route('po-summary.by-item') }}">P.O Summary — By Item</a>
        </li>
    </ul>

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h4 class="mb-1">Purchase Order Summary — By Item</h4>
            <p class="text-muted small mb-0">
                Every Generic Item ordered, across all suppliers. On Hand is Warehouse stock; Pending is what
                open/partially-received Purchase Orders still owe for that item.
            </p>
        </div>
        <form method="GET" action="{{ route('po-summary.by-item') }}" class="d-flex flex-wrap gap-2 align-items-center">
            @if($hideZeroBalance)<input type="hidden" name="hide_zero_balance" value="1">@endif
            @if($showZeroOnHand)<input type="hidden" name="show_zero_on_hand" value="1">@endif
            <select name="category_id" class="form-select" style="min-width: 180px;" onchange="this.form.submit()">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ (string) $categoryId === (string) $category->id ? 'selected' : '' }}>{{ $category->category_name }}</option>
                @endforeach
            </select>
            <input type="text" name="search" autocomplete="off" value="{{ $search }}" class="form-control" placeholder="Search by P.O / Supplier / Item" style="min-width: 240px;">
            <button type="submit" class="btn btn-outline-secondary text-nowrap">Search</button>
            @if($search !== '' || $categoryId)
                <a href="{{ route('po-summary.by-item', ['hide_zero_balance' => $hideZeroBalance ?: null, 'show_zero_on_hand' => $showZeroOnHand ?: null]) }}" class="btn btn-outline-secondary text-nowrap">Clear</a>
            @endif
        </form>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('po-summary.by-item', array_merge(request()->except('hide_zero_balance'), ['hide_zero_balance' => $hideZeroBalance ? null : 1])) }}"
           class="btn btn-outline-secondary btn-sm {{ $hideZeroBalance ? 'active' : '' }}">
            <i class="bx {{ $hideZeroBalance ? 'bx-check-square' : 'bx-square' }}"></i> Hide items with 0 balance
        </a>
        <a href="{{ route('po-summary.by-item', array_merge(request()->except('show_zero_on_hand'), ['show_zero_on_hand' => $showZeroOnHand ? null : 1])) }}"
           class="btn btn-outline-secondary btn-sm {{ $showZeroOnHand ? 'active' : '' }}">
            <i class="bx {{ $showZeroOnHand ? 'bx-check-square' : 'bx-square' }}"></i> Show items with 0 on-hand
        </a>
    </div>

    @forelse($items as $item)
        <div class="card mb-3">
            <div class="table-responsive">
                <table class="table table-sm mb-0 by-item-header">
                    <thead>
                        <tr>
                            <th>Generic Item</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th class="text-end">Total Order</th>
                            <th class="text-end">Pending</th>
                            <th class="text-end">On-Hand</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="fw-semibold table-light">
                            <td>
                                <a href="{{ route('po-summary.receipts', ['generic_name_id' => $item['generic_name_id']]) }}" title="All Goods Receipts of this item">{{ $item['generic_label'] }}</a>
                            </td>
                            <td>{{ $item['category_name'] ?? '—' }}</td>
                            <td>{{ $item['unit'] ?? '—' }}</td>
                            <td class="text-end">{{ number_format($item['total_order']) }}</td>
                            <td class="text-end">{{ number_format($item['pending']) }}</td>
                            <td class="text-end">{{ number_format($item['on_hand']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 by-item-detail">
                    <thead>
                        <tr>
                            <th>PO Ref #</th>
                            <th>PO Date</th>
                            <th>Supplier</th>
                            <th>Item Description</th>
                            <th class="text-end">Order Qty</th>
                            <th class="text-end">Received</th>
                            <th class="text-end">Balance</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($item['orders'] as $order)
                            <tr>
                                <td class="nowrap"><a href="{{ route('purchase-orders.show', $order['po_id']) }}">{{ $order['po_no'] }}</a></td>
                                <td class="nowrap">{{ \Illuminate\Support\Carbon::parse($order['order_date'])->format('M d, Y') }}</td>
                                <td>{{ $order['supplier_name'] }}</td>
                                <td>{{ $order['product'] }}</td>
                                <td class="text-end nowrap">{{ number_format($order['ordered']) }}</td>
                                <td class="text-end nowrap">
                                    @if($order['received'] > 0)
                                        <a href="{{ route('po-summary.receipts', ['purchase_order_id' => $order['po_id'], 'generic_name_id' => $item['generic_name_id']]) }}" title="Goods Receipts of this P.O.">{{ number_format($order['received']) }}</a>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td class="text-end fw-bold nowrap">{{ number_format($order['balance']) }}</td>
                                <td>{{ $order['remarks'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body text-center text-muted py-4">
                No items{{ $search !== '' ? ' for this search' : '' }}.
            </div>
        </div>
    @endforelse
</div>

<style>
    .by-item-header th, .by-item-header td { font-size: .75rem; padding: .5rem .6rem; }
    .by-item-detail th, .by-item-detail td { font-size: .8125rem; padding: .45rem .6rem; vertical-align: top; }
    .by-item-detail .nowrap { white-space: nowrap; }
</style>
@endsection
