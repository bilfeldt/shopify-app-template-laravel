<?php

namespace App\Services\Shopify\Exceptions;

use InvalidArgumentException;

class MissingAccessTokenException extends InvalidArgumentException implements ShopifyGraphQLExceptionInterface
{
    public function __construct(
        protected ?string $shopDomain = null,
        string $message = 'Shop does not have an access token',
    ) {
        parent::__construct($message);
    }

    public function getShopDomain(): ?string
    {
        return $this->shopDomain;
    }
}
