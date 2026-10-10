<?php

use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use App\Models\VerificationCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->user = User::create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.com',
        'password' => Hash::make('Password123!'),
        'country_id' => Country::first()->id,
        'language_id' => Language::first()->id,
    ]);
});

test('the access token expires_in reflects the real token expiration', function () {
    $this->postJson('/api/v2/auth/login', [
        'email' => 'grace@example.com',
        'password' => 'Password123!',
    ])->assertOk();

    $challenge = VerificationCode::where('type', VerificationCode::TYPE_LOGIN)->latest('id')->first();

    $response = $this->postJson('/api/v2/auth/login/verify-code', [
        'challenge_token' => $challenge->token,
        'code' => $challenge->code,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'expires_in']);

    $expiresIn = $response->json('expires_in');
    $token = $this->user->tokens()->latest()->first();
    $realTtl = abs($token->expires_at->diffInSeconds(now()));

    expect($expiresIn)->toBe(config('auth_api.access_token_ttl_minutes') * 60)
        ->and(abs($expiresIn - $realTtl))->toBeLessThanOrEqual(1);
});
