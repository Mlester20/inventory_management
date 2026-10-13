{{--
    Every supplier's Purchase Order Summary items in one sheet ("All Suppliers" view) — same
    columns as the per-supplier sheet plus a SUPPLIER column, since a row is no longer scoped to
    one supplier sitting in the letterhead.

    Params: $suppliers (Collection of PurchaseOrderSummaryService::items() entries), $period (string)
--}}
<div class="pending-sheet">
    @include('partials.print.letterhead', [
        'docTitle' => 'PURCHASE ORDER SUMMARY',
        'docNoLabel' => 'Covers',
        'docNo' => $period,
        'docDate' => now()->format('m/d/Y'),
        'toHeader' => 'Supplier',
        'toRows' => ['Name' => 'All Suppliers'],
    ])

    <div class="table-responsive">
        <table class="table table-bordered table-sm print-items-table mb-0">
            <thead>
                <tr>
                    <th style="width: 3%;">#</th>
                    <th>SUPPLIER</th>
                    <th>PO REF #</th>
                    <th>PO DATE</th>
                    <th>GENERIC ITEM</th>
                    <th>ITEM DESCRIPTION</th>
                    <th class="text-end">UNIT COST</th>
                    <th class="text-end">QTY ORDERED</th>
                    <th class="text-end">QTY RECEIVED</th>
                    <th class="text-end">BALANCE</th>
                    <th class="text-end">QTY ON-HAND<br>(WAREHOUSE)</th>
                    <th class="text-end">QTY ON-HAND<br>(POS)</th>
                </tr>
            </thead>
            <tbody>
                @php $row = 0; @endphp
                @foreach ($suppliers as $data)
                    @foreach ($data['items'] as $item)
                        @foreach ($item['orders'] as $order)
                            @php $row++; @endphp
                            <tr>
                                <td class="text-center">{{ $row }}</td>
                                <td>{{ $data['supplier_name'] }}</td>
                                <td>{{ $order['po_no'] }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($order['order_date'])->format('m/d/Y') }}</td>
                                <td>{{ $item['generic_label'] }}</td>
                                <td>{{ $order['product'] }}</td>
                                <td class="text-end">{{ $order['unit_cost'] !== null ? number_format($order['unit_cost'], 2) : '' }}</td>
                                <td class="text-end">{{ number_format($order['ordered']) }}</td>
                                <td class="text-end">{{ number_format($order['received']) }}</td>
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
                    <td class="text-end">{{ number_format($suppliers->sum('total_ordered')) }}</td>
                    <td class="text-end">{{ number_format($suppliers->sum('total_received')) }}</td>
                    <td class="text-end">{{ number_format($suppliers->sum('total_balance')) }}</td>
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
