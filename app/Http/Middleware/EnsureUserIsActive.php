<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Log a suspended user out immediately, even mid-session — suspension is
     * meant to take effect right away, not just block the next login.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! Auth::user()->is_active) {
            $user = Auth::user();

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // SOURCE_SYSTEM: the app's own middleware forcing this, not an
            // action the account owner performed from the admin UI or POS.
            ActivityLog::record(
                module: 'Auth',
                action: 'forced_logout',
                loggable: $user,
                description: "Forced logout of suspended account {$user->email} mid-session",
                source: ActivityLog::SOURCE_SYSTEM,
                userId: $user->id,
            );

            Alert::error('Account Suspended', 'Your account has been suspended. Please contact an administrator.');
            return redirect()->route('login');
        }

        return $next($request);
    }
}
