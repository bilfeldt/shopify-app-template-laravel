<?php

pest()->group('auth', 'middleware');

it('redirects an unauthenticated embedded load to the patch-id-token page', function () {
    // With no id_token on the request, the Shopify-recommended flow returns the
    // package's own 302 to the patch-id-token page (App Bridge then fetches a
    // fresh token and reloads) — not a redirect to a non-existent `login` route
    // and not a generic 401. VerifyShopifyAppHome emits this before the
    // auth:apphome guard's `redirectGuestsTo(null)` 401 fallback can run.
    $this->get('/')
        ->assertStatus(302)
        ->assertRedirectContains('/auth/patch-id-token');
});

it('does not throw a RouteNotFoundException for a JSON request either', function () {
    // Same path for a request that asks for JSON: no Authorization header means
    // the package treats it as a document load and returns the patch redirect,
    // never attempting the missing `login` route.
    $this->getJson('/')
        ->assertStatus(302)
        ->assertRedirectContains('/auth/patch-id-token');
});
