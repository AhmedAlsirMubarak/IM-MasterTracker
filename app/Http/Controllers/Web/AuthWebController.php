<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthWebController extends Controller
{
    /** Failed attempts allowed per email + IP before a lockout. */
    private const MAX_ATTEMPTS = 5;

    public function showLogin() { return view('auth.login'); }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $throttleKey = Str::lower($credentials['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()
                ->withErrors(['email' => "Too many sign-in attempts. Try again in {$seconds} seconds."])
                ->onlyInput('email');
        }

        // Only active accounts may sign in
        $credentials['is_active'] = true;

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);

        // Prevent session fixation
        $request->session()->regenerate();

        return redirect()->intended(route('contracts.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
