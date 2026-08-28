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
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
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

            if ($status === 419 && ! $request->expectsJson()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                Inertia::flash('toast', [
                    'type' => 'warning',
                    'message' => 'Sesi Anda telah berakhir. Silakan login kembali.',
                ]);

                return to_route('login');
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
