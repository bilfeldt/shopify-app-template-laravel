<?php

namespace App\Services\Shopify;

use Illuminate\Support\Str;

class ShopUtil
{
    /**
     * Examples:
     * 1. example-store.myshopify.com => example-store
     * 2. https://example-store.myshopify.com => example-store
     */
    public static function getSubdomainFromDomain(string $url): string
    {
        return Str::of($url)
            ->before('.myshopify.com')
            ->__toString();
    }

    /**
     * Example: example-store => example-store.myshopify.com
     */
    public static function getDomainFromSubdomain(string $subdomain): string
    {
        return $subdomain.'.myshopify.com';
    }
}
