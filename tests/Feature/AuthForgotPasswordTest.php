<?php

use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use App\Models\VerificationCode;
use App\Notifications\SendOtpNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->user = User::create([
        'first_name' => 'Bob',
        'last_name' => 'Taylor',
        'email' => 'bob@example.com',
        'password' => Hash::make('OldPassword123!'),
        'country_id' => Country::first()->id,
        'language_id' => Language::first()->id,
    ]);
});

test('it sends password reset OTP code to registered email', function () {
    Notification::fake();

    $response = $this->postJson('/api/v2/auth/forgot-password/send-code', [
        'email' => 'bob@example.com',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'email']);

    $this->assertDatabaseHas('verification_codes', [
        'email' => 'bob@example.com',
        'type' => VerificationCode::TYPE_PASSWORD_RESET,
    ]);

    Notification::assertSentOnDemand(SendOtpNotification::class);
});

test('it rejects forgot password request for non-existent email', function () {
    $response = $this->postJson('/api/v2/auth/forgot-password/send-code', [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('it verifies forgot password OTP code and returns reset_token', function () {
    VerificationCode::create([
        'user_id' => $this->user->id,
        'email' => 'bob@example.com',
        'code' => '445566',
        'type' => VerificationCode::TYPE_PASSWORD_RESET,
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v2/auth/forgot-password/verify-code', [
        'email' => 'bob@example.com',
        'code' => '445566',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'reset_token', 'email']);
});

test('it resets password using valid reset_token and revokes active tokens', function () {
    // Generate active session tokens for user
    $this->user->createToken('Mobile App');
    $this->user->createToken('Web Browser');
    expect($this->user->tokens()->count())->toBe(2);

    $resetToken = 'valid-password-reset-token';

    VerificationCode::create([
        'user_id' => $this->user->id,
        'email' => 'bob@example.com',
        'code' => '445566',
        'type' => VerificationCode::TYPE_PASSWORD_RESET,
        'token' => $resetToken,
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->postJson('/api/v2/auth/forgot-password/reset', [
        'token' => $resetToken,
        'password' => 'BrandNewPassword123!',
        'password_confirmation' => 'BrandNewPassword123!',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => __('auth.password_reset_success'),
        ]);

    $this->user->refresh();
    expect(Hash::check('BrandNewPassword123!', $this->user->password))->toBeTrue()
        ->and($this->user->tokens()->count())->toBe(0); // All tokens revoked
});
