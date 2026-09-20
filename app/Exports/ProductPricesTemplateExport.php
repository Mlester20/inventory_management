<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Blank template matching ProductPricesImport's expected columns. A product is
 * found by Code, or (Code left blank) by Category + Generic Description + Brand.
 * Cost is PHP. Retail is EITHER a mark-up % on Cost (Retail Markup %, price
 * computed) OR a typed Retail Price with the % blank. Wholesale/P1-P3 are the %
 * off Retail and their peso amounts are computed by the system. A blank cell
 * keeps the current value.
 */
class ProductPricesTemplateExport implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'PRICES';
    }

    public function headings(): array
    {
        return ['Code', 'Category', 'Generic Description', 'Brand', 'Cost', 'Retail Markup %', 'Retail Price', 'Wholesale %', 'P1 %', 'P2 %', 'P3 %', 'Tax'];
    }

    public function array(): array
    {
        return [
            [null, 'Pain Relief', 'Paracetamol 500mg', 'Biogesic', 10, 100, null, 10, 8, 6, 4, 'VAT Inc'],
        ];
    }
}
