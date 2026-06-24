<?php

namespace App\Services\Shopify\Exceptions;

/**
 * Maps to package log_code `refresh_token_expired`.
 *
 * The stored refresh token's `refreshTokenExpires` timestamp is in the past,
 * so the package short-circuits before hitting Shopify. The merchant must
 * re-authenticate to get a fresh token pair.
 */
final class ShopifyRefreshTokenExpiredException extends ShopifyReauthRequiredException {}
