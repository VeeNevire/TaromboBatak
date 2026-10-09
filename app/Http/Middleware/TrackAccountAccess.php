<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackAccountAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->record($user);
        }

        $response = $next($request);

        // A successful login authenticates the account inside the request.
        if ($user === null && $request->user() instanceof User) {
            $this->record($request->user());
        }

        return $response;
    }

    private function record(User $user): void
    {
        $accessedAt = now();

        try {
            // Access tracking must not alter the account's last profile edit timestamp.
            $user->newModelQuery()->whereKey($user->id)->toBase()->update(['last_active_at' => $accessedAt]);
        } catch (\Throwable $e) {
            // Tracking is best-effort; never fail the page because of it.
            report($e);

            return;
        }

        $user->setAttribute('last_active_at', $accessedAt);
    }
}
