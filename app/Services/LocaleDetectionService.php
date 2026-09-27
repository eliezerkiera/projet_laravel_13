<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use Illuminate\Http\Request;

class LocaleDetectionService
{
    /**
     * Detect and resolve country and language for the current request.
     *
     * @return array{country: Country|null, language: Language|null, country_source: string, language_source: string}
     */
    public function detect(Request $request): array
    {
        $user = $request->user();
        $country = $this->resolveCountry($user);
        $override = $request->header('X-Locale-Override');
        $language = $this->resolveLanguage($request, $user, $country, $override);

        if ($user && ! $user->country_id && $country) {
            $user->update([
                'country_id' => $country->id,
                'country_source' => 'auto',
            ]);
        }

        if ($user && ! $user->language_id && $language && ! $override) {
            $user->update([
                'language_id' => $language->id,
                'language_source' => 'auto',
            ]);
        }

        return [
            'country' => $country,
            'language' => $language,
            'country_source' => $user?->country_id ? ($user->country_source ?? 'auto') : 'auto',
            'language_source' => $override ? 'override' : ($user?->language_id ? ($user->language_source ?? 'auto') : 'auto'),
        ];
    }

    /**
     * Resolve the country without using an IP address.
     */
    protected function resolveCountry(?User $user): ?Country
    {
        if ($user?->country_id) {
            return $user->country;
        }

        // Automatic assignment remains entirely data-driven as active countries change.
        if (Country::query()->where('is_active', true)->count() !== 1) {
            return null;
        }

        return Country::query()->where('is_active', true)->first();
    }

    /**
     * Resolve the language using the established preference cascade.
     */
    protected function resolveLanguage(Request $request, ?User $user, ?Country $country, ?string $override): ?Language
    {
        if ($override) {
            return Language::query()->where('code', $override)->first();
        }

        if ($user?->language_id) {
            return $user->language;
        }

        if ($request->header('Accept-Language')) {
            $preferredLanguage = $request->getPreferredLanguage(['fr', 'en']);
            $language = Language::query()->where('code', $preferredLanguage)->first();

            if ($language) {
                return $language;
            }
        }

        return $country?->language;
    }

    /**
     * Apply the resolved language, or the configured application fallback.
     *
     * @param  array{country: Country|null, language: Language|null, country_source: string, language_source: string}  $detection
     */
    public function applyLocale(array $detection): void
    {
        app()->setLocale($detection['language']?->code ?? config('app.locale', 'en'));
    }
}
