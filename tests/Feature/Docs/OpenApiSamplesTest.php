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
            'expires_in', 'refresh_expires_in', 'user' => ['id', 'first_name', 'last_name', 'email'],
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
            'expires_in', 'refresh_expires_in', 'user' => ['id', 'first_name', 'last_name', 'email'],
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
            'expires_in', 'refresh_expires_in', 'user' => ['id', 'first_name', 'last_name', 'email'],
        ])
        ->assertJsonPath('message', 'Token refreshed successfully.')
        ->assertJsonPath('expires_in', 86400);
});

test('locale and countries responses match the documented shapes', function () {
    $locale = $this->getJson('/api/v2/locale');
    $locale->assertOk()
        ->assertJsonStructure([
            'language' => ['id', 'code', 'name', 'source'],
            'country' => ['id', 'code', 'name', 'phone_code', 'is_active', 'source'],
            'country_selection_required',
            'preferences' => ['language_id', 'country_id'],
            'supported_languages',
            'countries',
        ]);

    $countries = $this->getJson('/api/v2/countries/active');
    $countries->assertOk()
        ->assertJsonStructure(['country_selection_required', 'countries']);
});

test('devices listing matches the documented shape', function () {
    $user = makeUser();
    $token = $user->createToken('Sample Device')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v2/auth/devices');

    $response->assertOk()->assertJsonStructure(['devices']);

    if ($devices = $response->json('devices')) {
        expect($devices[0])->toHaveKeys(['id', 'name', 'is_current', 'created_at']);
    }
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
