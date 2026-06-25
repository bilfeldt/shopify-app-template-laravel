<?php

namespace App\Models;

use App\Enums\AccessTokenMode;
use Database\Factories\AccessTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Offline access token for a shop. One row per shop, rotated in place on refresh.
 *
 * Online (user-scoped) tokens are not persisted — when the app needs to act in
 * the context of a request, it exchanges the session JWT on demand instead.
 *
 * Example: Expiring offline access token
 * {
 *   "access_token": "f85632530bf277ec9ac6f649fc327f17",
 *   "scope": "write_orders,read_customers",
 *   "expires_in": 3600,
 *   "refresh_token": "shprt_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
 *   "refresh_token_expires_in": 7776000
 * }
 *
 * Example: Non-expiring offline access token
 * {
 *   "access_token": "f85632530bf277ec9ac6f649fc327f17",
 *   "scope": "write_orders,read_customers"
 * }
 *
 * @see https://github.com/Shopify/shopify-app-php/tree/main?tab=readme-ov-file#app-home
 * @see https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens/offline-access-tokens
 */
class AccessToken extends Model
{
    /** @use HasFactory<AccessTokenFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'token',
        'mode',
        'scopes',
        'expires_at',
        'refresh_token',
        'refresh_token_expires_at',
    ];

    protected $hidden = [
        'token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'mode' => AccessTokenMode::class,
            'scopes' => 'array',
            'expires_at' => 'immutable_datetime',
            'refresh_token_expires_at' => 'immutable_datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function getScopeString(): string
    {
        return implode(',', $this->scopes);
    }
}
