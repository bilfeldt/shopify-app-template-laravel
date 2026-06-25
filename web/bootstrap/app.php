<?php

use App\Http\Middleware\AssignRequestIdMiddleware;
use App\Http\Middleware\ContentSecurityPolicyHeader;
use App\Http\Middleware\VerifyShopifyAppHome;
use App\Http\Middleware\VerifyShopifyWebhook;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Shopify webhooks: registered outside the web group (no session,
            // no CSRF), protected by HMAC verification only.
            Route::middleware(VerifyShopifyWebhook::class)
                ->group(__DIR__.'/../routes/webhooks.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app always runs behind a TLS-terminating proxy (cloudflare tunnel
        // in dev, the platform load balancer in production) that forwards plain
        // HTTP. Trusting the X-Forwarded-* headers makes Laravel see the real
        // https scheme — otherwise generated URLs (including asset URLs) come
        // out as http://, and the browser's mixed-content auto-upgrade reloads
        // assets under a second URL.
        $middleware->trustProxies(at: '*');

        $middleware->prepend(ContentSecurityPolicyHeader::class);

        // Tag every request (and its log lines) with a request id the merchant
        // can quote to support. Prepended last so it runs first — before anything
        // that might fail. See App\Http\Middleware\AssignRequestIdMiddleware.
        $middleware->prepend(AssignRequestIdMiddleware::class);

        // Shopify verification must run before the `auth:apphome` guard resolves,
        // so a recoverable failure returns the package's own response (302 patch
        // redirect / 401 retry) instead of the framework's generic 401. Laravel's
        // middleware priority otherwise floats Authenticate ahead of an
        // unprioritised route middleware, so pin our order explicitly.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: VerifyShopifyAppHome::class,
        );

        // Embedded Shopify apps have no `login` route: real traffic always
        // arrives inside the Admin iframe with a valid id_token. Returning null
        // here makes the framework respond with a plain 401 instead of trying
        // to redirect to a non-existent named route.
        $middleware->redirectGuestsTo(fn () => null);

        // The embedded app runs inside the Shopify Admin iframe, where the
        // session cookie is third-party and routinely blocked by the browser.
        // Every request therefore starts a fresh session, so the CSRF token can
        // never match and POST/PATCH/DELETE requests fail with 419. Those routes
        // are not cookie-authenticated — the `auth:apphome` guard verifies a
        // Shopify session token in the Authorization header, which a cross-site
        // attacker cannot forge — so CSRF protection is both unnecessary and
        // broken here. List your embedded app's mutating routes below to exempt
        // them, e.g. 'settings', 'settings/*'.
        $middleware->validateCsrfTokens(except: [
            //
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Errors render through Laravel's Blade error views in
        // resources/views/errors/. The bundled errors/500.blade.php surfaces the
        // request id (App\Http\Middleware\AssignRequestIdMiddleware) so merchants
        // can quote it to support.
    })->create();
