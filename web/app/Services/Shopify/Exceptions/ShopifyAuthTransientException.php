<?php

namespace App\Services\Shopify\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Thrown when token refresh fails for reasons we expect to be temporary
 * (Shopify upstream 5xx after retries, or our network can't reach Shopify).
 *
 * Reported at warning level — these happen and shouldn't page oncall, but we
 * want them visible enough to spot a pattern.
 *
 * Render returns 503 + Retry-After in production. In non-production we return
 * null so APP_DEBUG=true falls through to Ignition, which surfaces the concrete
 * exception class name (e.g. ShopifyNetworkErrorException) and the package's
 * log_detail directly to the developer.
 */
abstract class ShopifyAuthTransientException extends ShopifyAuthException
{
    public function report(): void
    {
        Log::warning('Shopify auth transient failure', $this->context());
    }

    public function render(Request $request): ?Response
    {
        if (! app()->environment('production')) {
            return null;
        }

        return new Response('Service temporarily unavailable.', 503, [
            'Retry-After' => '30',
        ]);
    }
}
