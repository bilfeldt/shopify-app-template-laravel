<?php

namespace App\Services\Shopify\Exceptions;

interface ShopifyGraphQLExceptionInterface
{
    public function getShopDomain(): ?string;
}
