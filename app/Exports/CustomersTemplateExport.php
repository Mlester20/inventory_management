<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Blank template with one example row, matching CustomersImport's expected
 * columns. Price Level uses the human-readable label (e.g. "Wholesale") —
 * CustomersImport accepts either that or the internal key.
 */
class CustomersTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Customer Name', 'Customer Type', 'Contact Person', 'Contact Number', 'Email', 'Delivery Address', 'Price Level', 'VAT Type',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Green Cross Pharmacy', 'Pharmacy', 'Maria Santos', '09181234567', 'maria@greencross.ph',
                '45 Bonifacio Ave., Quezon City', Customer::PRICE_LEVELS['wholesale'], 'VAT',
            ],
        ];
    }
}
