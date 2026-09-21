<?php

namespace App\Imports;

use App\Models\ImportSkippedRow;
use App\Models\Product;
use App\Models\Taxes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Updates Cost, Retail price (PHP), the Wholesale/P1-P3 price-level percents
 * and Tax of products that already exist. Never creates products.
 *
 * A row finds its product by the system Code when the Code cell is filled
 * (what the "Export products" file provides), otherwise by Category +
 * Generic Description + Brand (same key as the products import); anything that
 * matches nothing is skipped to Import Results. A blank cell keeps the current
 * value, so a partial sheet never wipes what's already entered, and the file
 * can be corrected and imported again at any time.
 *
 * Retail is entered either as a mark-up % on Cost (price = cost x (1 + %/100))
 * or as a peso Retail Price with the % left blank (if both are filled and
 * disagree, the Retail Price is kept and the % is dropped). Wholesale/P1-P3 are a % OFF
 * Retail and their peso amount is computed (retail x (1 - percent/100)), and
 * recomputed whenever Retail or Cost changes, so the two never drift apart.
 */
class ProductPricesImport implements ToCollection, WithHeadingRow
{
    /** Header aliases (after WithHeadingRow slugging) per amount field. */
    protected const AMOUNTS = [
        'unit_cost' => ['cost', 'unit_cost'],
        'unit_price' => ['retail', 'retail_price', 'unit_price', 'n_r_php'],
    ];

    /** Retail is a MARK-UP % on Cost (Retail = Cost x (1 + %/100)); the other levels are % off Retail. */
    protected const RETAIL_MARKUP = ['retail_markup', 'retail_markup_percent', 'retail_percent', 'n_r'];

    /** Price level: percent field => [price field, header aliases]. */
    protected const TIERS = [
        'wholesale_percent' => ['wholesale_price', ['wholesale', 'wholesale_percent', 'ws', 'n_ws']],
        'price_1_percent' => ['price_1', ['p1', 'p1_percent', 'price_level_1', 'p_level_1', 'n_p1']],
        'price_2_percent' => ['price_2', ['p2', 'p2_percent', 'price_level_2', 'p_level_2', 'n_p2']],
        'price_3_percent' => ['price_3', ['p3', 'p3_percent', 'price_level_3', 'p_level_3', 'n_p3']],
    ];

    public string $batchId;
    public bool $headingsMissing = false;
    public int $updated = 0;
    public int $unchanged = 0;
    public int $ignoredBlank = 0;
    public int $skipped = 0;

    /** Rows where a Markup % disagreed with the Retail Price: price kept, % left blank. */
    public int $percentOverridden = 0;

    /** @var array<string,int> "{category}|{generic}|{brand}" => product id */
    protected array $productIds = [];

    /** @var array<string,int> lower-cased system code => product id */
    protected array $codeIds = [];

    /** @var array<int,string> product id => lower-cased "{category}|{generic}|" (a product's identity minus its Brand) */
    protected array $identity = [];

    /** @var array<int,array> product id => current cost/price/percent/tax values */
    protected array $current = [];

    /** @var array<int,int> product id => sheet row already handled in this file */
    protected array $seen = [];

    protected array $skips = [];
    protected int $currentRow = 1;
    protected ?Taxes $vatTax;
    protected ?Taxes $zeroTax;

