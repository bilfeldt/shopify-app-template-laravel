<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<meta name="shopify-api-key" content="{{ config('shopify.client_id') }}" />
<script src="{{ config('shopify.app_bridge.js_cdn') }}"></script>
<script src="{{ config('shopify.app_bridge.ui_js_cdn') }}"></script><!-- Used for Polaris Webcomponents -->

@vite(['resources/css/app.css', 'resources/js/app.js'])
