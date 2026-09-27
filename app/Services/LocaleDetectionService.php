<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stevebauman\Location\Facades\Location;

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

        // Priority 1: X-Locale-Override header (always highest priority)
        $override = $request->header('X-Locale-Override');
        if ($override) {
            return $this->handleOverride($override);
        }

        // Priority 2: Authenticated user with manual preferences
        if ($user) {
            $result = $this->handleAuthenticatedUser($user);
            if ($result) {
                return $result;
            }
        }

        // Priority 3: Auto-detection for guest or user without preferences
        return $this->handleAutoDetection($request, $user);
    }

    /**
     * Handle X-Locale-Override header.
     *
     * @return array{country: Country|null, language: Language|null, country_source: string, language_source: string}
     */
    protected function handleOverride(string $override): array
    {
        $language = Language::where('code', $override)->first();
        $country = Country::getDefault(); // Use default country for override

        return [
            'country' => $country,
            'language' => $language,
            'country_source' => 'override',
            'language_source' => 'override',
        ];
    }

    /**
     * Handle authenticated user with existing preferences.
     *
     * @return array{country: Country|null, language: Language|null, country_source: string, language_source: string}|null
     */
    protected function handleAuthenticatedUser(User $user): ?array
    {
        $result = [
            'country' => null,
            'language' => null,
            'country_source' => 'auto',
            'language_source' => 'auto',
        ];

        // Check manual country preference
        if ($user->country_id && $user->isCountryManual()) {
            $result['country'] = $user->country;
            $result['country_source'] = 'manual';
        }

        // Check manual language preference
        if ($user->language_id && $user->isLanguageManual()) {
            $result['language'] = $user->language;
            $result['language_source'] = 'manual';
        }

        // If at least one manual preference exists, don't auto-detect
        if ($result['country_source'] === 'manual' || $result['language_source'] === 'manual') {
            // Fill missing values with existing auto values if they exist
            if (! $result['country'] && $user->country_id) {
                $result['country'] = $user->country;
                $result['country_source'] = 'auto';
            }
            if (! $result['language'] && $user->language_id) {
                $result['language'] = $user->language;
                $result['language_source'] = 'auto';
            }

            return $result;
        }

        // If user has existing auto values, use them (don't re-detect)
        if ($user->country_id || $user->language_id) {
            if ($user->country_id) {
                $result['country'] = $user->country;
            }
            if ($user->language_id) {
                $result['language'] = $user->language;
            }

            return $result;
        }

        return null; // Proceed to auto-detection
    }

    /**
     * Handle auto-detection for guests or users without preferences.
     *
     * @return array{country: Country|null, language: Language|null, country_source: string, language_source: string}
     */
    protected function handleAutoDetection(Request $request, ?User $user): array
    {
        $result = [
            'country' => null,
            'language' => null,
            'country_source' => 'auto',
            'language_source' => 'auto',
        ];

        // Step 3a: Detect country by IP
        $country = $this->detectCountryByIp($request);
        $result['country'] = $country;

        // Step 3b: Detect language
        $language = $this->detectLanguage($request, $country);
        $result['language'] = $language;

        // Persist for authenticated user
        if ($user && $country) {
            $user->update([
                'country_id' => $country->id,
                'country_source' => 'auto',
            ]);
        }
        if ($user && $language) {
            $user->update([
                'language_id' => $language->id,
                'language_source' => 'auto',
            ]);
        }

        return $result;
    }

    /**
     * Detect country by IP address.
     */
    protected function detectCountryByIp(Request $request): ?Country
    {
        $ip = $this->getClientIp($request);

        // Try to use Location package if available
        if (class_exists('\Stevebauman\Location\Facades\Location')) {
            try {
                $location = Location::get($ip);

                if ($location && $location->countryCode) {
                    $country = Country::where('code', $location->countryCode)->first();

                    if ($country) {
                        // Check if country is active
                        if (! $country->is_active) {
                            // Fallback to default country but keep the detected country info
                            return Country::getDefault();
                        }

                        return $country;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('IP geolocation failed', ['ip' => $ip, 'error' => $e->getMessage()]);
            }
        }

        // Fallback to default country
        return Country::getDefault();
    }

    /**
     * Detect language based on Accept-Language header and country.
     */
    protected function detectLanguage(Request $request, ?Country $country): ?Language
    {
        // Try to match Accept-Language header
        $header = $request->header('Accept-Language');
        if ($header) {
            $preferred = $request->getPreferredLanguage(['fr', 'en']);
            if ($preferred) {
                $language = Language::where('code', $preferred)->first();
                if ($language) {
                    return $language;
                }
            }
        }

        // Fallback to country's default language
        if ($country && $country->language) {
            return $country->language;
        }

        // Final fallback to app default language
        return Language::getDefault();
    }

    /**
     * Get client IP address, respecting test IP from env.
     */
    protected function getClientIp(Request $request): string
    {
        // Allow overriding IP for testing
        if ($testIp = env('TEST_IP')) {
            return $testIp;
        }

        return $request->ip();
    }

    /**
     * Apply detected locale to the application.
     */
    public function applyLocale(array $detection): void
    {
        if ($detection['language']) {
            app()->setLocale($detection['language']->code);
        } else {
            app()->setLocale(config('app.locale', 'en'));
        }
    }
}
