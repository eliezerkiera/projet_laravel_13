<?php

namespace App\Http\Middleware;

use App\Services\Locale\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectLocaleMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected LocaleResolver $localeResolver
    ) {}

    /**
     * Handle an incoming request and detect locale.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Resolve locale context
            $context = $this->localeResolver->resolve($request);

            // Apply the resolved locale to the application
            $this->localeResolver->apply($context);

            // Attach locale context to request for controllers
            $request->attributes->set('locale_context', $context);
        } catch (\Throwable $e) {
            // Report the error (no longer silently swallowed)
            report($e);
            app()->setLocale(config('app.locale', 'en'));
        }

        $response = $next($request);

        // Add Content-Language header to all responses (including errors)
        $response->headers->set('Content-Language', app()->getLocale());

        // Add Vary header for proper caching
        $response->setVary(['Accept-Language', 'X-Locale-Override', 'Authorization'], false);

        return $response;
    }
}
