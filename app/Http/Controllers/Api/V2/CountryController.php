<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    /**
     * List countries available for manual selection.
     */
    public function index(): JsonResponse
    {
        $countries = Country::query()
            ->where('is_active', true)
            ->with('language')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'country_selection_required' => $countries->count() > 1,
            'countries' => $countries->map(fn (Country $country): array => [
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
            ])->values(),
        ]);
    }
}
