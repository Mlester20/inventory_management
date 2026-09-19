<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Points a single-sheet import at one specific tab of a workbook (see
 * SheetPicker). Property and method access proxies to the wrapped import, so
 * controllers keep using $import->importedCount / ->failures() unchanged.
 */
class NamedSheetImport implements WithMultipleSheets
{
    public function __construct(protected object $inner, protected string|int $sheet)
    {
    }

    public function sheets(): array
    {
        return [$this->sheet => $this->inner];
    }

    public function __get(string $name)
    {
        return $this->inner->{$name};
    }

    public function __call(string $method, array $arguments)
    {
        return $this->inner->{$method}(...$arguments);
    }
}
