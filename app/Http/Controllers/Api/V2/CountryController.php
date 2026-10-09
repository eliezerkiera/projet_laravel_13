<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Locale\LocaleResolver;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    /**
     * List countries available for manual selection.
     */
    public function index(LocaleResolver $resolver): JsonResponse
    {
        $countries = $resolver->getActiveCountriesWithLanguage();
        $countrySelectionRequired = count($countries) > 1;

        return response()->json([
            'country_selection_required' => $countrySelectionRequired,
            'countries' => array_map(function ($country) {
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
            }, $countries),
        ]);
    }
}
