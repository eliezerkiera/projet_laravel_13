<?php

use App\Models\Country;
use App\Models\Language;
use App\Models\RefreshToken;
use App\Models\User;
use App\Models\VerificationCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->language = Language::first();
    $this->country = Country::first();

    $this->user = User::create([
        'first_name' => 'Frank',
        'last_name' => 'Sinatra',
        'email' => 'frank@example.com',
        'password' => Hash::make('Password123!'),
        'country_id' => $this->country->id,
        'language_id' => $this->language->id,
    ]);

    $this->token = $this->user->createToken('Test Device')->plainTextToken;
});

function assertErrorCode($response, string $code, int $status): void
{
    $response->assertStatus($status)
        ->assertJson(['code' => $code])
        ->assertJsonStructure(['code', 'message']);
}

test('unauthenticated returns code on protected routes', function () {
    assertErrorCode($this->getJson('/api/v2/auth/me'), 'unauthenticated', 401);
});

test('validation_failed is returned for generic form errors', function () {
    $response = $this->postJson('/api/v2/auth/register/send-code', []);

    assertErrorCode($response, 'validation_failed', 422);
    $response->assertJsonStructure(['errors' => ['email']]);
});

test('credentials_invalid is returned on wrong login credentials', function () {
    $response = $this->postJson('/api/v2/auth/login', [
        'email' => 'frank@example.com',
        'password' => 'WrongPassword',
    ]);

    assertErrorCode($response, 'credentials_invalid', 422);
    $response->assertJsonStructure(['errors' => ['email']]);
});

test('otp_invalid is returned when the submitted code is wrong', function () {
    VerificationCode::create([
        'email' => 'code@example.com',
        'code' => '123456',
        'type' => VerificationCode::TYPE_REGISTER,
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v2/auth/register/verify-code', [
        'email' => 'code@example.com',
        'code' => '654321',
    ]);

    assertErrorCode($response, 'otp_invalid', 422);
    $response->assertJsonStructure(['errors' => ['code']]);
});

test('otp_expired is returned when the code has expired', function () {
    VerificationCode::create([
        'email' => 'expired@example.com',
        'code' => '123456',
        'type' => VerificationCode::TYPE_REGISTER,
        'expires_at' => now()->subMinute(),
    ]);

    assertErrorCode(
        $this->postJson('/api/v2/auth/register/verify-code', [
            'email' => 'expired@example.com',
            'code' => '123456',
        ]),
        'otp_expired',
        422
    );
});

test('otp_max_attempts is returned once the attempt limit is reached', function () {
    VerificationCode::create([
        'email' => 'maxed@example.com',
        'code' => '123456',
        'type' => VerificationCode::TYPE_REGISTER,
        'attempts' => 5,
        'expires_at' => now()->addMinutes(10),
    ]);

    assertErrorCode(
        $this->postJson('/api/v2/auth/register/verify-code', [
            'email' => 'maxed@example.com',
            'code' => '123456',
        ]),
        'otp_max_attempts',
        422
    );
});

test('invalid_token is returned for invalid intermediate tokens on each flow', function () {
    assertErrorCode(
        $this->postJson('/api/v2/auth/register/complete', [
            'verification_token' => 'nope',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'country_id' => $this->country->id,
        ]),
        'invalid_token',
        422
    );

    assertErrorCode(
        $this->postJson('/api/v2/auth/login/verify-code', [
            'challenge_token' => 'nope',
            'code' => '123456',
        ]),
        'invalid_token',
        422
    );

    assertErrorCode(
        $this->postJson('/api/v2/auth/login/resend-code', ['challenge_token' => 'nope']),
        'invalid_token',
        422
    );

    assertErrorCode(
        $this->postJson('/api/v2/auth/forgot-password/reset', [
            'reset_token' => 'nope',
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]),
        'invalid_token',
        422
    );

    assertErrorCode(
        $this->postJson('/api/v2/auth/verify-pending-email', [
            'email' => 'nobody@example.com',
            'code' => '123456',
        ]),
        'invalid_token',
        422
    );
});

test('invalid_refresh_token and refresh_token_expired are returned by the refresh endpoint', function () {
    assertErrorCode(
        $this->postJson('/api/v2/auth/refresh', ['refresh_token' => 'garbage-token']),
        'invalid_refresh_token',
        422
    );

    RefreshToken::create([
        'user_id' => $this->user->id,
        'token' => $token = Str::random(64),
        'device_name' => 'Expired Device',
        'expires_at' => now()->subMinute(),
        'revoked' => false,
        'family_id' => (string) Str::uuid(),
    ]);

    $response = $this->postJson('/api/v2/auth/refresh', ['refresh_token' => $token]);
    assertErrorCode($response, 'refresh_token_expired', 422);

    expect(RefreshToken::where('token', $token)->first()->revoked)->toBeTrue();
});

test('password_current_incorrect is returned when the current password is wrong', function () {
    $change = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson('/api/v2/auth/password', [
            'current_password' => 'TotallyWrong',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

    assertErrorCode($change, 'password_current_incorrect', 422);
    $change->assertJsonStructure(['errors' => ['current_password']]);

    $delete = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/v2/auth/account', ['password' => 'TotallyWrong']);

    assertErrorCode($delete, 'password_current_incorrect', 422);
    $delete->assertJsonStructure(['errors' => ['password']]);
});

test('pending_email_verification_required is returned with the pending email on login', function () {
    $this->user->update(['pending_email' => 'frank.new@example.com']);

    $response = $this->postJson('/api/v2/auth/login', [
        'email' => 'frank@example.com',
        'password' => 'Password123!',
    ]);

    assertErrorCode($response, 'pending_email_verification_required', 403);
    $response->assertJson([
        'email' => 'frank@example.com',
        'pending_email' => 'frank.new@example.com',
    ]);
});

test('device_not_found is returned for unknown and foreign devices', function () {
    $foreignUser = User::factory()->create();
    $foreignDevice = RefreshToken::create([
        'user_id' => $foreignUser->id,
        'token' => Str::random(64),
        'device_name' => 'Foreign Device',
        'expires_at' => now()->addDays(30),
        'revoked' => false,
        'family_id' => (string) Str::uuid(),
    ]);

    $unknownResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/v2/auth/devices/'.(string) Str::uuid());
    assertErrorCode($unknownResponse, 'device_not_found', 404);

    $foreignResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/v2/auth/devices/'.$foreignDevice->family_id);
    assertErrorCode($foreignResponse, 'device_not_found', 404);
});

test('resource_not_found is returned for any other 404', function () {
    assertErrorCode($this->getJson('/api/v2/does-not-exist'), 'resource_not_found', 404);
});

test('too_many_requests is returned with a retry_after when throttled', function () {
    $email = 'ratelimit-codes@example.com';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v2/auth/login', [
            'email' => $email,
            'password' => 'WrongPassword',
        ])->assertUnprocessable();
    }

    $response = $this->postJson('/api/v2/auth/login', [
        'email' => $email,
        'password' => 'WrongPassword',
    ]);

    assertErrorCode($response, 'too_many_requests', 429);
    $response->assertHeader('Retry-After')
        ->assertJsonStructure(['retry_after']);
});
