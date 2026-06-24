<?php

namespace App\Services\Shopify\Exceptions;

use Illuminate\Support\Facades\Log;

/**
 * Catch-all for any log_code the package returns that we do not have an
 * explicit subclass for — currently `configuration_error`, `refresh_error`,
 * `unexpected_error`, and anything the package may introduce in the future.
 *
 * These represent "we have no recipe for this": real bugs (wrong client_id /
 * secret), unknown OAuth errors, or genuinely novel failures. Always reported
 * at error level; the default Laravel handler renders 500 (with full Ignition
 * detail in APP_DEBUG, generic error page in production).
 */
final class ShopifyUnexpectedAuthException extends ShopifyAuthException
{
    public function report(): void
    {
        Log::error('Unexpected Shopify auth failure', $this->context());
    }
}
