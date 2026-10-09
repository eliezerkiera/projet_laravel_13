<?php

namespace App\Services\Locale;

use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Service responsible for resolving the locale (language + country) for a request.
 * This service performs resolution WITHOUT side effects (no database writes during detection).
 * It uses the following priority for language:
 * 1. X-Locale-Override header (normalized, must be supported)
 * 2. User profile language (if exists and is supported)
 * 3. Accept-Language header (first supported language by q-value order)
 * 4. Country's language (if country resolved and has a language)
 * 5. Default language from config('app.locale')
 *
 * For country:
 * 1. User profile country (returned even if inactive)
 * 2. Single active country (if exactly one exists)
 * 3. None (null)
 */
class LocaleResolver
{
    /**
     * Cache for supported languages (by code) for the duration of the request.
     */
    protected ?array $supportedLanguages = null;

    /**
     * Cache for active countries count.
     */
    protected ?int $activeCountriesCount = null;

    /**
     * Resolve the locale context for the given request.
     */
    public function resolve(Request $request): LocaleContext
    {
        // Reset caches for each resolution to ensure fresh data per request
        $this->supportedLanguages = null;
        $this->activeCountriesCount = null;

        // Get the authenticated user via sanctum guard (middleware runs before auth:sanctum)
        $user = $request->user('sanctum');

        // Resolve country first (needed for language fallback)
        $country = $this->resolveCountry($user);

        // Resolve language
        $language = $this->resolveLanguage($request, $user, $country);

        // Determine country selection required
        $countrySelectionRequired = $this->isCountrySelectionRequired($country, $user);

        return LocaleContext::create(
            $language,
            $this->getLanguageSource($request, $user, $country, $language),
            $country,
            $this->getCountrySource($user, $country),
            $countrySelectionRequired
        );
    }

    /**
     * Apply the resolved locale context to the application.
     */
    public function apply(LocaleContext $context): void
    {
        $locale = $context->language?->code ?? config('app.locale', 'en');
        app()->setLocale($locale);
    }

    /**
     * Resolve the country for the request.
     */
    protected function resolveCountry(?User $user): ?Country
    {
        // Priority 1: User profile country (returned even if inactive)
        if ($user?->country_id) {
            return $user->country;
        }

        // Priority 2: Single active country
        if ($this->getActiveCountriesCount() === 1) {
            return Country::query()->where('is_active', true)->first();
        }

        // Priority 3: None
        return null;
    }

    /**
     * Resolve the language for the request.
     */
    protected function resolveLanguage(Request $request, ?User $user, ?Country $country): ?Language
    {
        // Priority 1: X-Locale-Override header
        $override = $request->header('X-Locale-Override');
        if ($override) {
            $normalized = $this->normalizeLanguageCode($override);
            $language = $this->getSupportedLanguage($normalized);
            if ($language) {
                return $language;
            }
        }

        // Priority 2: User profile language
        if ($user?->language_id) {
            $language = $this->getSupportedLanguageById($user->language_id);
            if ($language) {
                return $language;
            }
        }

        // Priority 3: Accept-Language header (respecting q-values order)
        $languages = $request->getLanguages();
        foreach ($languages as $lang) {
            $normalized = $this->normalizeLanguageCode($lang);
            $language = $this->getSupportedLanguage($normalized);
            if ($language) {
                return $language;
            }
        }

        // Priority 4: Country's language
        if ($country?->language_id) {
            $language = $this->getSupportedLanguageById($country->language_id);
            if ($language) {
                return $language;
            }
        }

        // Priority 5: Default language from config
        $defaultCode = config('app.locale', 'en');

        return $this->getSupportedLanguage($defaultCode);
    }

