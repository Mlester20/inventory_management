<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UndeliveredItemsExport;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\UndeliveredItemsReportService;
use Illuminate\Http\Request;
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
