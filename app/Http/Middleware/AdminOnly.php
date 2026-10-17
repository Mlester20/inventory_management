<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 'user' is a restricted third tier (2026-10) with view access to a
        // specific allow-list of these routes — individually blocked from
        // the rest by each controller, same pattern as admin_staff's own
        // per-action restrictions. This middleware only gates "is this
        // anyone from the admin backend at all," not which parts.
        if (auth()->check() && in_array(auth()->user()->role, ['admin', 'admin_staff', 'user'], true)) {
            return $next($request);
        }

        abort(403, 'Unauthorized access. Admin role required.');
    }
}
