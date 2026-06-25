<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use App\Services\Shopify\ShopifyShop;
use App\Services\Shopify\ShopifyShopFactory;
use App\Services\Shopify\ShopUtil;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Shop extends Authenticatable
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    use Searchable;

    protected $fillable = [
        'shop_domain',
        'name',
        'email',
    ];

    protected static function searchableColumns(): array
    {
        return ['shop_domain', 'name', 'email'];
    }

    protected function casts(): array
    {
        return [
            'uninstalled_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the unique identifier for the shop (used by auth system).
     */
    public function getAuthIdentifierName(): string
    {
        return 'shop_domain';
    }

    /**
     * Get the unique identifier value for the shop.
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->shop_domain;
    }

    /**
     * Get the Shopify shop subdomain (without .myshopify.com).
     */
    public function getShopifySubdomain(): string
    {
        return ShopUtil::getSubdomainFromDomain($this->shop_domain);
    }

    public function accessToken(): HasOne
    {
        return $this->hasOne(AccessToken::class);
    }

    public function shopify(): ShopifyShop
    {
        return app(ShopifyShopFactory::class)->forShop($this);
    }
}
