<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The Sales Order Summary item lines as one flat sheet — a row per Sales Order line, so it can be
 * filtered/pivoted in Excel. Same data as the on-screen page, in the column layout Sir sent
 * (Customer, PO ref, PO date, Generic item, Item description, Price, Qty ordered / delivered,
 * Balance, Qty on-hand). PO REF # is the customer's PO number, or the SO number when the order has
 * none, so a row is never anonymous. Qty on-hand is the Warehouse stock, where deliveries come from.
 */
class UndeliveredItemsExport implements FromArray, WithColumnFormatting, WithColumnWidths, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(protected Collection $customers)
    {
    }

    public function title(): string
    {
        return 'SALES ORDER SUMMARY';
    }

    public function headings(): array
    {
        return ['CUSTOMER NAME', 'PO REF #', 'PO DATE', 'GENERIC ITEM', 'ITEM DESCRIPTION', 'PRICE', 'QTY ORDERED', 'QTY DELIVERED', 'BALANCE', 'QTY ON-HAND'];
    }

    /** Wide enough to read on opening, so nothing shows cut off (customer names, dates, descriptions). */
    public function columnWidths(): array
    {
        return ['A' => 44, 'B' => 18, 'C' => 13, 'D' => 34, 'E' => 46, 'F' => 14, 'G' => 14, 'H' => 15, 'I' => 12, 'J' => 14];
    }

    public function columnFormats(): array
    {
        return ['F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1];
    }

    public function styles(Worksheet $sheet): array
    {
        // Long item descriptions wrap inside their cell instead of running off it.
        $sheet->getStyle('D:E')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A:J')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        return [1 => ['font' => ['bold' => true]]];
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
                        $order['order_date'] ? Carbon::parse($order['order_date'])->format('Y-m-d') : null,
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
