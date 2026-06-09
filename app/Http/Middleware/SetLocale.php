<?php

namespace App\Http\Middleware;

use App\Support\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the app locale for the request from {@see LocaleResolver} (user preference → device language
 * → fallback). Runs before {@see HandleInertiaRequests} so the shared `locale` + `translations`
 * props reflect it.
 */
class SetLocale
{
    public function __construct(private readonly LocaleResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolver->resolve($request));

        return $next($request);
    }
}
