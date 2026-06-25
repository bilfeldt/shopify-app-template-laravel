<?php

namespace App\Services\Shopify\Exceptions;

use InvalidArgumentException;

class MissingApiVersionException extends InvalidArgumentException implements ShopifyGraphQLExceptionInterface
{
    public function __construct(
        protected ?string $shopDomain = null,
        string $message = 'SHOPIFY_API_VERSION is set but empty. Set it to a Shopify Admin API version (e.g. 2026-01) or remove it to use the configured default.',
    ) {
        parent::__construct($message);
    }

    public function getShopDomain(): ?string
    {
        return $this->shopDomain;
    }
}
