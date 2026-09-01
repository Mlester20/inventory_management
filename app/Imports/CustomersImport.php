<?php

namespace App\Imports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Bulk Customer import — synchronous, same reasoning as SuppliersImport
 * (small list, no queue/chunking needed). Same required fields as the
 * manual "New Customer" form (CustomerController::store()): only
 * customer_name, customer_type, price_level, and vat_type are required —
 * contact details are optional here just like on that form.
 */
class CustomersImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    public int $importedCount = 0;

    public function model(array $row)
    {
        $this->importedCount++;

        return new Customer([
            'customer_name' => trim($row['customer_name'] ?? ''),
            'customer_type' => trim($row['customer_type'] ?? ''),
            'contact_person' => trim($row['contact_person'] ?? '') ?: null,
            'contact_number' => trim((string) ($row['contact_number'] ?? '')) ?: null,
            'email' => trim($row['email'] ?? '') ?: null,
            'delivery_address' => trim($row['delivery_address'] ?? '') ?: null,
            'price_level' => $this->resolvePriceLevel($row['price_level'] ?? ''),
            'vat_type' => strtoupper(trim($row['vat_type'] ?? '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_name' => 'required|string|max:255|unique:customers,customer_name',
            'customer_type' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'delivery_address' => 'nullable|string',
            'price_level' => ['required', function ($attribute, $value, $fail) {
                if ($this->resolvePriceLevel($value) === null) {
                    $fail('Price Level must be one of: ' . implode(', ', Customer::PRICE_LEVELS) . '.');
                }
            }],
            'vat_type' => ['required', function ($attribute, $value, $fail) {
                if (! in_array(strtoupper(trim((string) $value)), ['VAT', 'NON-VAT'], true)) {
                    $fail('VAT Type must be exactly "VAT" or "NON-VAT".');
                }
            }],
        ];
    }

    /**
     * Accepts either the internal key (e.g. "wholesale") or the
     * human-readable label shown in the dropdown (e.g. "Wholesale",
     * "P. Level 3"), case-insensitively — a spreadsheet is far more
     * likely to contain the label than the internal key.
     */
    private function resolvePriceLevel(?string $raw): ?string
    {
        $raw = mb_strtolower(trim((string) $raw));

        foreach (Customer::PRICE_LEVELS as $key => $label) {
            if ($raw === mb_strtolower($key) || $raw === mb_strtolower($label)) {
                return $key;
            }
        }

        return null;
    }

    public function customValidationMessages(): array
    {
        return [
            'customer_name.unique' => 'A customer with this name already exists.',
            'email.unique' => 'A customer with this email already exists.',
        ];
    }
}
