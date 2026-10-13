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
 * The Purchase Order Summary item lines as one flat sheet — a row per Purchase Order line, so it
 * can be filtered/pivoted in Excel. Mirrors UndeliveredItemsExport's column layout (Supplier, PO
 * ref, PO date, Generic item, Item description, Unit cost, Qty ordered / received, Balance, Qty
 * on-hand split Warehouse / POS).
 */
class PendingPurchaseItemsExport implements FromArray, WithColumnFormatting, WithColumnWidths, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(protected Collection $suppliers)
    {
    }

    public function title(): string
    {
        return 'PURCHASE ORDER SUMMARY';
    }

    public function headings(): array
    {
        return ['SUPPLIER NAME', 'PO REF #', 'PO DATE', 'GENERIC ITEM', 'ITEM DESCRIPTION', 'UNIT COST', 'QTY ORDERED', 'QTY RECEIVED', 'BALANCE', 'QTY ON-HAND (WAREHOUSE)', 'QTY ON-HAND (POS)'];
    }

    /** Wide enough to read on opening, so nothing shows cut off (supplier names, dates, descriptions). */
    public function columnWidths(): array
    {
        return ['A' => 44, 'B' => 18, 'C' => 13, 'D' => 34, 'E' => 46, 'F' => 14, 'G' => 14, 'H' => 15, 'I' => 12, 'J' => 16, 'K' => 12];
    }

    public function columnFormats(): array
    {
        return ['F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1];
    }

    public function styles(Worksheet $sheet): array
    {
        // Long item descriptions wrap inside their cell instead of running off it.
        $sheet->getStyle('D:E')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A:K')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        return [1 => ['font' => ['bold' => true]]];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->suppliers as $supplier) {
            foreach ($supplier['items'] as $item) {
                foreach ($item['orders'] as $order) {
                    $rows[] = [
                        $supplier['supplier_name'],
                        $order['po_no'],
                        $order['order_date'] ? Carbon::parse($order['order_date'])->format('Y-m-d') : null,
                        $item['generic_label'],
                        $order['product'],
                        $order['unit_cost'],
                        $order['ordered'],
                        $order['received'],
                        $order['balance'],
                        $order['on_hand']['warehouse'],
                        $order['on_hand']['pos'],
                    ];
                }
            }
        }

        return $rows;
    }
}
