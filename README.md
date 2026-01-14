# Shopify App Template - Laravel

This is a template for building an [Public Embedded Shopify app](https://shopify.dev/docs/apps/build/scaffold-app).

## Benifits

This template includes an update setup consisting of:

- [Basic Shopify app configuration](https://shopify.dev/docs/apps/build/cli-for-apps/app-configuration) to configure your apps locally with TOML files and deploy your changes using Shopify CLI.
- Laravel as the backend service
- [Shopify App Bridge](https://shopify.dev/docs/api/app-bridge) to add interactivity to your app.
- [Shopify Polaris (Webcomponent)](https://shopify.dev/docs/beta/next-gen-dev-platform/polaris) to create a UI that adheres to Shopify's App Design Guidelines.
- [`Shopify/shopify-app-php`](https://github.com/Shopify/shopify-app-php) for request verification, token handling, GraphQL and helper functions

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

And set the `SHOPIFY_CLIENT_ID` environment variable

```
// web/.env
SHOPIFY_CLIENT_ID={INSERT-SHOPIFY-APP-CLIENT-ID}
```

Alternatively run the following command from the root (not the `web` folder) to copy the value from `shopify.app.toml` to `.env`:

```shell
grep 'client_id = ' shopify.app.toml | sed 's/client_id = "\(.*\)"/\1/' | xargs -I {} sed -i '' 's/^SHOPIFY_CLIENT_ID=.*/SHOPIFY_CLIENT_ID={}/' web/.env
```

### Working with the template

#### Localization

> For example, embedded apps receive the app user's chosen locale in the locale request parameter in Shopify's `GET` requests to the app.
See [here](https://shopify.dev/docs/apps/build/localize-your-app) for more details. Examples are `en-US`, `en-GB`, `en-CA` while a list of all Shopify Admin's supported languages can be found [here](https://help.shopify.com/en/manual/your-account/languages).

## Developer resources

- [Introduction to Shopify apps](https://shopify.dev/docs/apps/getting-started)
- [Shopify CLI](https://shopify.dev/docs/apps/tools/cli)

## Problems

- Installation does not copy the `.env` or `.env.example` files (or any other hidden files)
- NOTE: Provide a branch to `app init` by suffixing the url with `#foobar` for the branch named `foobar`
