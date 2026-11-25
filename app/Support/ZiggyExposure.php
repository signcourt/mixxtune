<?php

namespace App\Support;

use App\Services\V2\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * W201 MIXXTUNE_ROUTE_EXPOSURE
 *
 * The Ziggy `@routes` directive used to send EVERY named route (super-admin and admin
 * included) to EVERY browser, so anyone could read the whole panel's URL map from
 * "view source". This builds the same Ziggy script but only with the routes the
 * current visitor may use, decided by the URI prefix of each route (exact, not by name).
 *
 *   guest ........ only login / password / invitation / verification / public / storage
 *   super_admin .. everything
 *   admin ........ everything except super-admin/*
 *   others ....... everything except super-admin/*, admin/*, mail/*
 *
 * Fail-safe in the browser: if a page still asks route() for a hidden name, the link
 * becomes "#" with a console warning instead of a white page.
 * If anything goes wrong on the server, the unfiltered Ziggy output is returned and a
 * warning is logged, so the site never breaks because of this class.
 */
final class ZiggyExposure
{
    /** @var string[] URI prefixes a guest (not logged in) may know about */
    private const GUEST_ALLOWED = [
        '', 'up', 'login', 'logout', 'register', 'password', 'forgot-password',
        'reset-password', 'confirm-password', 'verify-email', 'email',
        'invitation-set-password', 'public', 'storage', 'sanctum',
    ];

    public static function html(?Request $request = null): string
    {
        try {
            return self::build($request ?? request());
        } catch (\Throwable $e) {
            Log::warning('W201 ZiggyExposure fell back to full @routes: '.$e->getMessage());

            return self::fallback();
        }
    }

    private static function build(Request $request): string
    {
        $ziggyClass = class_exists(\Tighten\Ziggy\Ziggy::class)
            ? \Tighten\Ziggy\Ziggy::class
            : \Tightenco\Ziggy\Ziggy::class;

        $generatorClass = class_exists(\Tighten\Ziggy\BladeRouteGenerator::class)
            ? \Tighten\Ziggy\BladeRouteGenerator::class
            : \Tightenco\Ziggy\BladeRouteGenerator::class;

        $hidden = self::hiddenRouteNames($request);

        $ziggy = new $ziggyClass();
        if ($hidden !== []) {
            /* second argument false = "except these names" */
            $ziggy->filter($hidden, false);
        }

        $json = $ziggy->toJson();

        $routeFunction = self::routeFunction($generatorClass);

        $guard = 'try{var __r=window.route;if(typeof __r==="function"){window.route=function(){'
            .'if(arguments.length===0){return __r.apply(this,arguments);}'
            .'try{return __r.apply(this,arguments);}catch(e){'
            .'if(window.console&&console.warn){console.warn("route hidden for this account:",arguments[0]);}'
            .'return "#";}};}}catch(e){}';

        return '<script type="text/javascript">const Ziggy='.$json.';'.$routeFunction.$guard.'</script>';
    }

    /**
     * The browser-side route() helper that Ziggy ships. Ziggy 2.x keeps it in
     * dist/route.umd.js; older versions exposed it through a private method.
     */
    private static function routeFunction(string $generatorClass): string
    {
        foreach ([
            base_path('vendor/tightenco/ziggy/dist/route.umd.js'),
            base_path('vendor/tightenco/ziggy/dist/index.js'),
        ] as $file) {
            if (is_file($file)) {
                $js = (string) file_get_contents($file);
                if ($js !== '') {
                    return $js;
                }
            }
        }

        if (method_exists($generatorClass, 'getRouteFunction')) {
            $fn = new \ReflectionMethod($generatorClass, 'getRouteFunction');
            $fn->setAccessible(true);

            return (string) $fn->invoke(new $generatorClass());
        }

        throw new \RuntimeException('Ziggy route() helper file not found');
    }

    /** Names of the routes this visitor must NOT receive (decided by URI prefix). */
    private static function hiddenRouteNames(Request $request): array
    {
        $user = $request->user();
        $role = app(PermissionService::class)->role($user);

        $hidePrefixes = match (true) {
            $user === null => null,                       /* guest: allow-list below */
            $role === 'super_admin' => [],
            $role === 'admin' => ['super-admin'],
            default => ['super-admin', 'admin', 'mail'],
        };

        $hidden = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name) {
                continue;
            }
            $prefix = explode('/', ltrim((string) $route->uri(), '/'))[0];

            if ($hidePrefixes === null) {
                if (! in_array($prefix, self::GUEST_ALLOWED, true)) {
                    $hidden[] = $name;
                }
                continue;
            }

            if (in_array($prefix, $hidePrefixes, true)) {
                $hidden[] = $name;
            }
        }

        return array_values(array_unique($hidden));
    }

    private static function fallback(): string
    {
        $generatorClass = class_exists(\Tighten\Ziggy\BladeRouteGenerator::class)
            ? \Tighten\Ziggy\BladeRouteGenerator::class
            : \Tightenco\Ziggy\BladeRouteGenerator::class;

        return (string) (new $generatorClass())->generate();
    }
}
