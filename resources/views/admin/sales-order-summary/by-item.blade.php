@extends(in_array(Auth::user()->role, ['admin', 'admin_staff'], true) ? 'layout.app' : 'layout.user')

@section('title', 'Sales Order Summary — By Item')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link" href="{{ route('so-summary.index') }}">S.O Summary</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('so-summary.all-items') }}">S.O Summary — All Customers</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="{{ route('so-summary.by-item') }}">S.O Summary — By Item</a>
        </li>
    </ul>

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h4 class="mb-1">Sales Order Summary — By Item</h4>
            <p class="text-muted small mb-0">
                Every Generic Item ordered, across all customers. On Hand is Warehouse stock; Reserved is what's
                sitting on a draft Delivery Receipt (any of them — with or without a Sales Order behind it);
                Available is On Hand minus Reserved. The Reserved column under each P.O./S.O. line only counts a
                draft Delivery Receipt made specifically against that line.
            </p>
        </div>
        <form method="GET" action="{{ route('so-summary.by-item') }}" class="d-flex flex-wrap gap-2 align-items-center">
            @if($hideZeroBalance)<input type="hidden" name="hide_zero_balance" value="1">@endif
            @if($showZeroOnHand)<input type="hidden" name="show_zero_on_hand" value="1">@endif
            <select name="category_id" class="form-select" style="min-width: 180px;" onchange="this.form.submit()">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ (string) $categoryId === (string) $category->id ? 'selected' : '' }}>{{ $category->category_name }}</option>
                @endforeach
            </select>
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Search by P.O / S.O / Customer / Item" style="min-width: 240px;">
            <button type="submit" class="btn btn-outline-secondary text-nowrap">Search</button>
            @if($search !== '' || $categoryId)
                <a href="{{ route('so-summary.by-item', ['hide_zero_balance' => $hideZeroBalance ?: null, 'show_zero_on_hand' => $showZeroOnHand ?: null]) }}" class="btn btn-outline-secondary text-nowrap">Clear</a>
            @endif
        </form>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('so-summary.by-item', array_merge(request()->except('hide_zero_balance'), ['hide_zero_balance' => $hideZeroBalance ? null : 1])) }}"
           class="btn btn-outline-secondary btn-sm {{ $hideZeroBalance ? 'active' : '' }}">
            <i class="bx {{ $hideZeroBalance ? 'bx-check-square' : 'bx-square' }}"></i> Hide items with 0 balance
        </a>
        <a href="{{ route('so-summary.by-item', array_merge(request()->except('show_zero_on_hand'), ['show_zero_on_hand' => $showZeroOnHand ? null : 1])) }}"
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
                            <th class="text-end">Undelivered</th>
                            <th class="text-end">On-Hand</th>
                            <th class="text-end">Reserved</th>
                            <th class="text-end">Available</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="fw-semibold table-light">
                            <td>{{ $item['generic_label'] }}</td>
                            <td>{{ $item['category_name'] ?? '—' }}</td>
                            <td>{{ $item['unit'] ?? '—' }}</td>
                            <td class="text-end">{{ number_format($item['total_order']) }}</td>
                            <td class="text-end">{{ number_format($item['undelivered']) }}</td>
                            <td class="text-end">{{ number_format($item['on_hand']) }}</td>
                            <td class="text-end">
                                @if($item['reserved'] > 0)
                                    <a href="{{ route('so-summary.deliveries', ['generic_name_id' => $item['generic_name_id']]) }}" title="Delivery Receipts (including drafts) of this item">{{ number_format($item['reserved']) }}</a>
                                @else
                                    0
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($item['available']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 by-item-detail">
                    <thead>
                        <tr>
                            <th>PO Ref #</th>
                            <th>SO Ref #</th>
                            <th>PO Date</th>
                            <th>Customer</th>
                            <th>Item Description</th>
                            <th class="text-end">Order Qty</th>
                            <th class="text-end">Delivered</th>
                            <th class="text-end">Balance</th>
                            <th class="text-end">Reserved</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($item['orders'] as $order)
                            <tr>
                                <td class="nowrap">{{ $order['po_no'] ?: '—' }}</td>
                                <td class="nowrap"><a href="{{ route('sales-orders.show', $order['so_id']) }}">{{ $order['so_no'] }}</a></td>
                                <td class="nowrap">{{ \Illuminate\Support\Carbon::parse($order['order_date'])->format('M d, Y') }}</td>
                                <td>{{ $order['customer_name'] }}</td>
                                <td>{{ $order['product'] }}</td>
                                <td class="text-end nowrap">{{ number_format($order['ordered']) }}</td>
                                <td class="text-end nowrap">
                                    @if($order['delivered'] > 0)
                                        <a href="{{ route('so-summary.deliveries', ['sales_order_id' => $order['so_id'], 'generic_name_id' => $item['generic_name_id']]) }}" title="Delivery Receipts of this P.O.">{{ number_format($order['delivered']) }}</a>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td class="text-end fw-bold nowrap">{{ number_format($order['balance']) }}</td>
                                <td class="text-end nowrap">{{ number_format($order['reserved']) }}</td>
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
