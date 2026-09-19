<?php

namespace App\Http\Controllers;

use App\Exports\ImportSkippedRowsExport;
use App\Models\ImportSkippedRow;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;

class ImportResultsController extends Controller
{
    public function index(Request $request)
    {
        if ($this->denied()) {
            Alert::error('Not allowed', 'Import results are restricted to full admin accounts.');

            return redirect()->route('admin.dashboard');
        }

        $rows = $this->filtered($request)
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $batches = ImportSkippedRow::query()
            ->selectRaw('batch_id, import_type, imported_by, COUNT(*) as skipped_count, MIN(created_at) as imported_at')
            ->groupBy('batch_id', 'import_type', 'imported_by')
            ->orderByDesc('imported_at')
            ->limit(15)
            ->get();

        $users = User::whereIn('id', $batches->pluck('imported_by')->filter())->pluck('name', 'id');

        $reasonCounts = $this->filtered($request, withReason: false)
            ->selectRaw('reason, COUNT(*) as total')
            ->groupBy('reason')
            ->pluck('total', 'reason');

        return view('admin.import-results', [
            'rows' => $rows,
            'batches' => $batches,
            'users' => $users,
            'reasonCounts' => $reasonCounts,
            'reasons' => ImportSkippedRow::REASONS,
        ]);
    }

    public function export(Request $request)
    {
        if ($this->denied()) {
            Alert::error('Not allowed', 'Import results are restricted to full admin accounts.');

            return redirect()->route('admin.dashboard');
        }

        $request->validate(['batch' => 'required|uuid']);

        $rows = $this->filtered($request)->orderBy('id')->get();

        if ($rows->isEmpty()) {
            Alert::info('Nothing to export', 'There are no skipped rows for that import.');

            return redirect()->route('import-results.index');
        }

        return Excel::download(
            new ImportSkippedRowsExport($rows),
            'skipped-rows-' . $rows->first()->import_type . '-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    private function denied(): bool
    {
        return auth()->user()->role !== 'admin';
    }

    private function filtered(Request $request, bool $withReason = true)
    {
        return ImportSkippedRow::query()
            ->when($request->filled('batch'), fn ($q) => $q->where('batch_id', $request->batch))
            ->when($request->filled('type'), fn ($q) => $q->where('import_type', $request->type))
            ->when($withReason && $request->filled('reason'), fn ($q) => $q->where('reason', $request->reason));
    }
}
