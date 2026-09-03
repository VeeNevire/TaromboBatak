<?php

namespace App\Actions\Fortify;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class VerifyTurnstile
{
    public function handle(Request $request, Closure $next): mixed
    {
        $secret = config('services.turnstile.secret_key');

        if (blank($secret)) {
            if (app()->isProduction()) {
                throw ValidationException::withMessages([
                    'turnstile' => 'Verifikasi keamanan belum dikonfigurasi.',
                ]);
            }

            return $next($request);
        }

        $token = $request->string('cf-turnstile-response')->toString();

        if (blank($token)) {
            throw ValidationException::withMessages([
                'turnstile' => 'Silakan selesaikan verifikasi keamanan terlebih dahulu.',
            ]);
        }

        $response = Http::asForm()
            ->timeout(5)
            ->connectTimeout(3)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

        if (! $response->successful() || ! $response->json('success', false)) {
            throw ValidationException::withMessages([
                'turnstile' => 'Verifikasi keamanan gagal. Silakan coba lagi.',
            ]);
        }

        return $next($request);
    }
}
