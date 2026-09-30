{{--
    The customer summary table (S.O Count / Invoiced-Completed / Open / Cancelled / Archived /
    Qty Ordered / Undelivered per customer), printed — same shared letterhead/signature pattern as
    the item-level sheets, portrait since it's only 8 narrow columns.

    Params: $customers (SalesOrderSummaryService::customerSummary() rows)
--}}
<div class="so-customer-sheet">
    @include('partials.print.letterhead', [
        'docTitle' => 'SALES ORDER SUMMARY',
        'docNoLabel' => 'As of',
        'docNo' => now()->format('m/d/Y'),
        'docDate' => now()->format('m/d/Y'),
        'toHeader' => 'Customer',
        'toRows' => ['Name' => 'All Customers'],
    ])

    <div class="table-responsive">
        <table class="table table-bordered table-sm print-items-table mb-0">
            <thead>
                <tr>
                    <th>CUSTOMER</th>
                    <th class="text-end">S.O COUNT</th>
                    <th class="text-end">INVOICED /<br>COMPLETED</th>
                    <th class="text-end">OPEN</th>
                    <th class="text-end">CANCELLED</th>
                    <th class="text-end">ARCHIVED</th>
                    <th class="text-end">QTY ORDERED</th>
                    <th class="text-end">UNDELIVERED</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($customers as $row)
                    <tr>
                        <td>{{ $row['customer_name'] }}</td>
                        <td class="text-end">{{ number_format($row['so_count']) }}</td>
                        <td class="text-end">{{ number_format($row['completed']) }}</td>
                        <td class="text-end">{{ number_format($row['open']) }}</td>
                        <td class="text-end">{{ number_format($row['cancelled']) }}</td>
                        <td class="text-end">{{ number_format($row['archived']) }}</td>
                        <td class="text-end">{{ number_format($row['qty_ordered']) }}</td>
                        <td class="text-end fw-bold">{{ number_format($row['undelivered']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td>TOTAL</td>
                    <td class="text-end">{{ number_format($customers->sum('so_count')) }}</td>
                    <td class="text-end">{{ number_format($customers->sum('completed')) }}</td>
                    <td class="text-end">{{ number_format($customers->sum('open')) }}</td>
                    <td class="text-end">{{ number_format($customers->sum('cancelled')) }}</td>
                    <td class="text-end">{{ number_format($customers->sum('archived')) }}</td>
                    <td class="text-end">{{ number_format($customers->sum('qty_ordered')) }}</td>
                    <td class="text-end">{{ number_format($customers->sum('undelivered')) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @include('partials.print.signature-block', [
        'columns' => 1,
        'label1' => 'Prepared By', 'value1' => Auth::user()->name ?? '',
    ])
</div>
