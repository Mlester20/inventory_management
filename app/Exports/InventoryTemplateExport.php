<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Blank template matching InventoryOpeningImport's expected columns. The
 * first three identify an existing product (same as the PRODUCTS sheet); Lot
 * No and Expiry Date may be left blank; one row per lot.
 */
class InventoryTemplateExport implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'INVENTORY';
    }

    public function headings(): array
    {
        return ['Category', 'Generic Description', 'Brand', 'Lot No', 'Expiry Date', 'Qty'];
    }

    public function array(): array
    {
        return [
            ['Pain Relief', 'Paracetamol 500mg', 'Biogesic', 'LOT-12345', '2027-12-31', 500],
        ];
    }
}
