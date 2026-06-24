<?php

use App\Enums\AccessTokenMode;
use App\Models\AccessToken;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

pest()->group('models', 'access-token');

it('can be created using the factory', function () {
    $accessToken = AccessToken::factory()->create();

    expect($accessToken)->toBeInstanceOf(AccessToken::class)
        ->and($accessToken->id)->toBeInt()
        ->and($accessToken->token)->toStartWith('shpat_')
        ->and($accessToken->mode)->toBe(AccessTokenMode::Offline)
        ->and($accessToken->scopes)->toBeArray()
        ->and($accessToken->expires_at)->toBeNull()
        ->and($accessToken->refresh_token)->toBeNull();
});

it('can be created as an expiring token', function () {
    $accessToken = AccessToken::factory()->offline()->create();

    expect($accessToken->expires_at)->not->toBeNull()
        ->and($accessToken->refresh_token)->toStartWith('shprt_')
        ->and($accessToken->refresh_token_expires_at)->not->toBeNull();
});

it('hides token and refresh_token when serialized', function () {
    $accessToken = AccessToken::factory()->offline()->create();

    $serialized = $accessToken->toArray();

    expect($serialized)->not->toHaveKey('token')
        ->and($serialized)->not->toHaveKey('refresh_token');
});

describe('casts', function () {
    it('casts mode to AccessTokenMode enum', function () {
        $accessToken = AccessToken::factory()->create(['mode' => 'offline']);

        expect($accessToken->mode)->toBeInstanceOf(AccessTokenMode::class)
            ->and($accessToken->mode)->toBe(AccessTokenMode::Offline);
    });

    it('casts scopes to an array', function () {
        $scopes = ['read_products', 'write_orders'];

        $accessToken = AccessToken::factory()->create(['scopes' => $scopes]);

        expect($accessToken->scopes)->toBe($scopes)
            ->and($accessToken->scopes)->toBeArray();
    });

    it('casts expires_at to a datetime', function () {
        $accessToken = AccessToken::factory()->offline()->create();

        expect($accessToken->expires_at)->toBeInstanceOf(CarbonImmutable::class);
    });

    it('casts refresh_token_expires_at to a datetime', function () {
        $accessToken = AccessToken::factory()->offline()->create();

        expect($accessToken->refresh_token_expires_at)->toBeInstanceOf(CarbonImmutable::class);
    });
})->group('casts');

it('allows nullable attributes to be null', function (string $attribute) {
    $accessToken = AccessToken::factory()->create([$attribute => null]);
    $accessToken->refresh();

    expect($accessToken->{$attribute})->toBeNull();
})->with([
    'expires_at',
    'refresh_token',
    'refresh_token_expires_at',
]);

it('has fillable attributes', function (string $attribute, mixed $set, mixed $get) {
    $accessToken = AccessToken::factory()->make();
    $accessToken->fill([$attribute => $set]);
    $accessToken->save();
    $accessToken->refresh();

    expect($accessToken->{$attribute})->toBe($get);
})->with([
    ['token', 'shpat_test_token', 'shpat_test_token'],
    ['mode', 'offline', AccessTokenMode::Offline],
    ['scopes', ['read_products', 'write_products'], ['read_products', 'write_products']],
]);

describe('relationships', function () {
    it('belongs to a shop', function () {
        $shop = Shop::factory()->create();
        $accessToken = AccessToken::factory()->create(['shop_id' => $shop->id]);

        expect($accessToken->shop)->toBeInstanceOf(Shop::class)
            ->and($accessToken->shop->is($shop))->toBeTrue();
    });

    it('is accessible from shop via hasOne', function () {
        $shop = Shop::factory()->create();
        $accessToken = AccessToken::factory()->create(['shop_id' => $shop->id]);

        expect($shop->accessToken)->toBeInstanceOf(AccessToken::class)
            ->and($shop->accessToken->is($accessToken))->toBeTrue();
    });
});

describe('methods', function () {
    it('converts scopes to a string', function () {
        $scopes = ['read_products', 'write_orders'];

        $accessToken = AccessToken::factory()->create(['scopes' => $scopes]);

        expect($accessToken->getScopeString())->toBe('read_products,write_orders');
    });
});
