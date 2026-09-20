<?php

namespace App\Http\Controllers;

use App\Exports\ProductPricesTemplateExport;
use App\Exports\ProductsForPricesExport;
use App\Imports\NamedSheetImport;
use App\Imports\ProductPricesImport;
use App\Imports\SheetPicker;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;

class ProductPricesImportController extends Controller
{
    public function import(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Updating prices by import is restricted to full admin accounts.');

            return redirect()->route('inventory-items.index', ['tab' => 'products']);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        set_time_limit(0);

        $file = $request->file('file');
        $import = new ProductPricesImport();
        Excel::import(new NamedSheetImport($import, SheetPicker::key($file, 'PRICES')), $file);

        if ($import->headingsMissing) {
            Alert::error(
                'Import failed',
                'This sheet needs a Code column, or the Category and Generic Description columns (plus Cost, Retail, Wholesale %, P1 %, P2 %, P3 % and Tax). Please use the downloaded template or the exported products file (the tab is named "PRICES").'
            );

            return redirect()->route('inventory-items.index', ['tab' => 'products']);
        }

        ActivityLog::record(
            module: 'Product',
            action: 'prices_imported',
            description: "Updated prices/tax of {$import->updated} product(s) from Excel; "
                . "{$import->unchanged} already up to date, {$import->skipped} row(s) skipped, {$import->ignoredBlank} row(s) with nothing to change ignored"
                . ($import->percentOverridden > 0 ? "; {$import->percentOverridden} row(s) where the Retail Markup % did not match, so the Retail Price was kept and the % left blank" : ''),
        );

        $summary = "{$import->updated} product(s) updated"
            . ($import->unchanged > 0 ? ", {$import->unchanged} already up to date" : '')
            . ($import->ignoredBlank > 0 ? ", {$import->ignoredBlank} row(s) with nothing to change ignored" : '')
            . ($import->percentOverridden > 0 ? ". {$import->percentOverridden} row(s) had a Retail Markup % that did not match the Retail Price, so the price was kept and the % left blank" : '') . '.';

        if ($import->skipped === 0) {
            Alert::success('Success', $summary);

            return redirect()->route('inventory-items.index', ['tab' => 'products']);
        }

        Alert::error('Updated with some rows skipped', "{$summary} {$import->skipped} row(s) were skipped — the full list is below, so you can correct them in your Excel file.");

        return redirect()->route('import-results.index', ['batch' => $import->batchId]);
    }

    public function export()
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Exporting products for price updates is restricted to full admin accounts.');

            return redirect()->route('inventory-items.index', ['tab' => 'products']);
        }

        return Excel::download(new ProductsForPricesExport(), 'products-for-price-update-' . now()->format('Ymd-His') . '.xlsx');
    }

    public function downloadTemplate()
    {
        return Excel::download(new ProductPricesTemplateExport(), 'update-prices-import-template.xlsx');
    }
}
