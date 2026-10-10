<?php

use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use App\Models\VerificationCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function makeUser(array $overrides = []): User
{
    $country = Country::first();
    $language = Language::first();

    return User::create(array_merge([
        'first_name' => 'Sample',
        'last_name' => 'User',
        'email' => 'sample-'.Str::random(6).'@example.com',
        'password' => Hash::make('Password123!'),
        'country_id' => $country?->id,
        'language_id' => $language?->id,
    ], $overrides));
}

function loginChallenge(User $user, string $code = '123456'): string
{
    $token = Str::random(64);

    VerificationCode::create([
        'user_id' => $user->id,
        'email' => $user->email,
        'code' => $code,
        'type' => VerificationCode::TYPE_LOGIN,
        'token' => $token,
        'attempts' => 0,
        'expires_at' => now()->addMinutes(10),
    ]);

    return $token;
}

test('register flow responses match the documented shapes', function () {
    $email = 'sample-register@example.com';

    $send = $this->postJson('/api/v2/auth/register/send-code', ['email' => $email]);
    $send->assertOk()
        ->assertJsonStructure(['message', 'email', 'expires_in', 'resend_available_in'])
        ->assertJsonPath('message', 'A verification code has been sent to your email address.');

    $verification = VerificationCode::query()
        ->where('email', $email)
        ->where('type', VerificationCode::TYPE_REGISTER)
        ->firstOrFail();

    $verify = $this->postJson('/api/v2/auth/register/verify-code', [
        'email' => $email,
        'code' => $verification->code,
    ]);
    $verify->assertOk()
        ->assertJsonStructure(['message', 'verification_token', 'email', 'expires_in']);

    $complete = $this->postJson('/api/v2/auth/register/complete', [
        'verification_token' => $verify->json('verification_token'),
        'first_name' => 'Sample',
        'last_name' => 'Register',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $complete->assertCreated()
        ->assertJsonStructure([
            'message', 'access_token', 'refresh_token', 'token_type',
            'expires_in', 'refresh_expires_in', 'user' => ['id', 'first_name', 'last_name', 'full_name', 'email', 'pending_email', 'email_verified_at', 'language_id', 'language', 'country_id', 'country', 'created_at', 'updated_at'],
        ])
        ->assertJsonPath('message', 'Your account has been registered successfully.')
        ->assertJsonPath('expires_in', 86400)
        ->assertJsonPath('refresh_expires_in', 2592000);
});

test('login and refresh flow responses match the documented shapes', function () {
    $user = makeUser(['email' => 'sample-login@example.com']);

    $login = $this->postJson('/api/v2/auth/login', [
        'email' => $user->email,
        'password' => 'Password123!',
    ]);
    $login->assertOk()
        ->assertJsonStructure(['message', 'challenge_token', 'email', 'expires_in', 'resend_available_in'])
        ->assertJsonPath('message', 'Credentials verified. Please enter the verification code sent to your email.');

    $challenge = loginChallenge($user);

    $verify = $this->postJson('/api/v2/auth/login/verify-code', [
        'challenge_token' => $challenge,
        'code' => '123456',
        'device_name' => 'Sample Device',
    ]);
    $tokens = $verify->assertOk()
        ->assertJsonStructure([
            'message', 'access_token', 'refresh_token', 'token_type',
            'expires_in', 'refresh_expires_in', 'user' => ['id', 'first_name', 'last_name', 'full_name', 'email', 'pending_email', 'email_verified_at', 'language_id', 'language', 'country_id', 'country', 'created_at', 'updated_at'],
        ])
        ->assertJsonPath('message', 'Logged in successfully.')
        ->assertJsonPath('expires_in', 86400)
        ->json();

    $refresh = $this->postJson('/api/v2/auth/refresh', [
        'refresh_token' => $tokens['refresh_token'],
    ]);
    $refresh->assertOk()
        ->assertJsonStructure([
            'message', 'access_token', 'refresh_token', 'token_type',
            'expires_in', 'refresh_expires_in', 'user' => ['id', 'first_name', 'last_name', 'full_name', 'email', 'pending_email', 'email_verified_at', 'language_id', 'language', 'country_id', 'country', 'created_at', 'updated_at'],
        ])
        ->assertJsonPath('message', 'Token refreshed successfully.')
        ->assertJsonPath('expires_in', 86400);
});

test('locale and countries responses match the documented shapes', function () {
    $locale = $this->getJson('/api/v2/locale');
    $locale->assertOk()
        ->assertJsonStructure([
            'language' => ['id', 'code', 'name', 'source'],
            'country' => ['id', 'code', 'name', 'native_name', 'phone_code', 'is_active', 'source', 'language'],
            'country_selection_required',
            'preferences' => ['language_id', 'country_id'],
            'supported_languages' => [['id', 'code', 'name']],
            'countries' => [['id', 'code', 'name', 'native_name', 'phone_code', 'language']],
        ]);

    // Anonymous with a single active country: the country is inherited.
    $locale->assertJsonPath('country.code', 'BF')
        ->assertJsonPath('country.source', 'single_active')
        ->assertJsonPath('country_selection_required', false)
        ->assertJsonPath('preferences.language_id', null)
        ->assertJsonPath('preferences.country_id', null);

    // Only supported languages are listed (Arabic removed).
    expect(collect($locale->json('supported_languages'))->pluck('code')->all())
        ->toBe(['fr', 'en']);

    $countries = $this->getJson('/api/v2/countries/active');
    $countries->assertOk()
        ->assertJsonStructure(['country_selection_required', 'countries']);
});

test('anonymous locale example with multiple active countries requires a selection', function () {
    Country::where('code', 'SN')->update(['is_active' => true]);

    $response = $this->getJson('/api/v2/locale');

    $response->assertOk()
        ->assertJsonPath('country', null)
        ->assertJsonPath('country_selection_required', true)
        ->assertJsonPath('preferences.country_id', null);

    expect($response->json('language'))->not->toBeNull();
});

test('authenticated locale example resolves the profile preferences', function () {
    $user = makeUser();
    $token = $user->createToken('Sample Device')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v2/locale');

    $response->assertOk()
        ->assertJsonPath('language.source', 'profile')
        ->assertJsonPath('country.source', 'profile')
        ->assertJsonPath('country_selection_required', false)
        ->assertJsonPath('preferences.language_id', $user->language_id)
        ->assertJsonPath('preferences.country_id', $user->country_id);
});

test('authenticated locale example with an inactive profile country requires a selection', function () {
    $inactive = Country::where('is_active', false)->firstOrFail();
    $user = makeUser(['country_id' => $inactive->id]);
    $token = $user->createToken('Sample Device')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v2/locale');

    $response->assertOk()
        ->assertJsonPath('country.id', $inactive->id)
        ->assertJsonPath('country.is_active', false)
        ->assertJsonPath('country.source', 'profile')
        ->assertJsonPath('country_selection_required', true)
        ->assertJsonPath('preferences.country_id', $inactive->id);
});

test('locale preferences update matches the documented shape', function () {
    $user = makeUser();
    $country = Country::where('is_active', true)->firstOrFail();
    $language = Language::where('code', 'fr')->firstOrFail();
    $token = $user->createToken('Sample Device')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v2/locale', [
            'language_id' => $language->id,
            'country_id' => $country->id,
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'locale' => ['language', 'country', 'country_selection_required', 'preferences', 'supported_languages', 'countries'],
            'user' => ['id', 'full_name', 'language_id', 'language', 'country_id', 'country'],
        ])
        ->assertJsonPath('locale.language.source', 'profile')
        ->assertJsonPath('locale.country.id', $country->id)
        ->assertJsonPath('locale.preferences.language_id', $language->id)
        ->assertJsonPath('locale.preferences.country_id', $country->id)
        ->assertJsonPath('user.id', $user->id);
});

