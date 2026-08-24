<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanReviewContributions
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canReviewContributions(), 403, 'Akses hanya untuk kontributor.');

        return $next($request);
    }
}
