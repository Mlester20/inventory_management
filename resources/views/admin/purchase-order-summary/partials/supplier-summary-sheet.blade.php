{{--
    The supplier summary table (P.O Count / Completed / Partially Received / Open / Qty Ordered /
    Pending per supplier), printed — same shared letterhead/signature pattern as the item-level
    sheets, portrait since it's only 6 narrow columns.

    Params: $suppliers (PurchaseOrderSummaryService::supplierSummary() rows)
--}}
<div class="po-supplier-sheet">
    @include('partials.print.letterhead', [
        'docTitle' => 'PURCHASE ORDER SUMMARY',
        'docNoLabel' => 'As of',
        'docNo' => now()->format('m/d/Y'),
        'docDate' => now()->format('m/d/Y'),
        'toHeader' => 'Supplier',
        'toRows' => ['Name' => 'All Suppliers'],
    ])

    <div class="table-responsive">
        <table class="table table-bordered table-sm print-items-table mb-0">
            <thead>
                <tr>
                    <th>SUPPLIER</th>
                    <th class="text-end">P.O COUNT</th>
                    <th class="text-end">COMPLETED</th>
                    <th class="text-end">PARTIALLY<br>RECEIVED</th>
                    <th class="text-end">OPEN</th>
                    <th class="text-end">CLOSED</th>
                    <th class="text-end">QTY ORDERED</th>
                    <th class="text-end">PENDING</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($suppliers as $row)
                    <tr>
                        <td>{{ $row['supplier_name'] }}</td>
                        <td class="text-end">{{ number_format($row['po_count']) }}</td>
                        <td class="text-end">{{ number_format($row['completed']) }}</td>
                        <td class="text-end">{{ number_format($row['partially_received']) }}</td>
                        <td class="text-end">{{ number_format($row['open']) }}</td>
                        <td class="text-end">{{ number_format($row['closed']) }}</td>
                        <td class="text-end">{{ number_format($row['qty_ordered']) }}</td>
                        <td class="text-end fw-bold">{{ number_format($row['pending']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td>TOTAL</td>
                    <td class="text-end">{{ number_format($suppliers->sum('po_count')) }}</td>
                    <td class="text-end">{{ number_format($suppliers->sum('completed')) }}</td>
                    <td class="text-end">{{ number_format($suppliers->sum('partially_received')) }}</td>
                    <td class="text-end">{{ number_format($suppliers->sum('open')) }}</td>
                    <td class="text-end">{{ number_format($suppliers->sum('closed')) }}</td>
                    <td class="text-end">{{ number_format($suppliers->sum('qty_ordered')) }}</td>
                    <td class="text-end">{{ number_format($suppliers->sum('pending')) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @include('partials.print.signature-block', [
        'columns' => 1,
        'label1' => 'Prepared By', 'value1' => Auth::user()->name ?? '',
    ])
</div>
