<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the resource, filterable by user, module, source,
     * and a created_at date range. Restricted to full admin — previously
     * admin_staff saw an own-actions-only view, but Sir's updated access
     * spec (2026-10) wants the Audit Trail hidden from admin_staff entirely.
     */
    public function index(Request $request)
    {
        if (auth()->user()->role === 'admin_staff') {
            Alert::error('Not allowed', 'The Audit Trail is restricted to full admin accounts.');
            return redirect()->route('admin.dashboard');
        }

        $activityLogs = ActivityLog::with('user')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->module))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $modules = ActivityLog::whereNotNull('module')->distinct()->orderBy('module')->pluck('module');
        $sources = [ActivityLog::SOURCE_ADMIN, ActivityLog::SOURCE_POS, ActivityLog::SOURCE_SYSTEM];
        $roles = ActivityLog::whereNotNull('role')->distinct()->orderBy('role')->pluck('role');

        return view('admin.activitiesLog', compact('activityLogs', 'users', 'modules', 'sources', 'roles'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ActivityLog $activityLog)
    {
        //
    }
}
