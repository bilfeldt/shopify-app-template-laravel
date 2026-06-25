<?php

namespace App\Services\Shopify\Exceptions;

/**
 * Maps to package log_code `invalid_grant`.
 *
 * Shopify's OAuth endpoint rejected the refresh token: it has been revoked,
 * was never valid, or the merchant uninstalled and re-installed (issuing a
 * new grant). The merchant must re-authenticate.
 */
final class ShopifyInvalidGrantException extends ShopifyReauthRequiredException {}
