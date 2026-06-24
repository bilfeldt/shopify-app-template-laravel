<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns a unique request id (UUID v7) to the request {@see Context} so every
 * log line for the request carries it, and any error page can show the merchant
 * the same id to quote to support.
 */
class AssignRequestIdMiddleware
{
    /** Context key (and log field) the request id is stored under. */
    public const CONTEXT_KEY = 'request_id';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Context::add(self::CONTEXT_KEY, (string) Str::uuid7());

        return $next($request);
    }
}
