<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    /**
     * Supported application locales.
     *
     * @var list<string>
     */
    protected array $supportedLocales = ['en', 'fr'];

    /**
     * Handle an incoming request and set the application locale.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Default locale is English as priority
        $locale = 'en';

        // 1. Check Accept-Language header
        $header = $request->header('Accept-Language');
        if ($header) {
            $preferred = $request->getPreferredLanguage($this->supportedLocales);
            if ($preferred && in_array($preferred, $this->supportedLocales, true)) {
                $locale = $preferred;
            }
        } elseif ($request->user() && $request->user()->relationLoaded('language') && $request->user()->language) {
            // 2. Check authenticated user's preferred language code
            $userLocale = strtolower((string) $request->user()->language->code);
            if (in_array($userLocale, $this->supportedLocales, true)) {
                $locale = $userLocale;
            }
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
