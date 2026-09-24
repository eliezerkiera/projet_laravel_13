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
        $tokens = $request->user()->tokens()->latest('created_at')->get();

        return response()->json([
            'devices' => DeviceResource::collection($tokens),
        ]);
    }

    /**
     * Disconnect a specific device / session by its token ID.
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $token = $request->user()->tokens()->where('id', $id)->first();

        if (! $token) {
            return response()->json([
                'message' => __('auth.device_not_found'),
            ], 404);
        }

        $token->delete();

        return response()->json([
            'message' => __('auth.device_disconnected'),
        ]);
    }

    /**
     * Disconnect all other devices / sessions except the current active one.
     */
    public function destroyOthers(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()?->id;

        $request->user()
            ->tokens()
            ->where('id', '!=', $currentTokenId)
            ->delete();

        return response()->json([
            'message' => __('auth.other_devices_disconnected'),
        ]);
    }

    /**
     * Log out from the current device session by deleting its access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => __('auth.logged_out'),
        ]);
    }
}
