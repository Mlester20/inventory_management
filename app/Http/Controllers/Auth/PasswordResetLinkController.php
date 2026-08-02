<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use RealRashid\SweetAlert\Facades\Alert;

class PasswordResetLinkController extends Controller
{
    /**
     * Minimum seconds between code requests for the same email, so this
     * endpoint can't be used to spam someone's inbox.
     */
    public const RESEND_THROTTLE_SECONDS = 15;

    /**
     * Display the password reset code request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset code request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($request->email);

        try {
            $this->sendCodeIfEligible($email);
        } catch (\Throwable $e) {
            // The mail transport (e.g. Resend sandbox mode, which only
            // delivers to the account's own signup address) can reject a
            // send outright. Never let that surface as a raw error here —
            // it would otherwise leak which emails have an account.
            report($e);
        }

        // Always show the same message and redirect, regardless of whether
        // the email has an account, so this endpoint can't be used to
        // enumerate which emails are registered.
        Alert::success('Check your email', 'If an account with that email exists, a verification code has been sent.');

        return redirect()->route('password.reset', ['email' => $request->email]);
    }

    /**
     * Generate and email a 6-digit verification code, unless one was
     * already sent to this address within the throttle window or the email
     * has no matching account.
     */
    private function sendCodeIfEligible(string $email): void
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            return;
        }

        $existing = DB::table('password_reset_tokens')->where('email', $email)->first();
        // Carbon 3's diffInSeconds() returns a signed value (negative when
        // the argument is in the past), so this must be compared as an
        // absolute value — otherwise every existing row looks "too recent"
        // forever and no resend is ever sent again after the first one.
        if ($existing && now()->diffInSeconds($existing->created_at, true) < self::RESEND_THROTTLE_SECONDS) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );

        Mail::to($user->email)->send(new ResetPasswordMail($code, $user));

        // No authenticated actor here (public, unauthenticated flow) — left
        // unattributed (user_id null); loggable identifies which account.
        ActivityLog::record(
            module: 'Auth',
            action: 'password_reset_requested',
            loggable: $user,
            description: "Password reset code requested for {$user->email}",
        );
    }
}
