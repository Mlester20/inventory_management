<?php

namespace App\Exports;

use App\Models\ImportSkippedRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Skipped rows in the same column layout as the matching import template
 * (data columns first), followed by Excel Row / Reason / Details, so the file
 * can be corrected and re-uploaded directly — the import ignores the extra
 * trailing columns.
 */
class ImportSkippedRowsExport implements FromArray, WithHeadings, WithTitle
{
    /** @var string[] */
    protected array $dataColumns;

    /** @param Collection<int, ImportSkippedRow> $rows */
    public function __construct(protected Collection $rows)
    {
        $this->dataColumns = $rows
            ->flatMap(fn (ImportSkippedRow $row) => array_keys($row->row_data ?? []))
            ->unique()
            ->values()
            ->all();
    }

    public function title(): string
    {
        return strtoupper($this->rows->first()->import_type);
    }

    public function headings(): array
    {
        return [...$this->dataColumns, 'Excel Row', 'Reason', 'Details'];
    }

    public function array(): array
    {
        return $this->rows->map(function (ImportSkippedRow $row) {
            $data = $row->row_data ?? [];

            return [
                ...array_map(fn ($column) => $data[$column] ?? null, $this->dataColumns),
                $row->sheet_row,
                ImportSkippedRow::REASONS[$row->reason] ?? $row->reason,
                $row->details,
            ];
        })->all();
    }
}
