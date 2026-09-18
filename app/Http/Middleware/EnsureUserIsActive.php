<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /** End sessions belonging to accounts that an administrator has disabled. */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isActive() === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->withErrors([
                'email' => 'Akun ini sudah dinonaktifkan. Hubungi administrator untuk bantuan.',
            ]);
        }

        return $next($request);
    }
}
