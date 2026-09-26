<?php

namespace App\Imports;

use App\Models\Customer;
use App\Models\ImportSkippedRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Sets the Withholding VAT % of customers that already exist, found by exact
 * Customer Name (case-insensitive). Never creates a customer and touches nothing
 * else except the VAT Type, which becomes VAT whenever a % is set (same rule as
 * the Customer form). A blank % keeps the current value; 0 (or "none") removes
 * the customer's Withholding VAT. Anything that can't be applied is skipped to
 * Import Results, so the file can be corrected and imported again.
 */
class CustomerWithholdingVatImport implements ToCollection, WithHeadingRow
{
    public string $batchId;
    public bool $headingsMissing = false;
    public int $updated = 0;
    public int $unchanged = 0;
    public int $ignoredBlank = 0;
    public int $skipped = 0;

    /** @var array<string,array{id:int,rate:?string,vat_type:string,walk_in:bool}> lower-cased name => current values */
    protected array $customers = [];

    /** @var array<string,int> lower-cased name => sheet row already handled */
    protected array $seen = [];

    protected array $skips = [];
    protected int $currentRow = 1;

    public function __construct()
    {
        $this->batchId = (string) Str::uuid();

        Customer::get(['id', 'customer_name', 'withholding_vat_rate', 'vat_type', 'customer_type'])->each(function ($c) {
            $this->customers[mb_strtolower(trim($c->customer_name))] = [
                'id' => $c->id,
                'rate' => $c->withholding_vat_rate,
                'vat_type' => $c->vat_type,
                'walk_in' => $c->isWalkIn(),
            ];
        });
    }

    public function collection(Collection $rows): void
    {
        $first = $rows->first();
        if ($first === null) {
            return;
        }

        if (! $first->has('customer_name') || ! $first->has('withholding_vat')) {
            $this->headingsMissing = true;

            return;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $this->currentRow++;
                $this->processRow($row);
            }
        });

        ImportSkippedRow::record($this->batchId, 'cust-wvat', $this->skips);
    }

    protected function processRow(Collection $row): void
    {
        $name = trim((string) ($row['customer_name'] ?? ''));
        $raw = trim((string) ($row['withholding_vat'] ?? ''));

        if ($name === '' && $raw === '') {
            return;
        }

        $rowData = ['Customer Name' => $name, 'Withholding VAT %' => $raw];

        if ($name === '') {
            $this->skip($rowData, 'Fill in the Customer Name.');

            return;
        }
        if ($raw === '') {
            $this->ignoredBlank++;

            return;
        }

        $key = mb_strtolower($name);
        if (! isset($this->customers[$key])) {
            $this->skip($rowData, "No customer named \"{$name}\" exists. Customers are not created here — use Import Customers or New Customer.");

            return;
        }
        if (isset($this->seen[$key])) {
            $this->skip($rowData, "Same customer as row {$this->seen[$key]} of this file.", ImportSkippedRow::REASON_DUPLICATE_IN_FILE);

            return;
        }

        $clean = str_replace(['%', ' '], '', $raw);
        if (in_array(mb_strtolower($clean), ['none', 'n/a', '-'], true)) {
            $clean = '0';
        }
        if (! is_numeric($clean) || (float) $clean < 0 || (float) $clean > 100) {
            $this->skip($rowData, "The value \"{$raw}\" is not a valid Withholding VAT % (use a number from 0 to 100, or 0 / none to remove it).");

            return;
        }

        if ($this->customers[$key]['walk_in'] && (float) $clean > 0) {
            $this->skip($rowData, 'This customer is a Walk-In (personal use), which is not covered by withholding tax, so it cannot have a Withholding VAT.');

            return;
        }

        $this->seen[$key] = $this->currentRow;

        $rate = round((float) $clean, 2);
        $newRate = $rate > 0 ? $rate : null;
        $current = $this->customers[$key];
        $currentRate = $current['rate'] !== null ? round((float) $current['rate'], 2) : null;

        // A customer that withholds VAT is a VAT customer — same rule as the form.
        $newVatType = $newRate !== null ? 'VAT' : $current['vat_type'];

        if ($newRate === $currentRate && $newVatType === $current['vat_type']) {
            $this->unchanged++;

            return;
        }

        DB::table('customers')->where('id', $current['id'])->update([
            'withholding_vat_rate' => $newRate,
            'vat_type' => $newVatType,
            'updated_at' => now(),
        ]);
        $this->customers[$key]['rate'] = $newRate;
        $this->customers[$key]['vat_type'] = $newVatType;
        $this->updated++;
    }

    protected function skip(array $rowData, string $details, string $reason = ImportSkippedRow::REASON_INVALID_DATA): void
    {
        $this->skipped++;
        $this->skips[] = [
            'sheet_row' => $this->currentRow,
            'reason' => $reason,
            'details' => $details,
            'row_data' => $rowData,
        ];
    }
}
