<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\Country;
use App\Models\Language;
use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleAuthService
{
    /**
     * Handle Google authentication - find or create user and link social account.
     *
     * @param  SocialiteUser  $socialiteUser  The user data from Google
     * @return User The authenticated user
     *
     * @throws ApiException
     */
    public function handleAuth(SocialiteUser $socialiteUser): User
    {
        $provider = 'google';
        $providerId = $socialiteUser->getId();
        $providerEmail = $socialiteUser->getEmail();
        $emailVerified = $socialiteUser->user['email_verified'] ?? false;

        // Use DB transaction for atomic operations
        return \DB::transaction(function () use ($provider, $providerId, $providerEmail, $emailVerified, $socialiteUser) {
            // Step 1: Check if social account already exists
            $socialAccount = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_id', $providerId)
                ->first();

            if ($socialAccount) {
                // Return existing user
                return $socialAccount->user;
            }

            // Step 2: Check if user exists with the same email
            $existingUser = User::query()
                ->where('email', $providerEmail)
                ->first();

            if ($existingUser) {
                // Link the Google account to existing user only if email is verified
                if (! $emailVerified) {
                    throw ApiException::forField(
                        'google_email_not_verified',
                        'email',
                        'auth.google_email_not_verified'
                    );
                }

                SocialAccount::create([
                    'user_id' => $existingUser->id,
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'provider_email' => $providerEmail,
                ]);

                return $existingUser;
            }

            // Step 3: Create new user
            $name = $socialiteUser->getName();
            $nameParts = $this->splitName($name);

            // Get default language and country
            $defaultLanguage = Language::getDefault();
            $defaultCountry = Country::getDefault();

            $user = User::create([
                'first_name' => $nameParts['first_name'],
                'last_name' => $nameParts['last_name'],
                'email' => $providerEmail,
                'password' => null, // No password for Google auth users
                'email_verified_at' => $emailVerified ? now() : null,
                'language_id' => $defaultLanguage?->id,
                'country_id' => $defaultCountry?->id,
            ]);

            // Link the social account
            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $providerId,
                'provider_email' => $providerEmail,
            ]);

            return $user;
        });
    }

    /**
     * Split full name into first and last name.
     *
     * @return array<string, string>
     */
    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);

        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
        ];
    }
}
