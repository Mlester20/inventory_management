<?php

namespace App\Http\Controllers;

use App\Exports\InventoryTemplateExport;
use App\Imports\InventoryOpeningImport;
use App\Imports\NamedSheetImport;
use App\Imports\SheetPicker;
use App\Models\ActivityLog;
use App\Services\InventoryAdjustmentService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;

class InventoryImportController extends Controller
{
    public function import(Request $request, InventoryAdjustmentService $adjustments)
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Importing opening inventory is restricted to full admin accounts.');

            return redirect()->route('inventory-items.index', ['tab' => 'batches']);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        // Thousands of lots are posted one by one through the stock ledger.
        set_time_limit(0);

        $file = $request->file('file');
        $import = new InventoryOpeningImport($adjustments, auth()->id(), $file->getClientOriginalName());
        Excel::import(new NamedSheetImport($import, SheetPicker::key($file, 'INVENTORY')), $file);

        if ($import->headingsMissing) {
            Alert::error(
                'Import failed',
                'This sheet needs the columns Category, Generic Description, Brand, Lot No, Expiry Date and Qty. Please use the downloaded template (the tab is named "INVENTORY").'
            );

            return redirect()->route('inventory-items.index', ['tab' => 'batches']);
        }

        ActivityLog::record(
            module: 'InventoryAdjustment',
            action: 'imported',
            loggable: $import->adjustment,
            description: "Imported opening inventory from Excel: {$import->linesImported} lot(s), {$import->unitsImported} unit(s)"
                . ($import->adjustment ? " as {$import->adjustment->adjustment_no}" : '')
                . "; {$import->skipped} row(s) skipped, {$import->ignoredNoQty} row(s) with no quantity ignored",
        );

        $summary = "{$import->linesImported} lot(s) ({$import->unitsImported} units) added to the Warehouse"
            . ($import->adjustment ? " as {$import->adjustment->adjustment_no}" : '')
            . ($import->ignoredNoQty > 0 ? ". {$import->ignoredNoQty} row(s) with no quantity were ignored" : '') . '.';

        if ($import->skipped === 0) {
            if ($import->linesImported === 0) {
                Alert::info('Nothing imported', $summary);

                return redirect()->route('inventory-items.index', ['tab' => 'batches']);
            }

            Alert::success('Success', $summary);

            return redirect()->route('inventory-adjustments.show', $import->adjustment);
        }

        Alert::error('Imported with some rows skipped', "{$summary} {$import->skipped} row(s) were skipped — the full list is below, so you can correct them in your Excel file.");

        return redirect()->route('import-results.index', ['batch' => $import->batchId]);
    }

    public function downloadTemplate()
    {
        return Excel::download(new InventoryTemplateExport(), 'opening-inventory-import-template.xlsx');
    }
}
