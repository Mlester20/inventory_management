<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Blank template matching ProductPricesImport's expected columns. A product is
 * found by Code, or (Code left blank) by Category + Generic Description + Brand
 * — Unit is only needed for that name-based lookup when the same Category/
 * Generic Description/Brand exists in more than one packaging (e.g. a BX and a
 * PC of the same item). Item Description and New Brand change the product's
 * text and need the Code.
 * Cost is PHP. Retail is EITHER a mark-up % on Cost (Retail Markup %, price
 * computed) OR a typed Retail Price with the % blank. Wholesale/P1-P3 are the %
 * off Retail and their peso amounts are computed by the system. Product Type
 * (Goods or Services) belongs to the Generic Item, so every product row of the
 * same Generic Description and Unit must agree. A blank cell keeps the current
 * value.
 */
class ProductPricesTemplateExport implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'PRICES';
    }

    public function headings(): array
    {
        return ['Code', 'Category', 'Generic Description', 'Brand', 'Unit', 'Item Description', 'New Brand', 'Cost', 'Retail Markup %', 'Retail Price', 'Wholesale %', 'P1 %', 'P2 %', 'P3 %', 'Tax', 'Product Type'];
    }

    public function array(): array
    {
        return [
            ['00001', 'Pain Relief', 'Paracetamol 500mg', 'Biogesic', 'BX', 'Pain reliever and fever reducer', null, 10, 100, null, 10, 8, 6, 4, 'VAT Inc', 'Goods'],
        ];
    }
}
