<?php

namespace App\Http\Controllers;

use App\Exports\InventoryTemplateExport;
use App\Exports\StockCountTemplateExport;
use App\Exports\StockForCountExport;
use App\Imports\InventoryOpeningImport;
use App\Imports\NamedSheetImport;
use App\Imports\SheetPicker;
use App\Imports\StockCountImport;
use App\Models\ActivityLog;
use App\Services\InventoryAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

    public function importCount(Request $request, InventoryAdjustmentService $adjustments)
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Importing a stock count is restricted to full admin accounts.');

            return redirect()->route('inventory-items.index', ['tab' => 'batches']);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        set_time_limit(0);

        $file = $request->file('file');
        $import = new StockCountImport($adjustments, auth()->id(), $file->getClientOriginalName());

        try {
            Excel::import(new NamedSheetImport($import, SheetPicker::key($file, 'COUNT')), $file);
        } catch (ValidationException $e) {
            Alert::error('Import stopped', 'The stock changed while the file was being processed (' . collect($e->errors())->flatten()->first() . '). Nothing was posted — please try again.');

            return redirect()->route('inventory-items.index', ['tab' => 'batches']);
        }

        if ($import->headingsMissing) {
            Alert::error(
                'Import failed',
                'This sheet needs a Code column (or Category and Generic Description) plus Lot No and Counted Qty. Please use the downloaded template or the "Download stock for counting" file (the tab is named "COUNT").'
            );

            return redirect()->route('inventory-items.index', ['tab' => 'batches']);
        }

        $numbers = collect([$import->increaseAdjustment, $import->decreaseAdjustment])->filter()->pluck('adjustment_no')->implode(' and ');

        ActivityLog::record(
            module: 'InventoryAdjustment',
            action: 'imported',
            loggable: $import->increaseAdjustment ?? $import->decreaseAdjustment,
            description: "Imported stock count from Excel: {$import->increased} lot(s) increased (+{$import->unitsAdded}), {$import->decreased} lot(s) decreased (-{$import->unitsRemoved}), {$import->unchanged} already correct"
                . ($numbers !== '' ? " as {$numbers}" : '')
                . "; {$import->skipped} row(s) skipped, {$import->ignoredBlank} row(s) with no count ignored",
        );

        $summary = "{$import->increased} lot(s) increased (+{$import->unitsAdded} units), {$import->decreased} lot(s) decreased (-{$import->unitsRemoved} units), {$import->unchanged} already correct"
            . ($numbers !== '' ? " — posted as {$numbers}" : '')
            . ($import->ignoredBlank > 0 ? ". {$import->ignoredBlank} row(s) with no Counted Qty were ignored" : '') . '.';

        if ($import->skipped === 0) {
            Alert::success('Stock count applied', $summary);

            return redirect()->route('inventory-items.index', ['tab' => 'batches']);
        }

        Alert::error('Applied with some rows skipped', "{$summary} {$import->skipped} row(s) were skipped — the full list is below, so you can correct them in your Excel file.");

        return redirect()->route('import-results.index', ['batch' => $import->batchId]);
    }

    public function exportForCount()
    {
        if (auth()->user()->role !== 'admin') {
            Alert::error('Not allowed', 'Exporting stock for a count is restricted to full admin accounts.');

            return redirect()->route('inventory-items.index', ['tab' => 'batches']);
        }

        return Excel::download(new StockForCountExport(), 'stock-for-counting-' . now()->format('Ymd-His') . '.xlsx');
    }

    public function countTemplate()
    {
        return Excel::download(new StockCountTemplateExport(), 'stock-count-import-template.xlsx');
    }
}
