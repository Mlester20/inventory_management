<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the resource, filterable by user, module, source,
     * and a created_at date range.
     */
    public function index(Request $request)
    {
        // admin_staff only ever sees their own actions — the user/role
        // filters below are for the full admin's cross-account view, so
        // they're ignored entirely for admin_staff rather than merely
        // hidden client-side (a manually-edited query string could
        // otherwise still pull another account's history).
        $ownOnly = auth()->user()->role === 'admin_staff';

        $activityLogs = ActivityLog::with('user')
            ->when($ownOnly, fn ($q) => $q->where('user_id', auth()->id()))
            ->when(! $ownOnly && $request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when(! $ownOnly && $request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->module))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $users = $ownOnly ? collect() : User::orderBy('name')->get(['id', 'name', 'email']);
        $modules = ActivityLog::whereNotNull('module')->distinct()->orderBy('module')->pluck('module');
        $sources = [ActivityLog::SOURCE_ADMIN, ActivityLog::SOURCE_POS, ActivityLog::SOURCE_SYSTEM];
        $roles = $ownOnly ? collect() : ActivityLog::whereNotNull('role')->distinct()->orderBy('role')->pluck('role');

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
