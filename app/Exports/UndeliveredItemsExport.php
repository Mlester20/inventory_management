<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * The Undelivered Items report as one flat sheet — a row per Sales Order line with a
 * balance, so it can be filtered/pivoted in Excel. Same data as the on-screen report, in
 * the column layout Sir sent (Customer, PO ref, PO date, Generic item, Item description,
 * Price, Qty ordered / delivered, Balance, Qty on-hand). PO REF # is the customer's PO
 * number, or the SO number when the order has none, so a row is never anonymous. Qty
 * on-hand is the Warehouse stock, where deliveries come from.
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
        return ['CUSTOMER NAME', 'PO REF #', 'PO DATE', 'GENERIC ITEM', 'ITEM DESCRIPTION', 'PRICE', 'QTY ORDERED', 'QTY DELIVERED', 'BALANCE', 'QTY ON-HAND'];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->customers as $customer) {
            foreach ($customer['items'] as $item) {
                foreach ($item['orders'] as $order) {
                    $rows[] = [
                        $customer['customer_name'],
                        $order['po_no'] ?: $order['so_no'],
                        $order['order_date'] ? \Illuminate\Support\Carbon::parse($order['order_date'])->format('Y-m-d') : null,
                        $item['generic_label'],
                        $order['product'],
                        $order['price'],
                        $order['ordered'],
                        $order['delivered'],
                        $order['balance'],
                        $order['on_hand'],
                    ];
                }
            }
        }

        return $rows;
    }
}
