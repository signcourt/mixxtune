<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool =>
                $request->expectsJson()
                || $request->is('api/*'),
        );

        $exceptions->respond(function (
            \Symfony\Component\HttpFoundation\Response $response,
            \Throwable $exception,
            Request $request
        ) {
            if (
                $response->getStatusCode() === 403
                && ! $request->expectsJson()
                && ! $request->is('api/*')
                && $request->session()->has(
                    'impersonator_user_id'
                )
                && $request->user()
            ) {
                $dashboard = match (
                    $request->user()->role
                ) {
                    'admin' =>
                        '/admin/dashboard',

                    'label' =>
                        '/label/dashboard',

                    'artist' =>
                        '/artist/dashboard',

                    default =>
                        '/',
                };

                return redirect($dashboard)->with(
                    'error',
                    'That page is not available while viewing another account.'
                );
            }

            return $response;
        });
    })
    ->create();
