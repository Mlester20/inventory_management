<?php

namespace App\Imports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Bulk Supplier import — synchronous (no queue/chunking needed, supplier
 * lists are small, not the 20k-row product-import scale this package was
 * originally installed for — see docs/excel-import-plan.md).
 *
 * Same required fields as the manual "New Supplier" form
 * (SupplierController::store()), so an imported row is never less valid
 * than one typed in by hand. Rows are validated and saved one at a time
 * (no chunking/batch-inserts), so the unique: rules below catch a
 * duplicate against an already-imported row from earlier in the same
 * file just as well as one already in the DB — confirmed live, no
 * separate in-memory duplicate tracking needed.
 */
class SuppliersImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    public int $importedCount = 0;

    public function model(array $row)
    {
        $this->importedCount++;

        return new Supplier([
            'supplier_name' => trim($row['supplier_name'] ?? ''),
            'contact_person' => trim($row['contact_person'] ?? ''),
            'contact_number' => trim((string) ($row['contact_number'] ?? '')),
            'email' => trim($row['email'] ?? ''),
            'delivery_address' => trim($row['delivery_address'] ?? ''),
            'vat_type' => strtoupper(trim($row['vat_type'] ?? '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'supplier_name' => 'required|string|max:255|unique:suppliers,supplier_name',
            'contact_person' => 'required|string|max:255',
            'contact_number' => 'required|string|max:255|unique:suppliers,contact_number',
            'email' => 'required|email|unique:suppliers,email',
            'delivery_address' => 'required|string',
            'vat_type' => ['required', function ($attribute, $value, $fail) {
                if (! in_array(strtoupper(trim((string) $value)), ['VAT', 'NON-VAT'], true)) {
                    $fail('VAT Type must be exactly "VAT" or "NON-VAT".');
                }
            }],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'supplier_name.unique' => 'A supplier with this name already exists.',
            'email.unique' => 'A supplier with this email already exists.',
            'contact_number.unique' => 'A supplier with this contact number already exists.',
        ];
    }
}
