<?php

namespace App\Http\Middleware;

use App\Auth\Guards\ShopifyAppHomeGuard;
use App\Services\Shopify\ShopifyRequestConverter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies an embedded App Home request and, on a recoverable failure, returns
 * the package-provided response exactly as Shopify recommends — a 302 to the
 * patch-id-token page (which drives App Bridge's session-token refresh) or a 401
 * carrying the `X-Shopify-Retry-Invalid-Session-Request` header — rather than a
 * generic 401 that strands the embedded UI on a blank page.
 *
 * The `ShopifyAppHomeGuard` cannot return an HTTP response (a Laravel guard only
 * resolves a user), so the recommended "return shopifyResultToResponse($result)"
 * step lives here. The guard still owns identity: it runs immediately after this
 * middleware (via `auth:apphome`) and reuses the cached verification result.
 *
 * A genuine credential misconfiguration (wrong SHOPIFY_CLIENT_ID/SECRET) is not
 * a recoverable session state — `verify()` throws there so it surfaces as a
 * reported 500 instead of an endless patch-redirect loop.
 *
 * @see https://github.com/Shopify/shopify-app-php#verifying-requests-with-exchangeable-id-tokens
 */
class VerifyShopifyAppHome
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('apphome');

        // Defensive: the apphome guard is always the Shopify guard, but guard
        // resolution is configuration-driven, so fall through rather than fail
        // if it has been swapped.
        if (! $guard instanceof ShopifyAppHomeGuard) {
            return $next($request);
        }

        // Respect an already-resolved identity (e.g. `actingAs()` in tests) —
        // verifying again would ignore the injected user and hit the network.
        if ($guard->hasUser()) {
            return $next($request);
        }

        $result = $guard->verify();

        if (! $result->ok) {
            // Not authenticated, but recoverable: hand back the package's own
            // response so App Bridge refreshes the session token and retries.
            // (A genuine credential misconfiguration never reaches here — verify()
            // throws above.) The request's error_reference rides along via Context.
            Log::info('Embedded request not authenticated; returning Shopify recovery response', [
                'log_code' => $result->log->code,
                'response_status' => $result->response->status,
            ]);

            return ShopifyRequestConverter::toResponse($result->response, $result->log);
        }

        Log::debug('Embedded request verified; proceeding', [
            'log_code' => $result->log->code,
        ]);

        return $next($request);
    }
}
