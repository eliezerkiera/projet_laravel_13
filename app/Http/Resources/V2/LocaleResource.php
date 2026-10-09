<?php

namespace App\Http\Resources\V2;

use App\Models\Country;
use App\Models\Language;
use App\Services\Locale\LocaleContext;
use App\Services\Locale\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for locale context information.
 * Used by both GET /api/v2/locale and PATCH /api/v2/locale responses.
 */
class LocaleResource extends JsonResource
{
    /**
     * The locale context being transformed.
     */
    protected LocaleContext $localeContext;

    /**
     * Create a new resource instance.
     */
    public function __construct(LocaleContext $localeContext)
    {
        parent::__construct($localeContext);
        $this->localeContext = $localeContext;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $context = $this->localeContext;

        return [
            'language' => $this->formatLanguage($context->language, $context->languageSource),
            'country' => $this->formatCountry($context->country, $context->countrySource),
            'country_selection_required' => $context->countrySelectionRequired,
            'preferences' => [
                'language_id' => $request->user('sanctum')?->language_id,
                'country_id' => $request->user('sanctum')?->country_id,
            ],
            'supported_languages' => $this->formatSupportedLanguages(),
            'countries' => $this->formatCountries(),
        ];
    }

    /**
     * Format the language with its source.
     */
    protected function formatLanguage(?Language $language, string $source): ?array
    {
        if ($language === null) {
            return null;
        }

        return [
            'id' => $language->id,
            'code' => $language->code,
            'name' => $language->name,
            'source' => $source,
        ];
    }

    /**
     * Format the country with its source.
     */
    protected function formatCountry(?Country $country, string $source): ?array
    {
        if ($country === null) {
            return null;
        }

        return [
            'id' => $country->id,
            'code' => $country->code,
            'name' => $country->name,
            'native_name' => $country->native_name,
            'phone_code' => $country->phone_code,
            'is_active' => $country->is_active,
            'source' => $source,
            'language' => $country->language ? [
                'id' => $country->language->id,
                'code' => $country->language->code,
                'name' => $country->language->name,
            ] : null,
        ];
    }

    /**
     * Format supported languages list.
     */
    protected function formatSupportedLanguages(): array
    {
        $resolver = app(LocaleResolver::class);
        $languages = $resolver->getSupportedLanguagesList();

        return array_map(function ($language) {
            return [
                'id' => $language->id,
                'code' => $language->code,
                'name' => $language->name,
            ];
        }, $languages);
    }

    /**
     * Format active countries list.
     */
    protected function formatCountries(): array
    {
        $resolver = app(LocaleResolver::class);
        $countries = $resolver->getActiveCountriesWithLanguage();

        return array_map(function ($country) {
            return [
                'id' => $country->id,
                'code' => $country->code,
                'name' => $country->name,
                'native_name' => $country->native_name,
                'phone_code' => $country->phone_code,
                'language' => $country->language ? [
                    'id' => $country->language->id,
                    'code' => $country->language->code,
                    'name' => $country->language->name,
                ] : null,
            ];
        }, $countries);
    }
}
