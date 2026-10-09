<?php

namespace App\Services\Locale;

use App\Models\Country;
use App\Models\Language;

/**
 * Immutable context object representing the resolved locale and country.
 * This is the result of locale resolution and contains all information needed
 * for the application to know which language and country to use, and why.
 */
final readonly class LocaleContext
{
    /**
     * @param  Language|null  $language  The resolved language (null if none resolved)
     * @param  string  $languageSource  One of: override, profile, accept_language, country, default
     * @param  Country|null  $country  The resolved country (null if none resolved)
     * @param  string  $countrySource  One of: profile, single_active, none
     * @param  bool  $countrySelectionRequired  Whether country selection is required
     */
    public function __construct(
        public ?Language $language,
        public string $languageSource,
        public ?Country $country,
        public string $countrySource,
        public bool $countrySelectionRequired = false,
    ) {}

    /**
     * Create a new context from the resolution results.
     */
    public static function create(
        ?Language $language,
        string $languageSource,
        ?Country $country,
        string $countrySource,
        bool $countrySelectionRequired = false,
    ): self {
        return new self($language, $languageSource, $country, $countrySource, $countrySelectionRequired);
    }
}
