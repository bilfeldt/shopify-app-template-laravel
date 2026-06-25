<?php

namespace App\Services\Shopify\Exceptions;

/**
 * Maps to package log_code `server_error`.
 *
 * Shopify's OAuth endpoint returned 5xx and the package's internal retry
 * budget was exhausted. Transient on Shopify's side.
 */
final class ShopifyServerErrorException extends ShopifyAuthTransientException {}
