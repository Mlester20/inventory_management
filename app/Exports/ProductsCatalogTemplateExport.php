<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Blank template matching ProductsCatalogImport's expected columns
 * (headers become snake_case keys via WithHeadingRow, so these must line
 * up). Cost and Unit Price are optional — leave blank to import at ₱0.00
 * pending real pricing, same as the bulk catalog-only import case.
 */
class ProductsCatalogTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['Category', 'Unit', 'Generic Description', 'Brand', 'Cost', 'Unit Price'];
    }

    public function array(): array
    {
        return [
            ['Pain Relief', 'Tablet', 'Paracetamol 500mg', 'Biogesic', 2.50, 5.00],
        ];
    }
}
