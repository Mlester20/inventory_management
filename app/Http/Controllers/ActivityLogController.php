<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Fetch all activity logs ordered by latest first, with user relationship
        $activityLogs = ActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('admin.activitiesLog', compact('activityLogs'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ActivityLog $activityLog)
    {
        //
    }
}
