<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\DeviceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * List all connected devices / sessions for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        // Get unique devices by family_id from refresh tokens
        $refreshTokens = $request->user()
            ->refreshTokens()
            ->where('revoked', false)
            ->get()
            ->groupBy('family_id');

        $devices = collect();
        foreach ($refreshTokens as $familyId => $tokens) {
            $latestToken = $tokens->sortByDesc('created_at')->first();
            $devices->push($latestToken);
        }

        return response()->json([
            'devices' => DeviceResource::collection($devices),
        ]);
    }

    /**
     * Disconnect a specific device / session by its family ID.
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        // Find refresh token by family_id
        $refreshToken = $request->user()
            ->refreshTokens()
            ->where('family_id', $id)
            ->where('revoked', false)
            ->first();

        if (! $refreshToken) {
            return response()->json([
                'code' => 'device_not_found',
                'message' => __('auth.device_not_found'),
            ], 404);
        }

        // Revoke the refresh token
        $refreshToken->revoke('device_revoked');

        // Revoke the access token for this device
        $request->user()
            ->tokens()
            ->where('name', $refreshToken->device_name)
            ->delete();

        // Check if this was the current device
        $currentAccessToken = $request->user()->currentAccessToken();
        $currentRefreshToken = $currentAccessToken ? $request->user()
            ->refreshTokens()
            ->where('device_name', $currentAccessToken->name)
            ->where('revoked', false)
            ->first() : null;

        $isCurrentDevice = $currentRefreshToken && $currentRefreshToken->family_id === $id;

        $response = [
            'message' => __('auth.device_disconnected'),
        ];

        if ($isCurrentDevice) {
            $response['current_device_revoked'] = true;
        }

        return response()->json($response);
    }

    /**
     * Disconnect all other devices / sessions except the current active one.
     */
    public function destroyOthers(Request $request): JsonResponse
    {
        $currentAccessToken = $request->user()->currentAccessToken();
        $currentDeviceName = $currentAccessToken?->name;

        // Find current refresh token
        $currentRefreshToken = $currentDeviceName ? $request->user()
            ->refreshTokens()
            ->where('device_name', $currentDeviceName)
            ->where('revoked', false)
            ->first() : null;

        $currentFamilyId = $currentRefreshToken?->family_id;

        // Revoke all refresh tokens except current
        $revokedCount = $request->user()
            ->refreshTokens()
            ->where('revoked', false)
            ->when($currentFamilyId, fn ($query) => $query->where('family_id', '!=', $currentFamilyId))
            ->update([
                'revoked' => true,
                'revoked_reason' => 'device_revoked',
                'revoked_at' => now(),
            ]);

        // Revoke all access tokens except current
        $request->user()
            ->tokens()
            ->when($currentAccessToken, fn ($query) => $query->where('id', '!=', $currentAccessToken->id))
            ->delete();

        return response()->json([
            'message' => __('auth.other_devices_disconnected'),
            'revoked_count' => $revokedCount,
        ]);
    }

    /**
     * Log out from the current device session by deleting its access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $currentAccessToken = $request->user()->currentAccessToken();
        $currentDeviceName = $currentAccessToken?->name;

        // Find and revoke current refresh token
        if ($currentDeviceName) {
            $request->user()
                ->refreshTokens()
                ->where('device_name', $currentDeviceName)
                ->where('revoked', false)
                ->update([
                    'revoked' => true,
                    'revoked_reason' => 'logout',
                    'revoked_at' => now(),
                ]);
        }

        $currentAccessToken?->delete();

        return response()->json([
            'message' => __('auth.logged_out'),
        ]);
    }
}
