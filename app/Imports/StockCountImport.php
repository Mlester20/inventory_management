<?php

namespace App\Imports;

use App\Models\ImportSkippedRow;
use App\Models\InventoryAdjustment;
use App\Models\Location;
use App\Models\Product;
use App\Services\InventoryAdjustmentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Physical stock count: sets the Warehouse quantity of EXISTING lots to the
 * counted figure. One row = one lot (product by Code, or Category + Generic
 * Description + Brand; lot by Lot No, blank = the product's no-lot batch).
 *
 * Never creates a product or a lot — an unknown one is skipped to Import
 * Results (Opening Inventory adds new lots). The difference between the system
 * quantity and the count is posted through InventoryAdjustmentService as one
 * "Correction - Increase" and one "Correction - Decrease" adjustment, so every
 * change is in the ledger / Product History and can be written off. A blank
 * Counted Qty leaves the lot alone; 0 means "counted, none on hand".
 */
class StockCountImport implements ToCollection, WithHeadingRow
{
    public string $batchId;
    public bool $headingsMissing = false;
    public int $increased = 0;
    public int $decreased = 0;
    public int $unitsAdded = 0;
    public int $unitsRemoved = 0;
    public int $unchanged = 0;
    public int $ignoredBlank = 0;
    public int $skipped = 0;
    public ?InventoryAdjustment $increaseAdjustment = null;
    public ?InventoryAdjustment $decreaseAdjustment = null;

    /** @var array<string,int> "{category}|{generic}|{brand}" => product id */
    protected array $productIds = [];

    /** @var array<string,int> lower-cased system code => product id */
    protected array $codeIds = [];

    /** @var array<string,int> "{productId}|{lot}" => product batch id */
    protected array $batchIds = [];

    /** @var array<int,int> product batch id => current Warehouse quantity */
    protected array $warehouseQty = [];

    /** @var array<string,int> "{productId}|{lot}" => sheet row already handled */
    protected array $seen = [];

    protected array $skips = [];
    protected array $increaseLines = [];
    protected array $decreaseLines = [];
    protected int $currentRow = 1;

    public function __construct(protected InventoryAdjustmentService $adjustments, protected ?int $userId = null, protected string $fileName = '')
    {
        $this->batchId = (string) Str::uuid();

        Product::query()
            ->join('generic_names as g', 'g.id', '=', 'products.generic_name_id')
            ->join('categories as c', 'c.id', '=', 'g.category_id')
            ->orderBy('products.id')
            ->get(['products.id', 'products.code', 'products.brand_name', 'g.generic_name', 'c.category_name'])
            ->each(function ($product) {
                $key = mb_strtolower($product->category_name . '|' . $product->generic_name . '|' . ($product->brand_name ?? ''));
                $this->productIds[$key] ??= $product->id;
                $this->codeIds[mb_strtolower((string) $product->code)] = $product->id;
            });

        DB::table('product_batches')->select('id', 'product_id', 'batch_no')->orderBy('id')->get()->each(function ($batch) {
            $this->batchIds[$batch->product_id . '|' . mb_strtolower(trim((string) $batch->batch_no))] ??= $batch->id;
        });

        $this->warehouseQty = DB::table('location_stocks')
            ->where('location_id', Location::warehouse()->id)
            ->pluck('qty', 'product_batch_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }

    public function collection(Collection $rows): void
    {
        $first = $rows->first();
        if ($first === null) {
            return;
        }

        $hasProduct = $first->has('code') || ($first->has('category') && $first->has('generic_description'));
        $hasCount = $first->has('counted_qty') || $first->has('qty') || $first->has('count');
        if (! $hasProduct || ! $hasCount) {
            $this->headingsMissing = true;

            return;
        }

        foreach ($rows as $row) {
            $this->currentRow++;
            $this->processRow($row);
        }

        ImportSkippedRow::record($this->batchId, 'count', $this->skips);

        DB::transaction(function () {
            if ($this->increaseLines !== []) {
                $this->increaseAdjustment = $this->postAdjustment('correction_increase', $this->increaseLines);
            }

            if ($this->decreaseLines !== []) {
                $this->decreaseAdjustment = $this->postAdjustment('correction_decrease', $this->decreaseLines);
            }
        });
    }

    protected function postAdjustment(string $type, array $lines): InventoryAdjustment
    {
        return $this->adjustments->createAdjustment([
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => $type,
            'description' => 'Stock count imported from Excel' . ($this->fileName !== '' ? " ({$this->fileName})" : ''),
            'prepared_by' => $this->userId,
            'lines' => $lines,
        ], $this->userId);
    }

    protected function processRow(Collection $row): void
    {
        $code = $this->firstPresent($row, ['code', 'product_code']);
        $category = trim((string) ($row['category'] ?? ''));
        $generic = trim((string) ($row['generic_description'] ?? ''));
        $brand = trim((string) ($row['brand'] ?? '')) ?: null;
        $lotRaw = $this->firstPresent($row, ['lot_no', 'lot', 'batch_no']);
        $countedRaw = $this->firstPresent($row, ['counted_qty', 'qty', 'count', 'physical_count']);

        if ($code === null && $category === '' && $generic === '' && $countedRaw === null) {
            return;
        }

        $lot = $this->normalizeLot($lotRaw);
        $rowData = [
            'Code' => $code,
            'Category' => $category,
            'Generic Description' => $generic,
            'Brand' => $brand,
            'Lot No' => $lot,
            'Counted Qty' => $countedRaw,
        ];

        if ($countedRaw === null) {
            $this->ignoredBlank++;

            return;
        }

        if ($code === null && ($category === '' || $generic === '')) {
            $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, 'Fill the product Code, or both Category and Generic Description.');

            return;
        }

        $counted = str_replace([',', ' '], '', $countedRaw);
        if (! is_numeric($counted) || (float) $counted < 0 || (float) $counted != (int) $counted) {
            $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, 'Counted Qty must be a whole number, 0 or more.');

            return;
        }
        $counted = (int) $counted;

        $productId = $code !== null
            ? ($this->codeIds[$this->normalizeCode($code)] ?? null)
            : ($this->productIds[mb_strtolower($category . '|' . $generic . '|' . ($brand ?? ''))] ?? null);

        if ($productId === null) {
            $this->skip($rowData, ImportSkippedRow::REASON_PRODUCT_NOT_FOUND, $code !== null
                ? "No product with Code \"{$code}\" exists."
                : 'No product with this Category, Generic Description and Brand exists.');

            return;
        }

        $lotKey = $productId . '|' . mb_strtolower($lot ?? '');
        $batchId = $this->batchIds[$lotKey] ?? null;
        if ($batchId === null) {
            $this->skip($rowData, ImportSkippedRow::REASON_LOT_NOT_FOUND, ($lot === null
                ? 'This product has no lot without a lot number.'
                : "This product has no lot \"{$lot}\".") . ' A stock count only corrects existing lots — add a new lot with Import Opening Inventory.');

            return;
        }

        if (isset($this->seen[$lotKey])) {
            $this->skip($rowData, ImportSkippedRow::REASON_DUPLICATE_IN_FILE, "Same product and lot as row {$this->seen[$lotKey]} of this file.");

            return;
        }
        $this->seen[$lotKey] = $this->currentRow;

        $current = $this->warehouseQty[$batchId] ?? 0;
        $difference = $counted - $current;

        if ($difference === 0) {
            $this->unchanged++;

            return;
        }

        $line = [
            'product_id' => $productId,
            'product_batch_id' => $batchId,
            'qty' => abs($difference),
            'remarks' => "Stock count: system {$current}, counted {$counted}",
        ];

        if ($difference > 0) {
            $this->increaseLines[] = $line;
            $this->increased++;
            $this->unitsAdded += $difference;
        } else {
            $this->decreaseLines[] = $line;
            $this->decreased++;
            $this->unitsRemoved += -$difference;
        }
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

    protected function firstPresent(Collection $row, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (! $row->has($alias)) {
                continue;
            }
            $value = $row[$alias];
            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    protected function normalizeLot(?string $raw): ?string
    {
        return $raw === null || trim($raw) === '' ? null : trim($raw);
    }

    /** System codes are zero-padded strings ("00042"); Excel may hand back the number 42. */
    protected function normalizeCode(string $code): string
    {
        $code = mb_strtolower(trim($code));

        return ctype_digit($code) ? str_pad(ltrim($code, '0') === '' ? '0' : ltrim($code, '0'), 5, '0', STR_PAD_LEFT) : $code;
    }
}
