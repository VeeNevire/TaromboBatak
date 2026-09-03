<?php

use App\Http\Middleware\EnsureUserCanReviewContributions;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'role.admin' => EnsureUserIsAdmin::class,
            'role.contributor' => EnsureUserCanReviewContributions::class,
            'role.staff' => EnsureUserIsStaff::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();

            if ($status === 429) {
                $message = 'Terlalu banyak permintaan. Silakan coba lagi beberapa saat lagi.';

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 429, [
                        'Retry-After' => $response->headers->get('Retry-After', '60'),
                    ]);
                }

                Inertia::flash('toast', [
                    'type' => 'warning',
                    'message' => $message,
                    'center' => true,
                ]);

                return redirect()->back(303);
            }

            if ($status === 419 && ! $request->expectsJson()) {
                $authenticated = $request->user() !== null;

                Log::warning('Session/CSRF token mismatch.', [
                    'method' => $request->method(),
                    'route' => $request->route()?->getName(),
                    'authenticated' => $authenticated,
                ]);

                if (! $authenticated) {
                    Auth::logout();
                    $request->session()->invalidate();
                }

                $request->session()->regenerateToken();

                Inertia::flash('toast', [
                    'type' => 'warning',
                    'message' => $authenticated
                        ? 'Token keamanan kedaluwarsa. Silakan muat ulang halaman lalu coba lagi.'
                        : 'Sesi Anda telah berakhir. Silakan login kembali.',
                    'center' => true,
                ]);

                return $authenticated ? redirect()->back() : to_route('login');
            }

            // Navigasi browser yang ditolak kebijakan tidak perlu menampilkan
            // halaman error: kembalikan pengguna dengan pesan penjelasan.
            // Middleware role staff/admin tetap memakai halaman 403 branded
            // karena exception-nya HttpException biasa.
            $isAuthorizationDenial = $exception instanceof AuthorizationException
                || ($exception instanceof AccessDeniedHttpException
                    && $exception->getPrevious() instanceof AuthorizationException);

            if (
                $status === 403
                && $isAuthorizationDenial
                && $request->user() !== null
                && ! $request->expectsJson()
            ) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => __('Anda tidak memiliki akses untuk membuka halaman tersebut.'),
                ]);

                return redirect()->back();
            }

            $renderBranded = in_array($status, [401, 403, 404])
                || (in_array($status, [500, 503]) && ! app()->environment('local'));

            if ($renderBranded) {
                return Inertia::render('Error/Page', [
                    'status' => $status,
                ])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
