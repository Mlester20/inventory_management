<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Blank template matching StockCountImport's columns. A product is found by
 * Code, or (Code left blank) by Category + Generic Description + Brand; the lot
 * by Lot No (blank = the product's lot without a number). Counted Qty is the
 * physical count — leave it blank to skip a lot, 0 means none on hand. Unit is
 * only needed (with Code blank) when the same Category/Generic Description/
 * Brand exists in more than one packaging (e.g. a BX and a PC of the same item).
 */
class StockCountTemplateExport implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'COUNT';
    }

    public function headings(): array
    {
        return ['Code', 'Category', 'Generic Description', 'Brand', 'Unit', 'Lot No', 'Counted Qty'];
    }

    public function array(): array
    {
        return [
            [null, 'Pain Relief', 'Paracetamol 500mg', 'Biogesic', 'BX', 'LOT-12345', 480],
        ];
    }
}
