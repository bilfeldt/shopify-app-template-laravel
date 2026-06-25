# Shopify App Template - Laravel

This is a template for building an [Public Embedded Shopify app](https://shopify.dev/docs/apps/build/scaffold-app) using Laravel.

Some Shopify Templates, including the first party ones, have a lot of code to support legacy or nieche features. This template is **intentionally keept lightweight yet complete** to support only the relevant and currently recommended features.

> [!TIP]
> Sometimes there is no need for a backend and *extension-only apps* is a great solution there. Especially with the addition of the [_App Home UI extensions_](https://shopify.dev/docs/apps/build/app-home/app-home-ui-extensions)

## Benifits

This template includes an updated setup consisting of:

- [Basic Shopify app configuration](https://shopify.dev/docs/apps/build/cli-for-apps/app-configuration) to configure your apps locally with TOML files and deploy your changes using Shopify CLI.
- Laravel 13 as the backend service
  - [Shopify managed installation](https://shopify.dev/docs/apps/build/authentication-authorization/app-installation): Simply configure relevant scopes in `shopify.app.toml`
  - [App-specific webhook subscriptions](https://shopify.dev/docs/apps/build/webhooks/subscribe#app-specific-vs-shop-specific-subscriptions): Webhooks are defined in `shopify.app.toml`
- [Shopify App Bridge](https://shopify.dev/docs/api/app-bridge) to add interactivity to your app.
- [Shopify Polaris (Webcomponent)](https://shopify.dev/docs/beta/next-gen-dev-platform/polaris) to create a UI that adheres to Shopify's App Design Guidelines.
- [`Shopify/shopify-app-php`](https://github.com/Shopify/shopify-app-php) for request verification, token handling, GraphQL and helper functions — wired into a complete, ready-to-extend backend (see [What's included](#whats-included)).

## What's included

The template ships the generic backend boilerplate every embedded app needs, so
you can delete the example page and start building features instead of plumbing:

- **Embedded App Home authentication** — a custom `apphome` auth guard
  (`App\Auth\Guards\ShopifyAppHomeGuard`) that verifies Shopify session/ID tokens
  and performs [token exchange](https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens/token-exchange)
  for an offline access token. The `VerifyShopifyAppHome` middleware returns
  App Bridge's own recovery responses (302 patch-id-token redirect / 401 retry)
  on recoverable failures, and a wrong `SHOPIFY_CLIENT_ID`/`SHOPIFY_CLIENT_SECRET`
  fails loudly instead of looping. The public `/auth/patch-id-token` route is
  wired up too.
- **Offline token storage + refresh** — `Shop` and `AccessToken` models with
  migrations; tokens are refreshed proactively before expiry and reactively on a
  401 (see `App\Services\Shopify\ShopifyShop`).
- **GraphQL client** — `$shop->shopify()->graphql()->query(...)` with automatic
  token refresh, throttle handling, and typed errors
  (`GraphQLRequestException`). `$shop->shopify()->details()` is included as a
  worked example.
- **Webhook pipeline** — a single canonical `POST /webhooks/shopify` endpoint,
  HMAC-verified by `VerifyShopifyWebhook`, routed by `WebhookDispatcher` with
  built-in deduplication. The four mandatory topics are handled out of the box:
  `app/uninstalled` plus the three GDPR compliance topics
  (`customers/data_request`, `customers/redact`, `shop/redact`). Add your own
  handlers in `App\Services\Shopify\Webhooks\Handlers`.
- **Error references** — every request is tagged with a request id
  (`AssignRequestIdMiddleware`) surfaced on the error page
  (`resources/views/errors/500.blade.php`) so merchants can quote it to support.
- **A test suite** — Unit + Feature tests covering the auth flow, webhook
  HMAC/dedup/GDPR handling, token refresh, the GraphQL client and the exception
  mapping, plus an opt-in Contract tier for real-store smoke tests.

### First-install hook

The first time a shop's offline token is created (i.e. on install/reinstall) is
the place to run one-time setup — declaring metafield definitions, seeding
defaults, registering extra webhooks, etc. Dispatch your jobs from the marked
extension point in `ShopifyAppHomeGuard::createAndSaveNewAccessToken()`.

## Getting started

### Requirements

1. You must [download and install Node.js](https://nodejs.org/en/download/) if you don't already have it.
1. You must [create a Shopify partner account](https://partners.shopify.com/signup) if you don’t have one.
1. You must create a store for testing if you don't have one, either a [development store](https://help.shopify.com/en/partners/dashboard/development-stores#create-a-development-store) or a [Shopify Plus sandbox store](https://help.shopify.com/en/partners/dashboard/managing-stores/plus-sandbox-store).

### Installing the template

This template can be used when initializing a new app using Shopify CLI

```shell
shopify app init --template=https://github.com/bilfeldt/shopify-app-template-laravel
```

### Setting up Laravel

Start by going to the laravel root folder `cd web`.

Initiate the sqlite database:

```shell
touch database/database.sqlite
```

Then copy the example environment file to a local version:

```shell
cp .env.example .env
```

Generate an app key:

```shell
php artisan key:generate
```

And set the Shopify credentials. `SHOPIFY_CLIENT_ID` is the app's Client ID (from
`shopify.app.toml`); `SHOPIFY_CLIENT_SECRET` is the app's Client secret (from the
Partner Dashboard, *Apps → your app → Client credentials*):

```
// web/.env
SHOPIFY_CLIENT_ID={INSERT-SHOPIFY-APP-CLIENT-ID}
SHOPIFY_CLIENT_SECRET={INSERT-SHOPIFY-APP-CLIENT-SECRET}
```

Alternatively run the following command from the root (not the `web` folder) to copy the `client_id` from `shopify.app.toml` to `.env` (you still need to set the secret manually):

```shell
grep 'client_id = ' shopify.app.toml | sed 's/client_id = "\(.*\)"/\1/' | xargs -I {} sed -i '' 's/^SHOPIFY_CLIENT_ID=.*/SHOPIFY_CLIENT_ID={}/' web/.env
```

### Testing

Run the bundled test suite from the `web` folder:

```shell
php artisan test                       # Unit + Feature (default)
php artisan test --testsuite=Contract  # opt-in: smoke tests against a real store
```

The Contract tier authenticates against a real store and self-skips when no dev
store / `TEST_STORE_*` credentials are available, so it is safe to run anywhere.

### Working with the template

#### Localization

> For example, embedded apps receive the app user's chosen locale in the locale request parameter in Shopify's `GET` requests to the app.
See [here](https://shopify.dev/docs/apps/build/localize-your-app) for more details. Examples are `en-US`, `en-GB`, `en-CA` while a list of all Shopify Admin's supported languages can be found [here](https://help.shopify.com/en/manual/your-account/languages).

## Developer resources

- [Introduction to Shopify apps](https://shopify.dev/docs/apps/getting-started)
- [Shopify CLI](https://shopify.dev/docs/apps/tools/cli)

## Problems

- NOTE: Provide a branch to `app init` by suffixing the url with `#foobar` for the branch named `foobar`
