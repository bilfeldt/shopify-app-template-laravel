<?php

namespace App\Actions;

use App\Actions\Auth\SaveTokenExchangeAccessTokenAction;
use App\Events\ShopifyAccessTokenRefreshed;
use App\Models\AccessToken;
use App\Models\Shop;
use App\Services\Shopify\Exceptions\AccessTokenStillValidException;
use App\Services\Shopify\ShopifyShopFactory;
use Illuminate\Support\Facades\Log;

class RefreshAccessTokenAction
{
    public function __construct(
        protected ShopifyShopFactory $shopifyServiceFactory,
        protected SaveTokenExchangeAccessTokenAction $saveTokenAction,
    ) {}

    public function execute(Shop $shop): AccessToken
    {
        $service = $this->shopifyServiceFactory->forShop($shop);

        Log::info('Refreshing access token for shop', [
            'shop_id' => $shop->id,
        ]);

        try {
            $newToken = $service->refreshAccessToken();
        } catch (AccessTokenStillValidException) {
            // Shopify considers the current token still valid and declined to
            // issue a new one. That is a no-op, not a failure: keep using the
            // existing token. This happens routinely because offline tokens
            // live only ~60 minutes, so the proactive buffer can fire while
            // Shopify still regards the token as fresh.
            Log::info('Access token still valid; keeping the existing one', [
                'shop_id' => $shop->id,
            ]);

            return $shop->accessToken;
        }

        $savedToken = $this->saveTokenAction->execute($shop, $newToken);

        ShopifyAccessTokenRefreshed::dispatch($shop);

        Log::info('Access token refreshed and saved', [
            'shop_id' => $shop->id,
            'access_token_id' => $savedToken->id,
        ]);

        return $savedToken;
    }
}