    public function __construct()
    {
        $this->batchId = (string) Str::uuid();
        // By rate, not by name: the Taxes page lets admins rename/create tax rows
        // freely (e.g. "VAT" -> "VAT-INC"), so matching a literal name here would
        // silently break. This mirrors Product::taxClassification() exactly:
        // rate > 0 is VATable, rate = 0 is Zero-Rated. Prefer the active one of
        // each so a deactivated leftover doesn't get picked over the real one.
        $this->vatTax = Taxes::where('rate', '>', 0)->orderByDesc('is_active')->orderBy('id')->first();
        $this->zeroTax = Taxes::where('rate', 0)->orderByDesc('is_active')->orderBy('id')->first();

        $fields = ['unit_cost', 'unit_price', 'unit_price_percent', 'tax_id'];
        foreach (self::TIERS as $percentField => [$priceField]) {
            $fields[] = $percentField;
            $fields[] = $priceField;
        }

        Product::query()
            ->join('generic_names as g', 'g.id', '=', 'products.generic_name_id')
            ->join('categories as c', 'c.id', '=', 'g.category_id')
            ->orderBy('products.id')
            ->get(array_merge(
                ['products.id', 'products.code', 'products.brand_name', 'products.description', 'g.generic_name', 'c.category_name'],
                array_map(fn ($field) => "products.{$field}", $fields),
            ))
            ->each(function ($product) use ($fields) {
                $key = mb_strtolower($product->category_name . '|' . $product->generic_name . '|' . ($product->brand_name ?? ''));
                $this->productIds[$key] ??= $product->id;
                $this->codeIds[mb_strtolower((string) $product->code)] = $product->id;
                $this->identity[$product->id] = mb_strtolower($product->category_name . '|' . $product->generic_name . '|');
                $this->current[$product->id] = $product->only(array_merge($fields, ['brand_name', 'description']));
            });
    }

