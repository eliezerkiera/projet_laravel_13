<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\V2\UpdateLocaleRequest;
use App\Http\Resources\V2\UserResource;
use App\Models\Country;
use App\Models\Language;
use Illuminate\Http\JsonResponse;

class LocaleController extends Controller
{
    /**
     * Update the user's locale preferences (country and/or language).
     */
    public function update(UpdateLocaleRequest $request): JsonResponse
    {
        $user = $request->user();

        // Handle reset to auto
        if ($request->boolean('reset_to_auto')) {
            $user->update([
                'country_source' => 'auto',
                'language_source' => 'auto',
            ]);

            $user->load(['language', 'country']);

            return response()->json([
                'message' => 'Locale preferences reset to auto-detection.',
                'user' => new UserResource($user),
            ]);
        }

        // Update country if provided
        if ($request->filled('country_id')) {
            $country = Country::findOrFail($request->country_id);
            $user->update([
                'country_id' => $country->id,
                'country_source' => 'manual',
            ]);
        }

        // Update language if provided
        if ($request->filled('language_id')) {
            $language = Language::findOrFail($request->language_id);
            $user->update([
                'language_id' => $language->id,
                'language_source' => 'manual',
            ]);
        }

        $user->load(['language', 'country']);

        // Check if the selected country is inactive
        $countryInactive = false;
        if ($user->country && ! $user->country->is_active) {
            $countryInactive = true;
        }

        $response = [
            'message' => 'Locale preferences updated successfully.',
            'user' => new UserResource($user),
        ];

        if ($countryInactive) {
            $response['warning'] = 'The selected country is currently inactive. Some features may be limited.';
        }

        return response()->json($response);
    }
}
