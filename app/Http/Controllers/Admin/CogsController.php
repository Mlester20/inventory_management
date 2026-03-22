<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CogsService;
use Illuminate\Http\Request;

class CogsController extends Controller
{
    public function __construct(protected CogsService $cogsService) {}

    /**
     * Display the COGS admin dashboard.
     * Accepts optional query params: start_date, end_date, year
     */
    public function index(Request $request)
    {
        // Validate request inputs
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'year' => 'nullable|integer|min:2000|max:' . now()->year,
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $year = $validated['year'] ?? now()->year;

        // Get COGS data
        $summary = $this->cogsService->calculate($startDate, $endDate);
        $perItem = $this->cogsService->perItem($startDate, $endDate);
        $monthlyTrend = $this->cogsService->monthlyTrend($year);

        return view('admin.cogs.index', [
            'summary' => $summary,
            'perItem' => $perItem,
            'monthlyTrend' => $monthlyTrend,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'year' => $year,
        ]);
    }
}
