<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\V2\UpdateLocaleRequest;
use App\Http\Resources\V2\UserResource;
use App\Models\Country;
use App\Models\Language;
use App\Services\Locale\LocaleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for locale management endpoints.
 * Handles both public GET /api/v2/locale and authenticated PATCH /api/v2/locale.
 */
class LocaleController extends Controller
{
    /**
     * Display the current locale context.
     * Public endpoint - works for both anonymous and authenticated users.
     */
    public function show(Request $request, LocaleResolver $resolver): JsonResponse
    {
        // Resolve the locale context (will use request attributes if already resolved by middleware)
        $context = $request->attributes->get('locale_context');

        if ($context === null) {
            // Fallback: resolve directly (shouldn't happen as middleware runs first)
            $context = $resolver->resolve($request);
        }

        // Get preferences from user if authenticated
        $user = $request->user('sanctum');

        $preferences = [
            'language_id' => $user?->language_id,
            'country_id' => $user?->country_id,
        ];

        return response()->json([
            'language' => $context->language ? [
                'id' => $context->language->id,
                'code' => $context->language->code,
                'name' => $context->language->name,
                'source' => $context->languageSource,
            ] : null,
            'country' => $context->country ? [
                'id' => $context->country->id,
                'code' => $context->country->code,
                'name' => $context->country->name,
                'native_name' => $context->country->native_name,
                'phone_code' => $context->country->phone_code,
                'is_active' => $context->country->is_active,
                'source' => $context->countrySource,
                'language' => $context->country->language ? [
                    'id' => $context->country->language->id,
                    'code' => $context->country->language->code,
                    'name' => $context->country->language->name,
                ] : null,
            ] : null,
            'country_selection_required' => $context->countrySelectionRequired,
            'preferences' => $preferences,
            'supported_languages' => array_map(function ($lang) {
                return [
                    'id' => $lang->id,
                    'code' => $lang->code,
                    'name' => $lang->name,
                ];
            }, $resolver->getSupportedLanguagesList()),
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
            }, $resolver->getActiveCountriesWithLanguage()),
        ]);
    }

    /**
     * Update the user's locale preferences.
     * Authenticated endpoint - requires Sanctum authentication.
     */
    public function update(UpdateLocaleRequest $request, LocaleResolver $resolver): JsonResponse
    {
        $user = $request->user();

        $updateData = [];
        $message = __('locale.preferences_updated');

        // Handle reset_to_auto
        if ($request->boolean('reset_to_auto')) {
            $updateData['language_id'] = null;
            $updateData['country_id'] = null;
            $message = __('locale.preferences_reset');
        } else {
            // Update language_id if provided (can be null to reset language only)
            if ($request->has('language_id')) {
                $updateData['language_id'] = $request->language_id;
            }

            // Update country_id if provided (can be null to reset country only)
            if ($request->has('country_id')) {
                $updateData['country_id'] = $request->country_id;
            }
        }

        // Perform single atomic update
        $user->update($updateData);
        $user->load(['language', 'country']);

        // Re-resolve the locale context with fresh user data
        // Note: X-Locale-Override header is still considered if present in request
        $context = $resolver->resolve($request);

        return response()->json([
            'message' => $message,
            'locale' => [
                'language' => $context->language ? [
                    'id' => $context->language->id,
                    'code' => $context->language->code,
                    'name' => $context->language->name,
                    'source' => $context->languageSource,
                ] : null,
                'country' => $context->country ? [
                    'id' => $context->country->id,
                    'code' => $context->country->code,
                    'name' => $context->country->name,
                    'native_name' => $context->country->native_name,
                    'phone_code' => $context->country->phone_code,
                    'is_active' => $context->country->is_active,
                    'source' => $context->countrySource,
                    'language' => $context->country->language ? [
                        'id' => $context->country->language->id,
                        'code' => $context->country->language->code,
                        'name' => $context->country->language->name,
                    ] : null,
                ] : null,
                'country_selection_required' => $context->countrySelectionRequired,
                'preferences' => [
                    'language_id' => $user->language_id,
                    'country_id' => $user->country_id,
                ],
                'supported_languages' => array_map(function ($lang) {
                    return [
                        'id' => $lang->id,
                        'code' => $lang->code,
                        'name' => $lang->name,
                    ];
                }, $resolver->getSupportedLanguagesList()),
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
                }, $resolver->getActiveCountriesWithLanguage()),
            ],
            'user' => new UserResource($user),
        ]);
    }
}
