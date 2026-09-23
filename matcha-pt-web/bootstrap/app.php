<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi kedaluwarsa atau CSRF token tidak valid. Silakan muat ulang halaman.',
                ], 419);
            }

            return back()
                ->withInput($request->except('password', 'password_confirmation', '_token'))
                ->with('error', 'Sesi Anda telah kedaluwarsa demi keamanan atau halaman sempat tidak aktif. Data isian Anda telah kami simpan, silakan tekan tombol sekali lagi.');
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 419) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json([
                        'message' => 'Sesi kedaluwarsa atau CSRF token tidak valid. Silakan muat ulang halaman.',
                    ], 419);
                }

                return back()
                    ->withInput($request->except('password', 'password_confirmation', '_token'))
                    ->with('error', 'Sesi Anda telah kedaluwarsa demi keamanan atau halaman sempat tidak aktif. Data isian Anda telah kami simpan, silakan tekan tombol sekali lagi.');
            }
        });
    })->create();
