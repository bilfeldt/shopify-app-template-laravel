<?php

/**
 * Contract: $shop->shopify()->details() returns the shape our app assumes.
 *
 * Failure mode this catches: Shopify renamed/removed a field on the `shop`
 * root query, or changed the structure of `plan`.
 */
beforeEach(function () {
    if (! contractShopAvailable()) {
        $this->markTestSkipped('No dev store install found — run `npm run dev` once to install the app (or set the TEST_STORE_* env vars in CI).');
    }
});

it('returns the documented shop details shape', function () {
    $details = contractShop()->shopify()->details();

    expect($details)
        ->toHaveKeys(['id', 'name', 'myshopifyDomain', 'currencyCode', 'plan', 'contactEmail'])
        ->and($details['id'])->toStartWith('gid://shopify/Shop/')
        ->and($details['plan'])->toHaveKey('publicDisplayName');
});
