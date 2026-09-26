<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UndeliveredItemsExport;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\UndeliveredItemsReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class UndeliveredItemsReportController extends Controller
{
    public function __construct(protected UndeliveredItemsReportService $reportService)
    {
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $customers = $this->reportService->build($filters['customer_id'], $filters['start_date'], $filters['end_date']);

        return view('admin.reports.undelivered-items', [
            'customers' => $customers,
            'customerOptions' => Customer::orderBy('customer_name')->get(['id', 'customer_name']),
            'filters' => $filters,
            'period' => $this->periodLabel($filters),
            'totals' => [
                'customers' => $customers->count(),
                'orders' => $customers->sum('so_count'),
                'balance' => $customers->sum('total_balance'),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        $customers = $this->reportService->build($filters['customer_id'], $filters['start_date'], $filters['end_date']);

        return Excel::download(new UndeliveredItemsExport($customers), 'undelivered-items-per-customer-' . now()->format('Ymd-His') . '.xlsx');
    }

    /** What the printed sheet says it covers: every open Sales Order, or the SO date range that was filtered. */
    protected function periodLabel(array $filters): string
    {
        $from = $filters['start_date'] ? Carbon::parse($filters['start_date'])->format('m/d/Y') : null;
        $to = $filters['end_date'] ? Carbon::parse($filters['end_date'])->format('m/d/Y') : null;

        return match (true) {
            $from && $to => "SOs dated {$from} - {$to}",
            (bool) $from => "SOs dated from {$from}",
            (bool) $to => "SOs dated up to {$to}",
            default => 'All open Sales Orders',
        };
    }

    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        return [
            'customer_id' => isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ];
    }
}
