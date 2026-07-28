<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use RealRashid\SweetAlert\Facades\Alert;

class NewPasswordController extends Controller
{
    /**
     * How long an emailed verification code stays valid. Shorter than a
     * clickable reset link's default 60 minutes since a 6-digit code is far
     * more brute-forceable — kept short on purpose.
     */
    public const CODE_EXPIRY_MINUTES = 10;

    /**
     * Max failed code-verification attempts before locking out that
     * email+IP combination for the rest of the code's validity window.
     */
    public const MAX_VERIFY_ATTEMPTS = 5;

    /**
     * Display the password reset view. Shows the code-entry step by
     * default; shows the new-password step only right after a successful
     * verifyCode() redirect flashed the verified email+code into session
     * (flash data survives exactly one redirect, so a page refresh drops
     * back to the code step — the code itself is re-checked again on the
     * final submit regardless, so nothing security-sensitive relies on
     * this flash surviving).
     */
    public function create(Request $request): View
    {
        $verifiedEmail = session('verified_email');
        $verifiedCode = session('verified_code');

        return view('auth.reset-password', [
            'email' => $request->query('email', $verifiedEmail),
            'code' => $verifiedCode,
            'step' => ($verifiedEmail && $verifiedCode) ? 'password' : 'code',
        ]);
    }

    /**
     * Verify the emailed code without resetting the password yet — moves
     * the UI from the code-entry step to the new-password step.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function verifyCode(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $email = strtolower($request->email);
        $result = $this->checkCode($request, $email);

        if ($result !== 'ok') {
            return back()->withInput($request->only('email'))
                ->withErrors(['code' => $this->codeErrorMessage($result)]);
        }

        return redirect()->route('password.reset')->with([
            'verified_email' => $email,
            'verified_code' => $request->code,
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = strtolower($request->email);

        // Re-checked here even though verifyCode() already passed — the UI
        // step is just a convenience, never a security boundary. Anyone
        // could skip straight to this endpoint with a guessed code.
        $result = $this->checkCode($request, $email);

        if ($result !== 'ok') {
            return back()->withInput($request->only('email'))
                ->withErrors(['code' => $this->codeErrorMessage($result)]);
        }

        $user = User::where('email', $email)->first();
        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        RateLimiter::clear($this->throttleKey($email, $request));

        Alert::success('Password Reset', 'Your password has been reset successfully. Please sign in.');
        return redirect()->route('auth');
    }

    /**
     * Check the submitted code against the stored hash, honoring the
     * per-email+IP rate limit and the code's expiry window. Registers a
     * failed attempt against the throttle when invalid.
     *
     * @return string 'ok', 'throttled', or 'invalid'
     */
    private function checkCode(Request $request, string $email): string
    {
        $throttleKey = $this->throttleKey($email, $request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_VERIFY_ATTEMPTS)) {
            return 'throttled';
        }

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        $user = User::where('email', $email)->first();

        // Carbon 3's diffInMinutes() returns a signed value (negative when
        // the argument is in the past), so this must be compared as an
        // absolute value — otherwise it's always negative (always <=
        // CODE_EXPIRY_MINUTES) and codes would never actually expire.
        $codeValid = $record
            && $user
            && Hash::check($request->code, $record->token)
            && now()->diffInMinutes($record->created_at, true) <= self::CODE_EXPIRY_MINUTES;

        if ($codeValid) {
            return 'ok';
        }

        // Deliberately indistinguishable whether the email has no account,
        // the code is wrong, or it expired.
        RateLimiter::hit($throttleKey, self::CODE_EXPIRY_MINUTES * 60);

        return 'invalid';
    }

    private function codeErrorMessage(string $result): string
    {
        return $result === 'throttled'
            ? 'Too many attempts. Please request a new code.'
            : 'That code is invalid or has expired.';
    }

    private function throttleKey(string $email, Request $request): string
    {
        return 'password-reset-code:' . $email . '|' . $request->ip();
    }
}
