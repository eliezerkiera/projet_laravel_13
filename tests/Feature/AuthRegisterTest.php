<?php

use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use App\Models\VerificationCode;
use App\Notifications\ExistingAccountNotification;
use App\Notifications\SendOtpNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('it can send an OTP code for registration', function () {
    Notification::fake();

    $response = $this->postJson('/api/v2/auth/register/send-code', [
        'email' => 'newuser@example.com',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'email']);

    $this->assertDatabaseHas('verification_codes', [
        'email' => 'newuser@example.com',
        'type' => VerificationCode::TYPE_REGISTER,
    ]);

    $code = VerificationCode::where('email', 'newuser@example.com')->first();
    expect(strlen($code->code))->toBe(6);

    Notification::assertSentOnDemand(SendOtpNotification::class, function ($notification, $channels, $notifiable) {
        return $notifiable->routes['mail'] === 'newuser@example.com';
    });
});

test('registration code endpoints do not disclose whether an account exists', function (string $endpoint) {
    config(['auth_api.rate_limits.otp_send.per_minute' => 10]);

    $email = 'existing@example.com';
    $existingUser = User::factory()->create(['email' => $email]);

    Notification::fake();

    $existingResponse = $this->postJson($endpoint, ['email' => $email]);
    $existingResponse->assertOk()
        ->assertJsonStructure(['message', 'email', 'expires_in', 'resend_available_in']);
    $this->assertDatabaseMissing('verification_codes', [
        'email' => $email,
        'type' => VerificationCode::TYPE_REGISTER,
    ]);

    $existingUser->forceDelete();

    $newResponse = $this->postJson($endpoint, ['email' => $email]);
    $newResponse->assertOk();

    expect($existingResponse->json())->toBe($newResponse->json());

    $this->assertDatabaseHas('verification_codes', [
        'email' => $email,
        'type' => VerificationCode::TYPE_REGISTER,
    ]);

    Notification::assertSentOnDemand(ExistingAccountNotification::class, function ($notification, $channels, $notifiable) use ($email) {
        return $notifiable->routes['mail'] === $email;
    });
    Notification::assertSentOnDemand(SendOtpNotification::class, function ($notification, $channels, $notifiable) use ($email) {
        return $notifiable->routes['mail'] === $email;
    });
})->with([
    'send-code' => '/api/v2/auth/register/send-code',
    'resend-code' => '/api/v2/auth/register/resend-code',
]);

test('registration code endpoints retain format validation', function (string $endpoint) {
    $this->postJson($endpoint, [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['email']);

    $this->postJson($endpoint, ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['email']);
})->with([
    'send-code' => '/api/v2/auth/register/send-code',
    'resend-code' => '/api/v2/auth/register/resend-code',
]);

test('registration completion returns invalid_token when the email is already taken', function () {
    $email = 'taken@example.com';
    User::factory()->create(['email' => $email]);

    VerificationCode::create([
        'email' => $email,
        'code' => '123456',
        'type' => VerificationCode::TYPE_REGISTER,
        'token' => 'verified-registration-token',
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->postJson('/api/v2/auth/register/complete', [
        'verification_token' => 'verified-registration-token',
        'first_name' => 'New',
        'last_name' => 'User',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_token')
        ->assertJsonPath('errors.verification_token.0', 'The verification token is invalid or has expired.');

    expect(User::where('email', $email)->count())->toBe(1);
});

test('it can verify registration OTP code and receive a verification token', function () {
    $codeRecord = VerificationCode::create([
        'email' => 'test@example.com',
        'code' => '123456',
        'type' => VerificationCode::TYPE_REGISTER,
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v2/auth/register/verify-code', [
        'email' => 'test@example.com',
        'code' => '123456',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'verification_token', 'email']);

    $codeRecord->refresh();
    expect($codeRecord->token)->not->toBeNull()
        ->and($codeRecord->verified_at)->not->toBeNull();
});

test('it fails verification when invalid code is submitted', function () {
    VerificationCode::create([
        'email' => 'test@example.com',
        'code' => '123456',
        'type' => VerificationCode::TYPE_REGISTER,
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v2/auth/register/verify-code', [
        'email' => 'test@example.com',
        'code' => '654321',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

test('it completes registration using the verification token', function () {
    $language = Language::first();
    $country = Country::first();

    $codeRecord = VerificationCode::create([
        'email' => 'complete@example.com',
        'code' => '123456',
        'type' => VerificationCode::TYPE_REGISTER,
        'token' => 'secure-verification-token-sample',
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->postJson('/api/v2/auth/register/complete', [
        'verification_token' => 'secure-verification-token-sample',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'country_id' => $country->id,
        'language_id' => $language->id,
        'device_name' => 'iPhone 15 Pro',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'access_token',
            'token_type',
            'user' => [
                'id',
                'first_name',
                'last_name',
                'full_name',
                'email',
                'language_id',
                'country_id',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'complete@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    // Code record must be consumed/deleted
    $this->assertDatabaseMissing('verification_codes', [
        'id' => $codeRecord->id,
    ]);
});
