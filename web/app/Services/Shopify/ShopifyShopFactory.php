<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use Shopify\App\ShopifyApp;

class ShopifyShopFactory
{
    public function __construct(
        protected ShopifyApp $shopifyApp,
    ) {}

    public function forShop(Shop $shop): ShopifyShop
    {
        return new ShopifyShop(
            shop: $shop,
            shopifyApp: $this->shopifyApp,
        );
    }
}
