<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ImportSkippedRow extends Model
{
    public const REASON_DUPLICATE_IN_FILE = 'duplicate_in_file';
    public const REASON_DIFFERENT_CATEGORY = 'different_category';
    public const REASON_ALREADY_IN_SYSTEM = 'already_in_system';
    public const REASON_INVALID_DATA = 'invalid_data';

    public const REASONS = [
        self::REASON_DUPLICATE_IN_FILE => 'Duplicate row in the file',
        self::REASON_ALREADY_IN_SYSTEM => 'Already exists (in the system or earlier in the file)',
        self::REASON_DIFFERENT_CATEGORY => 'Generic description exists under another category',
        self::REASON_INVALID_DATA => 'Invalid or duplicate data',
    ];

    protected $fillable = [
        'batch_id', 'import_type', 'sheet_row', 'reason', 'details', 'row_data', 'imported_by',
    ];

    protected $casts = [
        'row_data' => 'array',
    ];

    /**
     * Persist skipped rows for one import batch. Never throws: losing the
     * skipped-rows log must not fail an import that otherwise succeeded.
     *
     * @param  array<int, array{sheet_row:?int, reason:string, details:?string, row_data:array}>  $rows
     */
    public static function record(string $batchId, string $importType, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        try {
            $now = now();
            $userId = auth()->id();

            foreach (array_chunk($rows, 200) as $chunk) {
                static::insert(array_map(fn (array $row) => [
                    'batch_id' => $batchId,
                    'import_type' => $importType,
                    'sheet_row' => $row['sheet_row'] ?? null,
                    'reason' => $row['reason'],
                    'details' => $row['details'] ?? null,
                    'row_data' => json_encode($row['row_data'] ?? [], JSON_UNESCAPED_UNICODE),
                    'imported_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Persist Maatwebsite validation failures (customers/suppliers imports).
     *
     * @param  Collection<int, \Maatwebsite\Excel\Validators\Failure>  $failures
     */
    public static function recordFailures(string $batchId, string $importType, Collection $failures): void
    {
        $rows = $failures->groupBy(fn ($failure) => $failure->row())->map(function ($group, $row) {
            $values = collect($group->first()->values())
                ->mapWithKeys(fn ($value, $key) => [ucwords(str_replace('_', ' ', (string) $key)) => $value])
                ->all();

            $errors = $group->flatMap(fn ($failure) => $failure->errors())->unique();

            // The unique: rules' messages all read "... already exists." — a
            // duplicate (of an existing record or an earlier row in the file,
            // which is already saved by then), not malformed data.
            $isDuplicate = $errors->contains(fn ($error) => str_contains($error, 'already exists'));

            return [
                'sheet_row' => (int) $row,
                'reason' => $isDuplicate ? self::REASON_ALREADY_IN_SYSTEM : self::REASON_INVALID_DATA,
                'details' => $errors->implode(' '),
                'row_data' => $values,
            ];
        })->values()->all();

        static::record($batchId, $importType, $rows);
    }
}
