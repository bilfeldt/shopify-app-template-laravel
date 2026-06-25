<?php

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

pest()->group('models', 'searchable');

it('returns matching shops when searching by domain', function () {
    Shop::factory()->create(['shop_domain' => 'matching-shop.myshopify.com']);
    Shop::factory()->create(['shop_domain' => 'other-shop.myshopify.com']);

    $results = Shop::search('matching');

    expect($results)->toHaveCount(1)
        ->and($results->first()->shop_domain)->toBe('matching-shop.myshopify.com');
});

it('returns matching shops when searching by name', function () {
    Shop::factory()->create(['name' => 'Awesome Store']);
    Shop::factory()->create(['name' => 'Regular Store']);

    $results = Shop::search('Awesome');

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Awesome Store');
});

it('returns matching shops when searching by email', function () {
    Shop::factory()->create(['email' => 'hello@awesome.com']);
    Shop::factory()->create(['email' => 'hello@regular.com']);

    $results = Shop::search('awesome');

    expect($results)->toHaveCount(1)
        ->and($results->first()->email)->toBe('hello@awesome.com');
});

it('returns empty collection when no match is found', function () {
    Shop::factory()->create(['shop_domain' => 'test.myshopify.com']);

    $results = Shop::search('nonexistent');

    expect($results)->toHaveCount(0);
});

it('returns all shops up to the limit when query is empty', function () {
    Shop::factory()->count(3)->create();

    $results = Shop::search('');

    expect($results)->toHaveCount(3);
});

it('respects the limit parameter', function () {
    Shop::factory()->count(5)->create();

    $results = Shop::search('', limit: 2);

    expect($results)->toHaveCount(2);
});
