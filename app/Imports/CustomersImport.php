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
            // Blank in the source spreadsheet falls back to the same
            // defaults the customers table itself uses (retail / VAT) —
            // matches the manual "New Customer" form's own defaults, it's
            // only the source data that's missing an explicit choice, not
            // a case where "no price level" is a meaningful state.
            'price_level' => $this->resolvePriceLevel($row['price_level'] ?? '') ?? 'retail',
            'vat_type' => strtoupper(trim($row['vat_type'] ?? '')) ?: 'VAT',
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_name' => 'required|string|max:255|unique:customers,customer_name',
            'customer_type' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            // Not 'string' — a phone-number-looking cell in the source
            // spreadsheet can be read as a real PHP int/float when it isn't
            // formatted as text, which Laravel's `string` rule then rejects
            // outright (same fix as SuppliersImport). model() above already
            // casts to string before saving.
            'contact_number' => 'nullable|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'delivery_address' => 'nullable|string',
            // Blank is allowed here (defaulted in model() above) — only a
            // present-but-unrecognized value is rejected.
            'price_level' => ['nullable', function ($attribute, $value, $fail) {
                if (trim((string) $value) !== '' && $this->resolvePriceLevel($value) === null) {
                    $fail('Price Level must be one of: ' . implode(', ', Customer::PRICE_LEVELS) . '.');
                }
            }],
            'vat_type' => ['nullable', function ($attribute, $value, $fail) {
                if (trim((string) $value) !== '' && ! in_array(strtoupper(trim((string) $value)), ['VAT', 'NON-VAT'], true)) {
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
