<?php

/**
 * Contract: with valid offline-token credentials for the test store, the
 * GraphQL client can reach Shopify and authenticate.
 *
 * Failure mode this catches: token exchange semantics changed, our auth
 * headers stopped working, the persisted token shape no longer matches what
 * Shopify expects.
 */
beforeEach(function () {
    if (! contractShopAvailable()) {
        $this->markTestSkipped('No dev store install found — run `npm run dev` once to install the app (or set the TEST_STORE_* env vars in CI).');
    }
});

it('authenticates against the real dev store', function () {
    $shop = contractShop();

    // Simplest authenticated read — if this succeeds, auth + transport + token shape all work.
    $details = $shop->shopify()->details();

    expect($details)->toBeArray()
        ->and($details['myshopifyDomain'] ?? null)->toBe($shop->shop_domain);
});
