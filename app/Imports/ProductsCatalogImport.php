<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Sheet-selecting wrapper around ProductsCatalogSheetImport. The real
 * source file this was built against (docs/DATA.xlsx) has 19 sheets —
 * Customers, Suppliers, per-category working sheets full of duplicates,
 * pivot tables — and only the sheet literally named "PRODUCTS" is the
 * actual consolidated catalog to import (see ProductsCatalogSheetImport's
 * own docblock for why the others aren't a second source to merge in).
 *
 * Implementing WithMultipleSheets here means an admin can upload that
 * whole workbook directly — every sheet except the one named "PRODUCTS"
 * is silently ignored, rather than Maatwebsite defaulting to whichever
 * sheet happens to be first in the file (which would otherwise be
 * "CUSTOMERS", read using Product-shaped column expectations that don't
 * match at all).
 *
 * Property access (e.g. $import->productsImported) transparently proxies
 * to the inner ProductsCatalogSheetImport instance that sheets() hands to
 * Maatwebsite — the controller doesn't need to know this class is a thin
 * wrapper rather than the thing that actually processed the rows.
 */
class ProductsCatalogImport implements WithMultipleSheets
{
    protected ProductsCatalogSheetImport $rows;

    public function __construct()
    {
        $this->rows = new ProductsCatalogSheetImport();
    }

    public function sheets(): array
    {
        return [
            'PRODUCTS' => $this->rows,
        ];
    }

    public function __get(string $name)
    {
        return $this->rows->{$name};
    }

    /**
     * @return string[]
     */
    public function crossCategorySkips(): array
    {
        return $this->rows->crossCategorySkips();
    }
}
