<?php

use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use App\Models\VerificationCode;
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

test('it rejects registration code request if email already exists', function () {
    $language = Language::first();
    $country = Country::first();

    User::create([
        'first_name' => 'Existing',
        'last_name' => 'User',
        'email' => 'existing@example.com',
        'password' => 'secret123',
        'language_id' => $language->id,
        'country_id' => $country->id,
    ]);

    $response = $this->postJson('/api/v2/auth/register/send-code', [
        'email' => 'existing@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
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
