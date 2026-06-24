@php
    // The request id shown here also tags every log line for this request.
    // See App\Http\Middleware\AssignRequestIdMiddleware.
    $reference = \Illuminate\Support\Facades\Context::get(\App\Http\Middleware\AssignRequestIdMiddleware::CONTEXT_KEY);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Something went wrong</title>
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                color: #202223;
                background: #f6f6f7;
            }
            .card {
                max-width: 28rem;
                margin: 1.5rem;
                padding: 2rem;
                text-align: center;
            }
            h1 { font-size: 1.25rem; margin: 0 0 0.5rem; }
            p { margin: 0 0 1rem; line-height: 1.5; color: #6d7175; }
            code {
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 0.8125rem;
                background: rgba(0, 0, 0, 0.06);
                padding: 0.15rem 0.4rem;
                border-radius: 0.375rem;
                word-break: break-all;
            }
        </style>
    </head>
    <body>
        <main class="card">
            <h1>Something went wrong</h1>
            <p>The app hit an unexpected error. Please try again shortly.</p>
            <p>If the problem persists, contact support and quote reference<br /><code>{{ $reference }}</code></p>
        </main>
    </body>
</html>
