<?php

namespace App\Services\Shopify\Exceptions;

/**
 * Maps to package log_code `network_error`.
 *
 * Guzzle threw a RequestException trying to reach Shopify's OAuth endpoint —
 * DNS, TLS, connection reset, tunnel hiccup, etc. Transient; the next request
 * usually succeeds.
 */
final class ShopifyNetworkErrorException extends ShopifyAuthTransientException {}
