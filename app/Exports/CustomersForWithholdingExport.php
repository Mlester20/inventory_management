<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Every current customer with their Withholding VAT %, in the layout
 * CustomerWithholdingVatImport reads back — so the % can be filled in Excel
 * for the Government accounts instead of editing each customer by hand.
 * Blank = the customer has no Withholding VAT.
 */
class CustomersForWithholdingExport implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'CUSTOMERS';
    }

    public function headings(): array
    {
        return ['Customer Name', 'Customer Type', 'Withholding VAT %'];
    }

    public function array(): array
    {
        return Customer::orderBy('customer_name')
            ->get(['customer_name', 'customer_type', 'withholding_vat_rate'])
            ->map(fn ($c) => [
                $c->customer_name,
                $c->customer_type,
                $c->withholding_vat_rate !== null ? (float) $c->withholding_vat_rate : null,
            ])
            ->all();
    }
}