test('password change matches the documented shape', function () {
    $user = makeUser();
    $token = $user->createToken('Sample Device')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson('/api/v2/auth/password', [
            'current_password' => 'Password123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'other_sessions_revoked'])
        ->assertJsonPath('other_sessions_revoked', 0);
});

test('devices listing matches the documented shape', function () {
    $user = makeUser();
    $token = $user->createToken('Sample Device')->plainTextToken;
    $user->refreshTokens()->create([
        'token' => Str::random(64),
        'device_name' => 'Sample Device',
        'expires_at' => now()->addDays(30),
        'revoked' => false,
        'family_id' => (string) Str::uuid(),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v2/auth/devices');

    $response->assertOk()
        ->assertJsonStructure(['devices' => [['id', 'name', 'is_current', 'last_used_at', 'created_at']]]);
});

test('disconnecting other devices matches the documented shape', function () {
    $user = makeUser();
    $token = $user->createToken('Current Device')->plainTextToken;
    $user->tokens()->create([
        'name' => 'Other Device',
        'token' => hash('sha256', Str::random(40)),
        'abilities' => ['*'],
    ]);
    $user->refreshTokens()->create([
        'token' => Str::random(64),
        'device_name' => 'Other Device',
        'expires_at' => now()->addDays(30),
        'revoked' => false,
        'family_id' => (string) Str::uuid(),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/v2/auth/devices');

    $response->assertOk()
        ->assertJsonStructure(['message', 'revoked_count'])
        ->assertJsonPath('revoked_count', 1);
});

test('disconnecting a device by id matches the documented shape', function () {
    $user = makeUser();
    $token = $user->createToken('Current Device')->plainTextToken;
    $familyId = (string) Str::uuid();
    $refreshToken = $user->refreshTokens()->create([
        'token' => Str::random(64),
        'device_name' => 'Other Device',
        'expires_at' => now()->addDays(30),
        'revoked' => false,
        'family_id' => $familyId,
    ]);
    $user->tokens()->create([
        'name' => $refreshToken->device_name,
        'token' => hash('sha256', Str::random(40)),
        'abilities' => ['*'],
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/v2/auth/devices/'.$familyId);

    $response->assertOk()
        ->assertJsonStructure(['message'])
        ->assertJsonMissingPath('current_device_revoked');
});

test('disconnecting the current device by id flags the revoked current session', function () {
    $user = makeUser();
    $token = $user->createToken('Current Device')->plainTextToken;
    $familyId = (string) Str::uuid();
    $user->refreshTokens()->create([
        'token' => Str::random(64),
        'device_name' => 'Current Device',
        'expires_at' => now()->addDays(30),
        'revoked' => false,
        'family_id' => $familyId,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/v2/auth/devices/'.$familyId);

    $response->assertOk()
        ->assertJsonStructure(['message', 'current_device_revoked'])
        ->assertJsonPath('current_device_revoked', true);
});

test('error responses match the documented shapes', function () {
    $this->getJson('/api/v2/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated')
        ->assertJsonStructure(['code', 'message']);

    $validation = $this->postJson('/api/v2/auth/register/send-code', []);
    $validation->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonStructure(['code', 'message', 'errors' => ['email']]);

    $otp = $this->postJson('/api/v2/auth/register/verify-code', [
        'email' => 'none@example.com',
        'code' => '123456',
    ]);
    $otp->assertUnprocessable()
        ->assertJsonPath('code', 'otp_invalid')
        ->assertJsonPath('message', 'The verification code is invalid.')
        ->assertJsonStructure(['code', 'message', 'errors' => ['code']]);

    $credentials = $this->postJson('/api/v2/auth/login', [
        'email' => 'unknown@example.com',
        'password' => 'WrongPassword',
    ]);
    $credentials->assertUnprocessable()
        ->assertJsonPath('code', 'credentials_invalid')
        ->assertJsonPath('message', 'These credentials do not match our records.')
        ->assertJsonStructure(['code', 'message', 'errors' => ['email']]);

    $email = 'throttle@example.com';
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v2/auth/login', [
            'email' => $email,
            'password' => 'WrongPassword',
        ]);
    }

    $throttled = $this->postJson('/api/v2/auth/login', [
        'email' => $email,
        'password' => 'WrongPassword',
    ]);
    $throttled->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('code', 'too_many_requests')
        ->assertJsonStructure(['code', 'message', 'retry_after']);
});
