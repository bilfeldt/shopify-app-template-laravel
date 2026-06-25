<?php

namespace App\Services\Shopify\Exceptions;

/**
 * Maps to package log_code `invalid_client`.
 *
 * The OAuth endpoint reports that our client credentials are invalid or the
 * app has been uninstalled from the shop. In practice this is overwhelmingly
 * "uninstalled" once an app is live, so we treat it as a re-auth case rather
 * than a configuration bug. Genuine bad credentials show up here too but get
 * caught by the same flow on the next install attempt.
 */
final class ShopifyInvalidClientException extends ShopifyReauthRequiredException {}
