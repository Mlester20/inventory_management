{{--
    Every customer's Sales Order Summary items in one sheet (Sir's "All Customers" view) — same
    columns as the per-customer sheet plus a CUSTOMER column, since a row is no longer scoped to
    one customer sitting in the letterhead.

    Params: $customers (Collection of SalesOrderSummaryService::items() entries), $period (string)
--}}
<div class="undelivered-sheet">
    @include('partials.print.letterhead', [
        'docTitle' => 'SALES ORDER SUMMARY',
        'docNoLabel' => 'Covers',
        'docNo' => $period,
        'docDate' => now()->format('m/d/Y'),
        'toHeader' => 'Customer',
        'toRows' => ['Name' => 'All Customers'],
    ])

    <div class="table-responsive">
        <table class="table table-bordered table-sm print-items-table mb-0">
            <thead>
                <tr>
                    <th style="width: 3%;">#</th>
                    <th>CUSTOMER</th>
                    <th>PO REF #</th>
                    <th>PO DATE</th>
                    <th>GENERIC ITEM</th>
                    <th>ITEM DESCRIPTION</th>
                    <th class="text-end">PRICE</th>
                    <th class="text-end">QTY ORDERED</th>
                    <th class="text-end">QTY DELIVERED</th>
                    <th class="text-end">BALANCE</th>
                    <th class="text-end">QTY ON-HAND<br>(WAREHOUSE)</th>
                    <th class="text-end">QTY ON-HAND<br>(POS)</th>
                </tr>
            </thead>
            <tbody>
                @php $row = 0; @endphp
                @foreach ($customers as $data)
                    @foreach ($data['items'] as $item)
                        @foreach ($item['orders'] as $order)
                            @php $row++; @endphp
                            <tr>
                                <td class="text-center">{{ $row }}</td>
                                <td>{{ $data['customer_name'] }}</td>
                                <td>{{ $order['po_no'] ?: $order['so_no'] }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($order['order_date'])->format('m/d/Y') }}</td>
                                <td>{{ $item['generic_label'] }}</td>
                                <td>{{ $order['product'] }}</td>
                                <td class="text-end">{{ $order['price'] !== null ? number_format($order['price'], 2) : '' }}</td>
                                <td class="text-end">{{ number_format($order['ordered']) }}</td>
                                <td class="text-end">{{ number_format($order['delivered']) }}</td>
                                <td class="text-end fw-bold">{{ number_format($order['balance']) }}</td>
                                <td class="text-end">{{ number_format($order['on_hand']['warehouse']) }}</td>
                                <td class="text-end">{{ number_format($order['on_hand']['pos']) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="7" class="text-end">TOTAL</td>
                    <td class="text-end">{{ number_format($customers->sum('total_ordered')) }}</td>
                    <td class="text-end">{{ number_format($customers->sum('total_delivered')) }}</td>
                    <td class="text-end">{{ number_format($customers->sum('total_balance')) }}</td>
                    <td></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    @include('partials.print.signature-block', [
        'columns' => 1,
        'label1' => 'Prepared By', 'value1' => Auth::user()->name ?? '',
    ])
</div>
