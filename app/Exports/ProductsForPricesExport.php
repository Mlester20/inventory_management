<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Every current product in the Update Prices layout, with its system Code, so
 * the file can be edited in Excel and imported straight back: the Code makes
 * the match exact, so nothing can turn into a "new" product.
 */
class ProductsForPricesExport implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'PRICES';
    }

    public function headings(): array
    {
        return (new ProductPricesTemplateExport())->headings();
    }

    public function array(): array
    {
        return Product::query()
            ->join('generic_names as g', 'g.id', '=', 'products.generic_name_id')
            ->join('categories as c', 'c.id', '=', 'g.category_id')
            ->leftJoin('taxes as t', 't.id', '=', 'products.tax_id')
            ->orderBy('c.category_name')
            ->orderBy('g.generic_name')
            ->orderBy('products.brand_name')
            ->get([
                'products.code', 'c.category_name', 'g.generic_name', 'products.brand_name', 'g.unit', 'products.description',
                'products.unit_cost', 'products.unit_price', 'products.unit_price_percent',
                'products.wholesale_percent', 'products.price_1_percent', 'products.price_2_percent', 'products.price_3_percent',
                't.name as tax_name',
            ])
            ->map(fn ($p) => [
                $p->code, $p->category_name, $p->generic_name, $p->brand_name, $p->unit,
                $p->description,
                null,
                $this->number($p->unit_cost),
                $this->markupIfConsistent($p),
                $this->number($p->unit_price),
                ...array_map(
                    fn ($value) => $this->number($value),
                    [$p->wholesale_percent, $p->price_1_percent, $p->price_2_percent, $p->price_3_percent],
                ),
                match (true) {
                    $p->tax_name === null => 'VAT Ex',
                    str_starts_with(mb_strtolower($p->tax_name), 'zero') => 'Zero Vat',
                    default => 'VAT Inc',
                },
            ])
            ->all();
    }

    private function number(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /**
     * The stored Retail % is only exported when it really produces the stored
     * Retail Price from the Cost; otherwise the price was typed directly, and a
     * stale % in the file would make the re-import reject an untouched row.
     */
    private function markupIfConsistent($product): ?float
    {
        if ($product->unit_price_percent === null || (float) $product->unit_cost <= 0) {
            return null;
        }

        $computed = round((float) $product->unit_cost * (1 + (float) $product->unit_price_percent / 100), 2);

        return abs($computed - (float) $product->unit_price) <= 0.01 ? (float) $product->unit_price_percent : null;
    }
}
