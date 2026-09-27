<?php

namespace App\Http\Middleware;

use App\Services\LocaleDetectionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectLocaleMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected LocaleDetectionService $localeDetectionService
    ) {}

    /**
     * Handle an incoming request and detect locale.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Detect country and language
            $detection = $this->localeDetectionService->detect($request);

            // Apply locale to application
            $this->localeDetectionService->applyLocale($detection);

            // Attach detection results to request for controllers
            $request->attributes->set('detected_country', $detection['country']);
            $request->attributes->set('detected_language', $detection['language']);
            $request->attributes->set('country_source', $detection['country_source']);
            $request->attributes->set('language_source', $detection['language_source']);
        } catch (\Exception $e) {
            // Fallback to default locale on any error
            app()->setLocale(config('app.locale', 'en'));
        }

        return $next($request);
    }
}
