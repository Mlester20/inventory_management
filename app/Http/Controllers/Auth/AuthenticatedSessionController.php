<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RealRashid\SweetAlert\Facades\Alert;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            // No verified identity here (could be a typo or an attacker
            // probing an unknown address) — left unattributed (user_id null)
            // rather than guessed from the submitted email.
            ActivityLog::record(
                module: 'Auth',
                action: 'login_failed',
                description: "Failed login attempt for {$request->email}",
                metadata: ['email' => $request->email, 'reason' => 'invalid_credentials'],
            );

            Alert::error('Login Failed', 'Invalid email or password');
            return back()->withInput($request->only('email'));
        }

        if (! Auth::user()->is_active) {
            $suspendedUser = Auth::user();
            Auth::guard('web')->logout();

            // Credentials were verified before the suspension check, so this
            // one *is* attributed to the account, unlike the branch above.
            ActivityLog::record(
                module: 'Auth',
                action: 'login_failed',
                loggable: $suspendedUser,
                description: "Blocked login for suspended account {$suspendedUser->email}",
                metadata: ['email' => $suspendedUser->email, 'reason' => 'account_suspended'],
                userId: $suspendedUser->id,
            );

            Alert::error('Account Suspended', 'Your account has been suspended. Please contact an administrator.');
            return back()->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        ActivityLog::record(
            module: 'Auth',
            action: 'login',
            loggable: Auth::user(),
            description: 'User logged in',
        );

        return redirect()->intended($this->redirectBasedOnRole());
    }

    /**
     * Redirect based on user role.
     */
    private function redirectBasedOnRole(): string
    {
        $user = Auth::user();
        
        if ($user->role === 'admin') {
            return route('admin.dashboard');
        }
        
        return route('pages.home');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        Auth::guard('web')->logout();

        if ($user) {
            ActivityLog::record(
                module: 'Auth',
                action: 'logout',
                loggable: $user,
                description: 'User logged out',
                userId: $user->id,
            );
        }

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
