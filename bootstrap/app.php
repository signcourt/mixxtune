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
        /* W230 MIXXTUNE_DISTRIBUTOR_HOST: a distributor's own address gets its own session cookie */
        $middleware->web(prepend: [
            \App\Http\Middleware\DistributorHostContext::class,
        ]);

        $middleware->web(append: [
            /* W230: on a distributor's address only his own tree can be logged in */
            \App\Http\Middleware\DistributorHostGuard::class,
            \App\Http\Middleware\EnsureAccountActive::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            /* W135 MIXXTUNE_IDLE_LOGOUT: sign out after idle, then e-mail OTP again */
            \App\Http\Middleware\EnforceIdleTimeout::class,
            \App\Http\Middleware\EnforceMaintenanceMode::class,
            /* W129 MIXXTUNE_OFFICE_LOGIN: office staff stay inside My Office */
            \App\Http\Middleware\OfficeStaffOnly::class,
            /* W226 MIXXTUNE_SUB_LABEL_MONEY: a distributor's sub-label uses My Earnings, not the wallet / report pages */
            \App\Http\Middleware\SubLabelMoneyGuard::class,
            /* W228 MIXXTUNE_DISTRIBUTOR_TREE_HOLD: a distributor tree put on hold by a Super Admin cannot use the panel */
            \App\Http\Middleware\DistributorTreeHold::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        /* W201 MIXXTUNE_TRUSTED_HOSTS: only our own host names may reach the app (400 otherwise) */
        $middleware->trustHosts(
            at: fn () => \App\Support\TrustedHosts::patterns(),
            subdomains: false
        );

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            /* W128 MIXXTUNE_OFFICE_MONITOR: the desktop agent's own token */
            'office.agent' => \App\Http\Middleware\OfficeAgentToken::class,
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