    /**
     * Get the language source string based on resolution.
     */
    protected function getLanguageSource(Request $request, ?User $user, ?Country $country, ?Language $resolvedLanguage): string
    {
        $override = $request->header('X-Locale-Override');

        // Check if override was the source
        if ($override) {
            $normalized = $this->normalizeLanguageCode($override);
            $language = $this->getSupportedLanguage($normalized);
            if ($language && $language->code === $resolvedLanguage?->code) {
                return 'override';
            }
        }

        // Check if profile was the source
        if ($user?->language_id && $resolvedLanguage && $user->language_id === $resolvedLanguage->id) {
            return 'profile';
        }

        // Check if Accept-Language was the source
        $languages = $request->getLanguages();
        foreach ($languages as $lang) {
            $normalized = $this->normalizeLanguageCode($lang);
            $language = $this->getSupportedLanguage($normalized);
            if ($language && $language->code === $resolvedLanguage?->code) {
                return 'accept_language';
            }
        }

        // Check if country's language was the source
        if ($country?->language_id && $resolvedLanguage && $country->language_id === $resolvedLanguage->id) {
            return 'country';
        }

        // Default
        return 'default';
    }

    /**
     * Get the country source string based on resolution.
     */
    protected function getCountrySource(?User $user, ?Country $country): string
    {
        if ($user?->country_id && $country && $user->country_id === $country->id) {
            return 'profile';
        }

        if ($country && $this->getActiveCountriesCount() === 1) {
            return 'single_active';
        }

        return 'none';
    }

    /**
     * Determine if country selection is required.
     * Formula: activeCount >= 1 && !(country !== null && country->is_active)
     */
    protected function isCountrySelectionRequired(?Country $country, ?User $user): bool
    {
        $activeCount = $this->getActiveCountriesCount();

        if ($activeCount < 1) {
            return false;
        }

        // Check if we have a country that is active
        if ($country !== null && $country->is_active) {
            return false;
        }

        // Check if user has a country that is active
        if ($user?->country_id && $user->country && $user->country->is_active) {
            return false;
        }

        return true;
    }

    /**
     * Normalize a language code: lowercase, trim, take part before first - or _
     */
    protected function normalizeLanguageCode(string $code): string
    {
        $code = strtolower(trim($code));
        $pos = strpos($code, '-');
        if ($pos !== false) {
            return substr($code, 0, $pos);
        }
        $pos = strpos($code, '_');
        if ($pos !== false) {
            return substr($code, 0, $pos);
        }

        return $code;
    }

    /**
     * Get supported languages indexed by code (cached per request).
     */
    protected function getSupportedLanguages(): array
    {
        if ($this->supportedLanguages !== null) {
            return $this->supportedLanguages;
        }

        $languages = Language::query()
            ->where('is_active', true)
            ->get()
            ->keyBy('code');

        $this->supportedLanguages = $languages->all();

        return $this->supportedLanguages;
    }

    /**
     * Get a supported language by code.
     */
    protected function getSupportedLanguage(string $code): ?Language
    {
        $languages = $this->getSupportedLanguages();

        return $languages[$code] ?? null;
    }

    /**
     * Get a supported language by ID.
     */
    protected function getSupportedLanguageById(int $id): ?Language
    {
        $languages = $this->getSupportedLanguages();
        foreach ($languages as $language) {
            if ($language->id === $id) {
                return $language;
            }
        }

        return null;
    }

    /**
     * Get the count of active countries (cached per request).
     */
    protected function getActiveCountriesCount(): int
    {
        if ($this->activeCountriesCount !== null) {
            return $this->activeCountriesCount;
        }

        $this->activeCountriesCount = (int) Country::query()
            ->where('is_active', true)
            ->count();

        return $this->activeCountriesCount;
    }

    /**
     * Get all active countries with their language (for API responses).
     */
    public function getActiveCountriesWithLanguage(): array
    {
        return Country::query()
            ->where('is_active', true)
            ->with('language')
            ->orderBy('sort_order')
            ->get()
            ->all();
    }

    /**
     * Get all supported languages (for API responses).
     */
    public function getSupportedLanguagesList(): array
    {
        return Language::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->all();
    }
}
