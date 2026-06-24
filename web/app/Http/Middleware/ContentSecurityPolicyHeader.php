<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicyHeader
{
    /**
     * Ensures that the request is setting the appropriate CSP frame-ancestor directive.
     *
     * @see https://shopify.dev/docs/apps/build/security/set-up-iframe-protection
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Ensure custom CSP middleware registered later in the stack gets precedence
        if ($this->hasCspHeader($response)) {
            return $response;
        }

        $shop = $request->query('shop', '');

        $domainHost = $shop ? "https://$shop" : '*.myshopify.com';
        $allowedDomains = "$domainHost https://admin.shopify.com https://extensions.shopifycdn.com";

        $response->headers->set('Content-Security-Policy', "frame-ancestors $allowedDomains;");
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        return $response;
    }

    private function hasCspHeader(mixed $response): bool
    {
        if (! $response instanceof Response) {
            return false;
        }

        return $response->headers->has('Content-Security-Policy');
    }
}
