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
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'email' => 'alice@example.com',
        'password' => Hash::make('Password123!'),
        'country_id' => Country::first()->id,
        'language_id' => Language::first()->id,
    ]);
});

test('it rejects login with invalid credentials', function () {
    $response = $this->postJson('/api/v2/auth/login', [
        'email' => 'alice@example.com',
        'password' => 'WrongPassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('it blocks login if a pending email verification is required', function () {
    $this->user->update(['pending_email' => 'alice.new@example.com']);

    $response = $this->postJson('/api/v2/auth/login', [
        'email' => 'alice@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'email' => 'alice@example.com',
            'pending_email' => 'alice.new@example.com',
        ]);
});

test('it validates credentials and sends 2FA OTP with challenge token', function () {
    Notification::fake();

    $response = $this->postJson('/api/v2/auth/login', [
        'email' => 'alice@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'challenge_token', 'email']);

    $this->assertDatabaseHas('verification_codes', [
        'user_id' => $this->user->id,
        'email' => 'alice@example.com',
        'type' => VerificationCode::TYPE_LOGIN,
    ]);

    Notification::assertSentOnDemand(SendOtpNotification::class, function ($notification, $channels, $notifiable) {
        return $notifiable->routes['mail'] === 'alice@example.com';
    });
});

test('it can verify 2FA OTP code and generate Sanctum access token', function () {
    $challengeToken = 'sample-login-challenge-token';

    VerificationCode::create([
        'user_id' => $this->user->id,
        'email' => 'alice@example.com',
        'code' => '998877',
        'type' => VerificationCode::TYPE_LOGIN,
        'token' => $challengeToken,
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v2/auth/login/verify-code', [
        'challenge_token' => $challengeToken,
        'code' => '998877',
        'device_name' => 'MacBook Pro M3',
    ]);

    $response->assertOk()
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
            ],
        ]);

    expect($this->user->tokens()->count())->toBe(1);
});

test('it rejects 2FA verification with incorrect code', function () {
    $challengeToken = 'sample-login-challenge-token-invalid';

    VerificationCode::create([
        'user_id' => $this->user->id,
        'email' => 'alice@example.com',
        'code' => '998877',
        'type' => VerificationCode::TYPE_LOGIN,
        'token' => $challengeToken,
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v2/auth/login/verify-code', [
        'challenge_token' => $challengeToken,
        'code' => '000000',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});
