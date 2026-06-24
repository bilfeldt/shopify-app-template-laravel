<?php

namespace App\Actions\Auth;

use App\Enums\AccessTokenMode;
use App\Models\AccessToken;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;
use Shopify\App\Types\TokenExchangeAccessToken;

class SaveTokenExchangeAccessTokenAction
{
    public function execute(Shop $shop, TokenExchangeAccessToken $accessToken): AccessToken
    {
        $model = $shop->accessToken()->updateOrCreate([], [
            'token' => $accessToken->token,
            'mode' => AccessTokenMode::Offline,
            'scopes' => explode(',', $accessToken->scope),
            'expires_at' => $accessToken->expires, // Note uses format 'Y-m-d\TH:i:s\Z'
            'refresh_token' => $accessToken->refreshToken,
            'refresh_token_expires_at' => $accessToken->refreshTokenExpires, // Note uses format 'Y-m-d\TH:i:s\Z'
        ]);
        $shop->load('accessToken'); // Because the relationship is already loaded, then we need to reload it here, otherwise the new relationship is not found

        Log::info('Saved AccessToken-model', [
            'shop_id' => $shop->id,
            'access_token_id' => $model->id,
        ]);

        return $model;
    }
}
