<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * The Undelivered Items report as one flat sheet — a row per Sales Order line with a
 * balance, so it can be filtered/pivoted in Excel. Same data as the on-screen report.
 */
class UndeliveredItemsExport implements FromArray, WithHeadings, WithStrictNullComparison, WithTitle
{
    public function __construct(protected Collection $customers)
    {
    }

    public function title(): string
    {
        return 'UNDELIVERED';
    }

    public function headings(): array
    {
        return ['Customer', 'Item (Generic Description)', 'Item Description', 'SO No.', 'PO No.', 'SO Date', 'Ordered', 'Delivered', 'Undelivered'];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->customers as $customer) {
            foreach ($customer['items'] as $item) {
                foreach ($item['orders'] as $order) {
                    $rows[] = [
                        $customer['customer_name'],
                        $item['generic_label'],
                        $order['product'],
                        $order['so_no'],
                        $order['po_no'],
                        $order['order_date'] ? \Illuminate\Support\Carbon::parse($order['order_date'])->format('Y-m-d') : null,
                        $order['ordered'],
                        $order['delivered'],
                        $order['balance'],
                    ];
                }
            }
        }

        return $rows;
    }
}