    public function collection(Collection $rows): void
    {
        $first = $rows->first();
        if ($first === null) {
            return;
        }

        $hasCode = $first->has('code');
        $hasName = $first->has('category') && $first->has('generic_description');
        if (! $hasCode && ! $hasName) {
            $this->headingsMissing = true;

            return;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $this->currentRow++;
                $this->processRow($row);
            }
        });

        ImportSkippedRow::record($this->batchId, 'prices', $this->skips);
    }

    protected function processRow(Collection $row): void
    {
        $code = $this->firstPresent($row, ['code', 'product_code']);
        $category = trim((string) ($row['category'] ?? ''));
        $generic = trim((string) ($row['generic_description'] ?? ''));
        $brand = trim((string) ($row['brand'] ?? '')) ?: null;

        $amounts = [];
        foreach (self::AMOUNTS as $field => $aliases) {
            $amounts[$field] = $this->firstPresent($row, $aliases);
        }
        $retailMarkupRaw = $this->firstPresent($row, self::RETAIL_MARKUP);
        $percents = [];
        foreach (self::TIERS as $percentField => [, $aliases]) {
            $percents[$percentField] = $this->firstPresent($row, $aliases);
        }
        $taxRaw = $this->firstPresent($row, ['tax', 'n_tax']);
        $descriptionRaw = $this->firstPresent($row, ['item_description']);
        $newBrandRaw = $this->firstPresent($row, ['new_brand']);

        $requested = array_merge($amounts, $percents, ['tax' => $taxRaw, 'retail_markup' => $retailMarkupRaw, 'description' => $descriptionRaw, 'new_brand' => $newBrandRaw]);
        $noChangeRequested = collect($requested)->every(fn ($v) => $v === null);

        if ($code === null && $category === '' && $generic === '' && $noChangeRequested) {
            return;
        }

        $rowData = [
            'Code' => $code,
            'Category' => $category,
            'Generic Description' => $generic,
            'Brand' => $brand,
            'Item Description' => $descriptionRaw,
            'New Brand' => $newBrandRaw,
            'Cost' => $amounts['unit_cost'],
            'Retail Markup %' => $retailMarkupRaw,
            'Retail Price' => $amounts['unit_price'],
            'Wholesale %' => $percents['wholesale_percent'],
            'P1 %' => $percents['price_1_percent'],
            'P2 %' => $percents['price_2_percent'],
            'P3 %' => $percents['price_3_percent'],
            'Tax' => $taxRaw,
        ];

        if ($code === null && ($category === '' || $generic === '')) {
            $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, 'Fill the product Code, or both Category and Generic Description.');

            return;
        }

        if ($noChangeRequested) {
            $this->ignoredBlank++;

            return;
        }

        $productId = $code !== null
            ? ($this->codeIds[$this->normalizeCode($code)] ?? null)
            : ($this->productIds[mb_strtolower($category . '|' . $generic . '|' . ($brand ?? ''))] ?? null);

        if ($productId === null) {
            $this->skip($rowData, ImportSkippedRow::REASON_PRODUCT_NOT_FOUND, $code !== null
                ? "No product with Code \"{$code}\" exists."
                : 'No product with this Category, Generic Description and Brand exists. Import it on the PRODUCTS sheet first.');

            return;
        }

        if (isset($this->seen[$productId])) {
            $this->skip($rowData, ImportSkippedRow::REASON_DUPLICATE_IN_FILE, "Same product as row {$this->seen[$productId]} of this file.");

            return;
        }

        $update = [];
        foreach ($amounts as $field => $raw) {
            if ($raw === null) {
                continue;
            }
            $number = $this->parseNumber($raw, 1_000_000_000);
            if ($number === null) {
                $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, "The value \"{$raw}\" is not a valid amount (use a number of 0 or more, in PHP).");

                return;
            }
            $update[$field] = $number;
        }

        $percentsGiven = [];
        foreach ($percents as $field => $raw) {
            if ($raw === null) {
                continue;
            }
            $number = $this->parseNumber(str_replace('%', '', $raw), 100);
            if ($number === null) {
                $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, "The value \"{$raw}\" is not a valid price level % (use a number from 0 to 100).");

                return;
            }
            $percentsGiven[$field] = $number;
        }

        $current = $this->current[$productId];
        $cost = (float) ($update['unit_cost'] ?? $current['unit_cost'] ?? 0);

        $markup = null;
        if ($retailMarkupRaw !== null) {
            $markup = $this->parseNumber(str_replace('%', '', $retailMarkupRaw), 100000);
            if ($markup === null) {
                $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, "The value \"{$retailMarkupRaw}\" is not a valid Retail Markup % (use a number of 0 or more).");

                return;
            }
        }

        if ($markup !== null && isset($update['unit_price'])) {
            // Both filled: keep both when they agree; when they don't (or there's
            // no Cost to check against) the typed Retail Price wins and the %
            // is left blank — Sir's rule.
            $computed = round($cost * (1 + $markup / 100), 2);
            if ($cost > 0 && abs($computed - $update['unit_price']) <= 0.01) {
                $update['unit_price_percent'] = $markup;
            } else {
                $update['unit_price_percent'] = null;
                $this->percentOverridden++;
            }
        } elseif ($markup !== null) {
            if ($cost <= 0) {
                $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, 'Retail Markup % needs a Cost. Add the Cost, or type the Retail Price instead and leave the % blank.');

                return;
            }
            $update['unit_price'] = round($cost * (1 + $markup / 100), 2);
            $update['unit_price_percent'] = $markup;
        } elseif (isset($update['unit_price'])) {
            // A typed price means the % is left blank — but only when the price
            // actually changes, so re-importing an untouched row is a true no-op.
            if ($current['unit_price'] === null || abs((float) $current['unit_price'] - $update['unit_price']) > 0.004) {
                $update['unit_price_percent'] = null;
            }
        } elseif (isset($update['unit_cost']) && $current['unit_price_percent'] !== null && $cost > 0) {
            $update['unit_price'] = round($cost * (1 + (float) $current['unit_price_percent'] / 100), 2);
        }

        if ($taxRaw !== null) {
            $resolved = $this->resolveTax($taxRaw);
            if ($resolved === false) {
                $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, "Tax \"{$taxRaw}\" is not recognised. Use VAT Inc, VAT Ex or Zero Vat.");

                return;
            }
            if ($resolved === 'missing') {
                $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, 'That tax is not set up in the system (Taxes page).');

                return;
            }
            $update['tax_id'] = $resolved;
        }

        $textChanges = [];
        if ($descriptionRaw !== null || $newBrandRaw !== null) {
            // Description/Brand text is changed by Code only: without it the
            // Category + Generic + Brand match IS the product's identity.
            if ($code === null) {
                $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, 'Item Description and New Brand can only be changed on a row that has the product Code.');

                return;
            }

            if ($descriptionRaw !== null && $descriptionRaw !== trim((string) ($current['description'] ?? ''))) {
                $textChanges['description'] = $descriptionRaw;
            }

            if ($newBrandRaw !== null && $newBrandRaw !== trim((string) ($current['brand_name'] ?? ''))) {
                if (mb_strlen($newBrandRaw) > 255) {
                    $this->skip($rowData, ImportSkippedRow::REASON_INVALID_DATA, 'New Brand is too long (255 characters at most).');

                    return;
                }

                $owner = $this->productIds[($this->identity[$productId] ?? '') . mb_strtolower($newBrandRaw)] ?? null;
                if ($owner !== null && $owner !== $productId) {
                    $this->skip($rowData, ImportSkippedRow::REASON_ALREADY_IN_SYSTEM, "Another product already has the Brand \"{$newBrandRaw}\" under the same Category and Generic Description.");

                    return;
                }

                $textChanges['brand_name'] = $newBrandRaw;
            }
        }

        // Only a row that passed validation claims the product, so a later row
        // isn't called a "duplicate" of one that was itself rejected.
        $this->seen[$productId] = $this->currentRow;

        $retail = (float) ($update['unit_price'] ?? $current['unit_price'] ?? 0);
        $retailChanged = array_key_exists('unit_price', $update);

        $derived = [];
        foreach (self::TIERS as $percentField => [$priceField]) {
            $percent = $percentsGiven[$percentField] ?? null;
            if ($percent !== null) {
                $update[$percentField] = $percent;
            }

            $effective = $percent ?? ($current[$percentField] !== null ? (float) $current[$percentField] : null);
            if ($effective !== null && $retail > 0 && ($percent !== null || $retailChanged)) {
                $derived[$priceField] = round($retail * (1 - $effective / 100), 2);
            }
        }

        $priceChanged = $this->differs($current, $update);
        if (! $priceChanged && $textChanges === []) {
            $this->unchanged++;

            return;
        }

        if ($priceChanged) {
            DB::table('products')->where('id', $productId)->update($update + $derived + ['updated_at' => now()]);
            $this->current[$productId] = array_merge($current, $update, $derived);
        }

        if ($textChanges !== []) {
            // Through the model, so item_name (built from the Generic Name and
            // Brand in Product::saving) stays in step with the new Brand.
            Product::findOrFail($productId)->fill($textChanges)->save();

            if (isset($textChanges['brand_name'])) {
                $identity = $this->identity[$productId] ?? '';
                unset($this->productIds[$identity . mb_strtolower(trim((string) ($current['brand_name'] ?? '')))]);
                $this->productIds[$identity . mb_strtolower($textChanges['brand_name'])] = $productId;
            }

            $this->current[$productId] = array_merge($this->current[$productId], $textChanges);
        }

        $this->updated++;
    }

    /** True when any requested value is different from what the product has now. */
    protected function differs(array $current, array $update): bool
    {
        foreach ($update as $field => $value) {
            $old = $current[$field] ?? null;

            if ($field === 'tax_id') {
                if ($old != $value) {
                    return true;
                }

                continue;
            }

            if ($value === null) {
                if ($old !== null) {
                    return true;
                }

                continue;
            }

            if ($old === null || abs((float) $old - (float) $value) > 0.004) {
                return true;
            }
        }

        return false;
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

    /** System codes are zero-padded strings ("00042"); Excel may hand back the number 42. */
    protected function normalizeCode(string $code): string
    {
        $code = mb_strtolower(trim($code));

        return ctype_digit($code) ? str_pad(ltrim($code, '0') === '' ? '0' : ltrim($code, '0'), 5, '0', STR_PAD_LEFT) : $code;
    }

    protected function parseNumber(string $raw, float $max): ?float
    {
        $clean = str_replace(['₱', ',', ' '], '', $raw);

        return is_numeric($clean) && (float) $clean >= 0 && (float) $clean <= $max ? round((float) $clean, 2) : null;
    }

    /** @return int|null|false|'missing' tax id, null for VAT-exempt, false if unrecognised */
    protected function resolveTax(string $raw): int|string|false|null
    {
        $key = preg_replace('/[^a-z]/', '', mb_strtolower($raw));

        if (in_array($key, ['vat', 'vatinc', 'vatinclusive', 'vatable', 'inc'], true)) {
            return $this->vatTax?->id ?? 'missing';
        }

        if (in_array($key, ['vatex', 'vatexempt', 'vatexcempt', 'exempt', 'ex', 'nonvat', 'novat'], true)) {
            return null;
        }

        if (in_array($key, ['zerovat', 'zero', 'zerorated', 'vatzero'], true)) {
            return $this->zeroTax?->id ?? 'missing';
        }

        return false;
    }
}
