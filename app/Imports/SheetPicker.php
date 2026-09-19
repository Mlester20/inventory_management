<?php

namespace App\Imports;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SheetPicker
{
    /**
     * The tab to import: the one named $wanted (case-insensitive) when the
     * workbook has it — e.g. the full DATA.xlsx with CUSTOMERS/SUPPLIERS/PRODUCTS
     * tabs — otherwise the first tab, so a single-sheet file with any tab name
     * (Excel's default "Sheet1", CSV's "Worksheet") still imports.
     */
    public static function key(UploadedFile $file, string $wanted): string|int
    {
        try {
            $path = $file->getRealPath();
            $names = IOFactory::createReaderForFile($path)->listWorksheetNames($path);
        } catch (\Throwable) {
            return 0;
        }

        foreach ($names as $name) {
            if (strcasecmp(trim($name), $wanted) === 0) {
                return $name;
            }
        }

        return 0;
    }
}
