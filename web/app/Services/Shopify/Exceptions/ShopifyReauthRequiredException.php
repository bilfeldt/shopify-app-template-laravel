<?php

namespace App\Services\Shopify\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Thrown when the merchant must re-authenticate (the offline refresh token is
 * dead, the grant was revoked, or the app was uninstalled).
 *
 * Concrete subclasses are the actual `log_code` values; this intermediate
 * carries the shared rendering + reporting behaviour.
 *
 * Production response shape follows the App Bridge "reauthorize" convention:
 * a 401 with the X-Shopify-API-Request-Failure-Reauthorize headers. App Bridge
 * (loaded by the embedded iframe) reads those headers and triggers a top-level
 * re-auth navigation. For non-XHR document loads we still return the 401 — the
 * App Bridge bootstrap on the host page handles the redirect.
 *
 * Not reported: a refresh-token death is a normal lifecycle event for long-lived
 * installs, not a bug to page oncall about.
 */
abstract class ShopifyReauthRequiredException extends ShopifyAuthException
{
    public function report(): bool
    {
        Log::info('Shopify re-authentication required', $this->context());

        return false;
    }

    public function render(Request $request): Response
    {
        return new Response('', 401, [
            'X-Shopify-API-Request-Failure-Reauthorize' => '1',
            'X-Shopify-API-Request-Failure-Reauthorize-Url' => $this->reauthorizeUrl(),
        ]);
    }

    /**
     * Where App Bridge should send the merchant to re-acquire a valid token.
     *
     * For token-exchange apps the canonical recovery is to re-load the embedded
     * app entry-point — Shopify Admin issues a fresh id_token, which the guard
     * then exchanges for a new access token. A dedicated `/auth` route is only
     * needed if we ever fall back to the classic OAuth grant flow.
     */
    protected function reauthorizeUrl(): string
    {
        return '/';
    }
}
