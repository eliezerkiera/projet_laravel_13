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

test('it returns English message by default', function () {
    $response = $this->postJson('/api/v2/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'secret',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'These credentials do not match our records.',
        ]);
});

test('it returns French message when Accept-Language is set to fr', function () {
    $response = $this->withHeader('Accept-Language', 'fr')
        ->postJson('/api/v2/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'secret',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
        ]);
});

test('it translates every business message with X-Locale-Override fr', function () {
    // unauthenticated (401) must be checked first: once a valid Sanctum token
    // authenticates this test, the guard keeps the user for later requests.
    $this->withHeader('X-Locale-Override', 'fr')
        ->withHeader('Authorization', 'Bearer invalid-token')
        ->getJson('/api/v2/auth/me')
        ->assertStatus(401)
        ->assertHeader('Content-Language', 'fr')
        ->assertJson([
            'code' => 'unauthenticated',
            'message' => 'Non authentifié.',
        ]);

    $user = User::create([
        'first_name' => 'Lena',
        'last_name' => 'Doe',
        'email' => 'lena@example.com',
        'password' => Hash::make('Secret123!'),
        'country_id' => Country::first()->id,
        'language_id' => Language::first()->id,
    ]);
    $token = $user->createToken('iPhone 15')->plainTextToken;

    // credentials_invalid (422)
    $this->withHeader('X-Locale-Override', 'fr')
        ->postJson('/api/v2/auth/login', [
            'email' => 'lena@example.com',
            'password' => 'wrong-password',
        ])
        ->assertStatus(422)
        ->assertHeader('Content-Language', 'fr')
        ->assertJson([
            'code' => 'credentials_invalid',
            'message' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
        ]);

    // otp_invalid (422)
    VerificationCode::create([
        'email' => 'invalid-code@example.com',
        'code' => '111111',
        'type' => VerificationCode::TYPE_REGISTER,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->withHeader('X-Locale-Override', 'fr')
        ->postJson('/api/v2/auth/register/verify-code', [
            'email' => 'invalid-code@example.com',
            'code' => '222222',
        ])
        ->assertStatus(422)
        ->assertHeader('Content-Language', 'fr')
        ->assertJson([
            'code' => 'otp_invalid',
            'message' => 'Le code de vérification est invalide.',
        ]);

    // device_not_found (404)
    $this->withHeader('X-Locale-Override', 'fr')
        ->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/v2/auth/devices/'.(string) Str::uuid())
        ->assertStatus(404)
        ->assertHeader('Content-Language', 'fr')
        ->assertJson([
            'code' => 'device_not_found',
            'message' => "Session d'appareil introuvable.",
        ]);
});

test('it translates the rate limited response with X-Locale-Override fr', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->withHeader('X-Locale-Override', 'fr')
            ->postJson('/api/v2/auth/login', [
                'email' => 'limited@example.com',
                'password' => 'wrong-password',
            ]);
    }

    $this->withHeader('X-Locale-Override', 'fr')
        ->postJson('/api/v2/auth/login', [
            'email' => 'limited@example.com',
            'password' => 'wrong-password',
        ])
        ->assertStatus(429)
        ->assertHeader('Content-Language', 'fr')
        ->assertJson([
            'code' => 'too_many_requests',
            'message' => 'Trop de tentatives. Veuillez réessayer plus tard.',
        ]);
});

test('it keeps the error code identical between languages', function () {
    VerificationCode::create([
        'email' => 'same-code@example.com',
        'code' => '111111',
        'type' => VerificationCode::TYPE_REGISTER,
        'expires_at' => now()->addMinutes(10),
    ]);

    $inEnglish = $this->withHeader('X-Locale-Override', 'en')
        ->postJson('/api/v2/auth/register/verify-code', [
            'email' => 'same-code@example.com',
            'code' => '222222',
        ])
        ->assertStatus(422)
        ->assertHeader('Content-Language', 'en');

    $inFrench = $this->withHeader('X-Locale-Override', 'fr')
        ->postJson('/api/v2/auth/register/verify-code', [
            'email' => 'same-code@example.com',
            'code' => '222222',
        ])
        ->assertStatus(422)
        ->assertHeader('Content-Language', 'fr');

    expect($inEnglish->json('code'))->toBe($inFrench->json('code'))
        ->and($inEnglish->json('code'))->toBe('otp_invalid')
        ->and($inEnglish->json('message'))->not->toBe($inFrench->json('message'));
});
