<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\GenericName;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Does the actual row-by-row work for one "PRODUCTS"-shaped sheet
 * (Category, Unit, Generic Description, Brand, Code/Cost/Unit Price
 * optional). Instantiated and driven by ProductsCatalogImport, which picks
 * this specific sheet out of a real multi-sheet workbook — this class
 * itself doesn't know or care which sheet it was handed, it just processes
 * whatever rows it's given.
 *
 * Unlike SuppliersImport/CustomersImport (one Model per row via ToModel), a
 * product row also needs a Category and a GenericName resolved or created
 * underneath it first, so this uses ToCollection + chunked processing
 * instead — chunkSize() rows at a time, each chunk in its own transaction —
 * with in-memory lookup maps (seeded once, extended as new rows get
 * created) so a ~5k-row run doesn't re-query per row.
 */
class ProductsCatalogSheetImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    /** @var array<string,int> category_name => id */
    protected array $categoryIds;

    /** @var array<string,int> generic_name => id */
    protected array $genericNameIds;

    /** @var array<string,int> generic_name => category_id it was created under */
    protected array $genericNameCategory;

    /** @var array<string,true> "{category}|{generic}|{brand}" already imported this run */
    protected array $seenProductKeys = [];

    /** @var string[] human-readable notes for skipped cross-category rows */
    protected array $skippedCrossCategory = [];

    protected int $nextGenericId;
    protected int $nextProductId;

    public int $categoriesCreated = 0;
    public int $genericNamesCreated = 0;
    public int $productsImported = 0;
    public int $duplicatesSkipped = 0;

    public function __construct()
    {
        $this->categoryIds = Category::pluck('id', 'category_name')->all();

        // Keyed case-insensitively: generic_names.generic_name has a
        // case-insensitive unique index at the DB level (default collation),
        // but a plain PHP array key comparison is case-sensitive — without
        // normalizing, two source rows differing only by case (confirmed in
        // the real data: "GAUZE PAD - STERILE, 3X3"" vs "...3x3"") would
        // both look like a "new" generic name to this map, and the second
        // create() would fail against the DB's own constraint instead of
        // being caught here and reused like it should be.
        $this->genericNameIds = GenericName::pluck('id', 'generic_name')
            ->mapWithKeys(fn ($id, $name) => [$this->genericKey($name) => $id])
            ->all();
        $this->genericNameCategory = GenericName::pluck('category_id', 'generic_name')
            ->mapWithKeys(fn ($categoryId, $name) => [$this->genericKey($name) => $categoryId])
            ->all();

        // Seeded once from the current max — this import is synchronous and
        // transactional, so nothing else is inserting concurrently; matches
        // the same non-atomic "suggested next code" convention already used
        // by InventoryItemsController's $nextGenericCode/$nextProductCode.
        // withTrashed() matters here: a soft-deleted row still occupies its
        // code in the unique index (the exact "trashed record still occupies
        // its number" bug already fixed for the document-number generators —
        // see docs/stock-integrity-bugs-2026-09-fix.md), so max('id') alone
        // would collide with a trashed row's code.
        $this->nextGenericId = (int) GenericName::withTrashed()->max('id');
        $this->nextProductId = (int) Product::withTrashed()->max('id');
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $this->importRow($row);
            }
        });
    }

    protected function importRow(Collection $row): void
    {
        $category = trim((string) ($row['category'] ?? ''));
        $unit = trim((string) ($row['unit'] ?? ''));
        $generic = trim((string) ($row['generic_description'] ?? ''));
        $brand = trim((string) ($row['brand'] ?? '')) ?: null;
        $legacyCode = trim((string) ($row['code'] ?? '')) ?: null;

        // Blank padding row (the used-range of a real spreadsheet often
        // extends past the last real row of data) — nothing to import.
        if ($category === '' || $generic === '') {
            return;
        }

        $productKey = mb_strtolower($category . '|' . $generic . '|' . ($brand ?? ''));
        if (isset($this->seenProductKeys[$productKey])) {
            $this->duplicatesSkipped++;

            return;
        }
        $this->seenProductKeys[$productKey] = true;

        $categoryId = $this->resolveCategory($category);
        $genericKey = $this->genericKey($generic);

        if (isset($this->genericNameIds[$genericKey])) {
            if ($this->genericNameCategory[$genericKey] !== $categoryId) {
                $this->skippedCrossCategory[] = "\"{$generic}\" (row wants \"{$category}\", already exists under a different category)";

                return;
            }
            $genericNameId = $this->genericNameIds[$genericKey];
        } else {
            $genericNameId = $this->createGenericName($generic, $categoryId, $unit);
        }

        Product::create([
            'code' => $this->nextProductCode(),
            'generic_name_id' => $genericNameId,
            'brand_name' => $brand,
            'unit' => $unit,
            'unit_cost' => $this->parseDecimal($row['cost'] ?? null),
            // No source row in the real sheet has pricing today, but a
            // future corrected file (or the template's own Unit Price
            // column) is read here rather than always hardcoded to 0.
            'unit_price' => $this->parseDecimal($row['unit_price'] ?? $row['n_r_php'] ?? null) ?? 0,
            'low_stock_threshold' => 0,
            'custom_field_1' => $legacyCode,
        ]);

        $this->productsImported++;
    }

    protected function resolveCategory(string $name): int
    {
        if (isset($this->categoryIds[$name])) {
            return $this->categoryIds[$name];
        }

        $category = Category::create(['category_name' => $name]);
        $this->categoryIds[$name] = $category->id;
        $this->categoriesCreated++;

        return $category->id;
    }

    protected function createGenericName(string $generic, int $categoryId, string $unit): int
    {
        $genericName = GenericName::create([
            'code' => $this->nextGenericCode(),
            'generic_name' => $generic,
            'category_id' => $categoryId,
            'unit' => $unit ?: 'PC',
            'vat_type' => 'VAT',
        ]);

        $key = $this->genericKey($generic);
        $this->genericNameIds[$key] = $genericName->id;
        $this->genericNameCategory[$key] = $categoryId;
        $this->genericNamesCreated++;

        return $genericName->id;
    }

    protected function genericKey(string $genericName): string
    {
        return mb_strtolower($genericName);
    }

    protected function nextGenericCode(): string
    {
        $this->nextGenericId++;

        return str_pad((string) $this->nextGenericId, 5, '0', STR_PAD_LEFT);
    }

    protected function nextProductCode(): string
    {
        $this->nextProductId++;

        return str_pad((string) $this->nextProductId, 5, '0', STR_PAD_LEFT);
    }

    protected function parseDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @return string[]
     */
    public function crossCategorySkips(): array
    {
        return $this->skippedCrossCategory;
    }
}
