<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'pending_email' => $this->pending_email,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'language_id' => $this->language_id,
            'language' => $this->whenLoaded('language', function () {
                return $this->language ? [
                    'id' => $this->language->id,
                    'code' => $this->language->code,
                    'name' => $this->language->name,
                ] : null;
            }),
            'country_id' => $this->country_id,
            'country' => $this->whenLoaded('country', function () {
                return $this->country ? [
                    'id' => $this->country->id,
                    'code' => $this->country->code,
                    'name' => $this->country->name,
                    'phone_code' => $this->country->phone_code,
                ] : null;
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
