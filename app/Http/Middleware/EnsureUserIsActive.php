<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a user whose account was deactivated while they were logged in.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account has been deactivated.'], 401);
            }
            return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated.']);
        }

        if ($user && $user->last_login_at === null) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
