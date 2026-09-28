<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the news agent (Hermes Agent) in with the shared secret token from
 * MARGA_NEWS_AGENT_TOKEN, sent as "Authorization: Bearer <token>".
 */
class AuthenticateMargaNewsAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.marga_news.agent_token');

        abort_if(! is_string($token) || $token === '', 503, 'Agen berita belum dikonfigurasi.');

        $given = $request->bearerToken();

        abort_unless(is_string($given) && hash_equals($token, $given), 401, 'Token agen tidak valid.');

        return $next($request);
    }
}
