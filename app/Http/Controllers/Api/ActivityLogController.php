<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    /**
     * Get activity logs for the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Fetch activity logs for the current user ordered by latest first
        $activityLogs = ActivityLog::where('user_id', $user->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $activityLogs->items(),
            'pagination' => [
                'current_page' => $activityLogs->currentPage(),
                'last_page' => $activityLogs->lastPage(),
                'total' => $activityLogs->total(),
                'per_page' => $activityLogs->perPage(),
            ]
        ]);
    }
}
