{{--
    One customer's Undelivered Items sheet: the same rows and columns as the Excel download
    (PO REF #, PO DATE, GENERIC ITEM, ITEM DESCRIPTION, PRICE, QTY ORDERED, QTY DELIVERED, BALANCE,
    QTY ON-HAND — the customer sits in the letterhead instead of repeating on every row), laid out
    like the other documents' prints (shared letterhead, bordered items table, signature box).
    It is what the modal shows and what the Print button prints.

    Params: $customer (one entry of UndeliveredItemsReportService::build), $period (string)
--}}
<div class="undelivered-sheet">
    @include('partials.print.letterhead', [
        'docTitle' => 'UNDELIVERED ITEMS',
        'docNoLabel' => 'Covers',
        'docNo' => $period,
        'docDate' => now()->format('m/d/Y'),
        'toHeader' => 'Customer',
        'toRows' => ['Name' => $customer['customer_name'], 'Address' => $customer['customer_address'] ?? ''],
    ])

    <div class="table-responsive">
        <table class="table table-bordered table-sm print-items-table mb-0">
            <thead>
                <tr>
                    <th style="width: 3%;">#</th>
                    <th>PO REF #</th>
                    <th>PO DATE</th>
                    <th>GENERIC ITEM</th>
                    <th>ITEM DESCRIPTION</th>
                    <th class="text-end">PRICE</th>
                    <th class="text-end">QTY ORDERED</th>
                    <th class="text-end">QTY DELIVERED</th>
                    <th class="text-end">BALANCE</th>
                    <th class="text-end">QTY ON-HAND</th>
                </tr>
            </thead>
            <tbody>
                @php $row = 0; @endphp
                @foreach ($customer['items'] as $item)
                    @foreach ($item['orders'] as $order)
                        @php $row++; @endphp
                        <tr>
                            <td class="text-center">{{ $row }}</td>
                            <td>{{ $order['po_no'] ?: $order['so_no'] }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($order['order_date'])->format('m/d/Y') }}</td>
                            <td>{{ $item['generic_label'] }}</td>
                            <td>{{ $order['product'] }}</td>
                            <td class="text-end">{{ $order['price'] !== null ? number_format($order['price'], 2) : '' }}</td>
                            <td class="text-end">{{ number_format($order['ordered']) }}</td>
                            <td class="text-end">{{ number_format($order['delivered']) }}</td>
                            <td class="text-end fw-bold">{{ number_format($order['balance']) }}</td>
                            <td class="text-end">{{ number_format($order['on_hand']) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                @php
                    $orderedTotal = collect($customer['items'])->sum('ordered');
                    $deliveredTotal = collect($customer['items'])->sum('delivered');
                @endphp
                <tr class="fw-bold">
                    <td colspan="6" class="text-end">TOTAL</td>
                    <td class="text-end">{{ number_format($orderedTotal) }}</td>
                    <td class="text-end">{{ number_format($deliveredTotal) }}</td>
                    <td class="text-end">{{ number_format($customer['total_balance']) }}</td>
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
