# Shopify App Template - Laravel

This is a template for building an [Public Embedded Shopify app](https://shopify.dev/docs/apps/build/scaffold-app).

## Benifits

This template includes an update setup consisting of:

- [Basic Shopify app configuration](https://shopify.dev/docs/apps/build/cli-for-apps/app-configuration) to configure your apps locally with TOML files and deploy your changes using Shopify CLI.
- Laravel as the backend service
- [Shopify App Bridge](https://shopify.dev/docs/api/app-bridge) to add interactivity to your app.
- [Shopify Polaris (Webcomponent)](https://shopify.dev/docs/beta/next-gen-dev-platform/polaris) to create a UI that adheres to Shopify's App Design Guidelines.

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

## Developer resources

- [Introduction to Shopify apps](https://shopify.dev/docs/apps/getting-started)
- [Shopify CLI](https://shopify.dev/docs/apps/tools/cli)

## Problems

- Installation does not copy the `.env` or `.env.example` files (or any other hidden files)
- NOTE: Provide a branch to `app init` by suffixing the url with `#foobar` for the branch named `foobar`
