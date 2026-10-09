<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Find the current access token to determine if this is the current device
        $currentAccessToken = $request->user()?->currentAccessToken();
        $currentFamilyId = null;

        if ($currentAccessToken) {
            // Find the refresh token for the current access token by device name
            $currentRefreshToken = $request->user()
                ->refreshTokens()
                ->where('device_name', $currentAccessToken->name)
                ->where('revoked', false)
                ->first();
            $currentFamilyId = $currentRefreshToken?->family_id;
        }

        return [
            'id' => $this->family_id ?? $this->id,
            'name' => $this->device_name,
            'is_current' => $currentFamilyId === $this->family_id,
            'last_used_at' => $this->updated_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
