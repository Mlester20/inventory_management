<?php

namespace App\Exports;

use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Every lot that has a Warehouse stock row, in the stock count layout, with the
 * system quantity for reference. Fill in Counted Qty and upload it back; the
 * Current Qty / Expiry columns are ignored by the import.
 */
class StockForCountExport implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'COUNT';
    }

    public function headings(): array
    {
        return ['Code', 'Category', 'Generic Description', 'Brand', 'Unit', 'Lot No', 'Counted Qty', 'Current Qty (system)', 'Expiry Date'];
    }

    public function array(): array
    {
        return DB::table('location_stocks as ls')
            ->join('product_batches as b', 'b.id', '=', 'ls.product_batch_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('generic_names as g', 'g.id', '=', 'p.generic_name_id')
            ->join('categories as c', 'c.id', '=', 'g.category_id')
            ->where('ls.location_id', Location::warehouse()->id)
            ->whereNull('p.deleted_at')
            ->orderBy('c.category_name')
            ->orderBy('g.generic_name')
            ->orderBy('p.brand_name')
            ->orderBy('b.expiration_date')
            ->get(['p.code', 'c.category_name', 'g.generic_name', 'p.brand_name', 'g.unit', 'b.batch_no', 'ls.qty', 'b.expiration_date'])
            ->map(fn ($row) => [
                $row->code, $row->category_name, $row->generic_name, $row->brand_name, $row->unit, $row->batch_no,
                null, (int) $row->qty, $row->expiration_date,
            ])
            ->all();
    }
}
