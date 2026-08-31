<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CogsService;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class CogsController extends Controller
{
    public function __construct(protected CogsService $cogsService) {}

    /**
     * Display the COGS admin dashboard. Restricted to full admin — unlike
     * the Dashboard (a mix of operational + financial figures, so only the
     * peso figures get masked), this entire page is financial data with
     * nothing operational left to show once masked, so it's blocked
     * outright rather than rendered mostly-blank.
     * Accepts optional query params: start_date, end_date, year
     */
    public function index(Request $request)
    {
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'The COGS report is restricted to full admin accounts.');
            return redirect()->route('admin.dashboard');
        }

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
