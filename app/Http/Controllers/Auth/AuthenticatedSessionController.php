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
            Alert::error('Login Failed', 'Invalid email or password');
            return back()->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        // Log the login activity for the authenticated user (works for admin and regular users)
        ActivityLog::logLogin(Auth::id(), $request->ip());

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
        // Log the logout activity before destroying the session (works for admin and regular users)
        $userId = Auth::id();
        $ipAddress = $request->ip();

        Auth::guard('web')->logout();

        if ($userId) {
            ActivityLog::logLogout($userId, $ipAddress);
        }

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
