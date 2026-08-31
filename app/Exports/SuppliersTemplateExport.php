<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Blank template with one example row, matching SuppliersImport's expected
 * columns exactly (headers become snake_case keys via WithHeadingRow, so
 * these must line up).
 */
class SuppliersTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Supplier Name', 'Contact Person', 'Contact Number', 'Email', 'Delivery Address', 'VAT Type',
        ];
    }

    public function array(): array
    {
        return [
            ['MedSupply Philippines Inc.', 'Juan Dela Cruz', '09171234567', 'juan@medsupply.ph', '123 Rizal St., Manila', 'VAT'],
        ];
    }
}
