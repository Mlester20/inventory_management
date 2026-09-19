<?php

namespace App\Imports;

use App\Models\ImportSkippedRow;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Services\InventoryAdjustmentService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Opening stock import: one row = one lot of one product (Category, Generic
 * Description, Brand, Lot No, Expiry Date, Qty). Never creates products — a
 * row whose product isn't in the system is skipped and reported. All valid
 * rows are posted as ONE "Opening Balance" Inventory Adjustment through
 * InventoryAdjustmentService, so the stock lands in the Warehouse with the
 * normal ledger/Product History entries and can be written off as a unit.
 *
 * A lot is identified by Product + Lot No (blank counts as its own lot): two
 * different products may share a Lot No and Expiry, but the same product
 * can't get the same lot twice, so re-importing a file adds nothing.
 * InventoryAdjustmentService alone would double-count here — it restocks an
 * existing lot and creates a fresh lot for every blank-lot line.
 */
class InventoryOpeningImport implements ToCollection, WithHeadingRow
{
    public string $batchId;
    public bool $headingsMissing = false;
    public int $linesImported = 0;
    public int $unitsImported = 0;
    public int $skipped = 0;
    public int $ignoredNoQty = 0;
    public ?InventoryAdjustment $adjustment = null;

    /** @var array<string,int> "{category}|{generic}|{brand}" => product id */
    protected array $productIds = [];

    /** @var array<string,true> "{productId}|{lot}" lots already in the DB */
    protected array $existingLots = [];

    /** @var array<string,int> "{productId}|{lot}" => sheet row first seen in this file */
    protected array $seenLots = [];

    protected array $skips = [];
    protected array $lines = [];
    protected int $currentRow = 1;

    public function __construct(protected InventoryAdjustmentService $adjustments, protected ?int $userId = null, protected string $fileName = '')
    {
        $this->batchId = (string) Str::uuid();

        Product::query()
            ->join('generic_names as g', 'g.id', '=', 'products.generic_name_id')
            ->join('categories as c', 'c.id', '=', 'g.category_id')
            ->orderBy('products.id')
            ->get(['products.id', 'products.brand_name', 'g.generic_name', 'c.category_name'])
            ->each(function ($product) {
                $key = mb_strtolower($product->category_name . '|' . $product->generic_name . '|' . ($product->brand_name ?? ''));
                $this->productIds[$key] ??= $product->id;
            });

        DB::table('product_batches')->select('product_id', 'batch_no')->get()->each(function ($batch) {
            $this->existingLots[$batch->product_id . '|' . mb_strtolower(trim((string) $batch->batch_no))] = true;
        });
    }

    public function collection(Collection $rows): void
    {
        $first = $rows->first();
        if ($first === null) {
            return;
        }

        foreach (['category', 'generic_description', 'qty'] as $required) {
            if (! $first->has($required)) {
                $this->headingsMissing = true;

                return;
            }
        }

        foreach ($rows as $row) {
            $this->currentRow++;
            $this->processRow($row);
        }

        ImportSkippedRow::record($this->batchId, 'inventory', $this->skips);

        if ($this->lines !== []) {
            $this->adjustment = $this->adjustments->createAdjustment([
                'adjustment_date' => now()->toDateString(),
                'adjustment_type' => 'opening_balance',
                'description' => 'Opening balance imported from Excel' . ($this->fileName !== '' ? " ({$this->fileName})" : ''),
                'prepared_by' => $this->userId,
                'lines' => $this->lines,
            ], $this->userId);
        }
    }

    protected function processRow(Collection $row): void
    {
        $category = trim((string) ($row['category'] ?? ''));
        $generic = trim((string) ($row['generic_description'] ?? ''));
        $brand = trim((string) ($row['brand'] ?? '')) ?: null;
        $qtyRaw = $row['qty'] ?? null;
        $lotRaw = $row['lot_no'] ?? $row['lot'] ?? $row['batch_no'] ?? null;
        $expiryRaw = $row['expiry_date'] ?? $row['expiration_date'] ?? null;

        $noQty = $qtyRaw === null || trim((string) $qtyRaw) === '' || (is_numeric($qtyRaw) && (float) $qtyRaw == 0.0);

        // Padding rows and products with nothing in stock — expected in a full
        // item list, so counted rather than reported as skipped.
        if ($noQty) {
            if ($category !== '' || $generic !== '') {
                $this->ignoredNoQty++;
            }

            return;
        }

        $lot = $this->normalizeLot($lotRaw);
        $rowData = [
            'Category' => $category,
            'Generic Description' => $generic,
            'Brand' => $brand,
            'Lot No' => $lot,
            'Expiry Date' => is_scalar($expiryRaw) ? (string) $expiryRaw : null,
            'Qty' => is_scalar($qtyRaw) ? (string) $qtyRaw : null,
        ];

        if ($category === '' || $generic === '') {
            $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, 'Category and Generic Description are required.');

            return;
        }

        if (! is_numeric($qtyRaw) || (float) $qtyRaw < 0 || (float) $qtyRaw != (int) $qtyRaw) {
            $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, 'Qty must be a whole number greater than 0.');

            return;
        }

        try {
            $expiry = $this->parseExpiry($expiryRaw);
        } catch (\Throwable) {
            $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, 'Expiry Date is not a valid date (use YYYY-MM-DD).');

            return;
        }

        $productKey = mb_strtolower($category . '|' . $generic . '|' . ($brand ?? ''));
        $productId = $this->productIds[$productKey] ?? null;
        if ($productId === null) {
            $this->skip($rowData, ImportSkippedRow::REASON_PRODUCT_NOT_FOUND, 'No product with this Category, Generic Description and Brand exists. Import it on the PRODUCTS sheet first.');

            return;
        }

        $lotKey = $productId . '|' . mb_strtolower($lot ?? '');
        if (isset($this->seenLots[$lotKey])) {
            $this->skip($rowData, ImportSkippedRow::REASON_DUPLICATE_IN_FILE, "Same product and lot as row {$this->seenLots[$lotKey]} of this file.");

            return;
        }
        $this->seenLots[$lotKey] = $this->currentRow;

        if (isset($this->existingLots[$lotKey])) {
            $this->skip($rowData, ImportSkippedRow::REASON_ALREADY_IN_SYSTEM, $lot === null
                ? 'This product already has a lot with no lot number.'
                : "This product already has lot \"{$lot}\".");

            return;
        }

        $this->lines[] = [
            'product_id' => $productId,
            'batch_no' => $lot,
            'expiration_date' => $expiry,
            'qty' => (int) $qtyRaw,
            'remarks' => 'Opening balance import',
        ];
        $this->linesImported++;
        $this->unitsImported += (int) $qtyRaw;
    }

    protected function skip(array $rowData, string $reason, string $details): void
    {
        $this->skipped++;
        $this->skips[] = [
            'sheet_row' => $this->currentRow,
            'reason' => $reason,
            'details' => $details,
            'row_data' => $rowData,
        ];
    }

    protected function normalizeLot(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (is_float($raw) && $raw == (int) $raw) {
            $raw = (int) $raw;
        }

        return trim((string) $raw) === '' ? null : trim((string) $raw);
    }

    protected function parseExpiry(mixed $raw): ?string
    {
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
        }

        return Carbon::parse(trim((string) $raw))->format('Y-m-d');
    }
}
