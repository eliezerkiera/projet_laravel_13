<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Locale Management Language Lines (English)
    |--------------------------------------------------------------------------
    */

    'preferences_updated' => 'Locale preferences updated successfully.',
    'preferences_reset' => 'Locale preferences reset to auto-detection.',

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */

    'validation' => [
        'fields_required' => 'At least one of country_id, language_id or reset_to_auto must be provided.',
        'reset_to_auto_conflict' => 'reset_to_auto cannot be combined with country_id or language_id.',
        'country_not_available' => 'The selected country is not available.',
    ],

];
