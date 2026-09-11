<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AccountActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogSubAdminActivity
{
    public function __construct(private AccountActivityLogger $logger) {}

    /**
     * Record successful data-changing requests performed by sub-admins.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $actor = $request->user();
        $routeName = $request->route()?->getName();

        if (! $actor instanceof User
            || ! $actor->isSubAdmin()
            || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || $response->getStatusCode() >= 400
            || $routeName === 'logout'
            || $request->session()->has('errors')) {
            return $response;
        }

        $this->logger->log(
            $actor,
            $actor,
            $routeName ?? strtolower($request->method()).' '.$request->path(),
            'Melakukan perubahan data'.($routeName !== null ? ' melalui '.$routeName.'.' : '.'),
            [
                'method' => $request->method(),
                'route' => $routeName,
            ],
        );

        return $response;
    }
}
