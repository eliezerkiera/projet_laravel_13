<?php

use App\Models\Country;
use App\Models\Language;
use App\Models\SocialAccount;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('it creates a new user and social account on first Google login', function () {
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'google123';
    $socialiteUser->email = 'newuser@example.com';
    $socialiteUser->name = 'John Doe';
    $socialiteUser->user = ['email_verified' => true];

    Socialite::shouldReceive('driver->stateless->userFromToken')
        ->once()
        ->with('fake-google-token')
        ->andReturn($socialiteUser);

    $response = $this->postJson('/api/v2/auth/google/token', [
        'token' => 'fake-google-token',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'access_token',
            'refresh_token',
            'token_type',
            'expires_in',
            'refresh_expires_in',
            'user',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'newuser@example.com',
    ]);

    $this->assertDatabaseHas('social_accounts', [
        'provider' => 'google',
        'provider_id' => 'google123',
        'provider_email' => 'newuser@example.com',
    ]);
});

test('it links Google account to existing user with same email', function () {
    $existingUser = User::create([
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'email' => 'alice@example.com',
        'password' => null,
        'country_id' => Country::first()->id,
        'language_id' => Language::first()->id,
    ]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'google456';
    $socialiteUser->email = 'alice@example.com';
    $socialiteUser->name = 'Alice Smith';
    $socialiteUser->user = ['email_verified' => true];

    Socialite::shouldReceive('driver->stateless->userFromToken')
        ->once()
        ->with('fake-google-token')
        ->andReturn($socialiteUser);

    $response = $this->postJson('/api/v2/auth/google/token', [
        'token' => 'fake-google-token',
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $existingUser->id,
        'provider' => 'google',
        'provider_id' => 'google456',
    ]);

    // Ensure no duplicate user was created
    $this->assertDatabaseCount('users', 1);
});

test('it returns existing user when social account already linked', function () {
    $user = User::create([
        'first_name' => 'Bob',
        'last_name' => 'Johnson',
        'email' => 'bob@example.com',
        'password' => null,
        'country_id' => Country::first()->id,
        'language_id' => Language::first()->id,
    ]);

    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google789',
        'provider_email' => 'bob@example.com',
    ]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'google789';
    $socialiteUser->email = 'bob@example.com';
    $socialiteUser->name = 'Bob Johnson';
    $socialiteUser->user = ['email_verified' => true];

    Socialite::shouldReceive('driver->stateless->userFromToken')
        ->once()
        ->with('fake-google-token')
        ->andReturn($socialiteUser);

    $response = $this->postJson('/api/v2/auth/google/token', [
        'token' => 'fake-google-token',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('user.email', 'bob@example.com');
});

test('it rejects Google auth when email is not verified and user exists', function () {
    User::create([
        'first_name' => 'Charlie',
        'last_name' => 'Brown',
        'email' => 'charlie@example.com',
        'password' => null,
        'country_id' => Country::first()->id,
        'language_id' => Language::first()->id,
    ]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'google999';
    $socialiteUser->email = 'charlie@example.com';
    $socialiteUser->name = 'Charlie Brown';
    $socialiteUser->user = ['email_verified' => false];

    Socialite::shouldReceive('driver->stateless->userFromToken')
        ->once()
        ->with('fake-google-token')
        ->andReturn($socialiteUser);

    $response = $this->postJson('/api/v2/auth/google/token', [
        'token' => 'fake-google-token',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'google_email_not_verified');
});

test('it returns validation error when token is missing', function () {
    $response = $this->postJson('/api/v2/auth/google/token', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

test('it rejects invalid Google token', function () {
    Socialite::shouldReceive('driver->stateless->userFromToken')
        ->once()
        ->with('invalid-token')
        ->andThrow(new Exception('Invalid token'));

    $response = $this->postJson('/api/v2/auth/google/token', [
        'token' => 'invalid-token',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'google_token_invalid');
});

test('it accepts custom device_name in request', function () {
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'google111';
    $socialiteUser->email = 'deviceuser@example.com';
    $socialiteUser->name = 'Device User';
    $socialiteUser->user = ['email_verified' => true];

    Socialite::shouldReceive('driver->stateless->userFromToken')
        ->once()
        ->with('fake-google-token')
        ->andReturn($socialiteUser);

    $response = $this->postJson('/api/v2/auth/google/token', [
        'token' => 'fake-google-token',
        'device_name' => 'My iPhone',
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('personal_access_tokens', [
        'name' => 'My iPhone',
    ]);
});

test('created user can access protected route with returned token', function () {
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'google222';
    $socialiteUser->email = 'protecteduser@example.com';
    $socialiteUser->name = 'Protected User';
    $socialiteUser->user = ['email_verified' => true];

    Socialite::shouldReceive('driver->stateless->userFromToken')
        ->once()
        ->with('fake-google-token')
        ->andReturn($socialiteUser);

    $response = $this->postJson('/api/v2/auth/google/token', [
        'token' => 'fake-google-token',
    ]);

    $response->assertStatus(200);
    $token = $response->json('access_token');

    // Use the token to access a protected route
    $protectedResponse = $this->withToken($token)
        ->getJson('/api/v2/auth/me');

    $protectedResponse->assertStatus(200)
        ->assertJsonPath('user.email', 'protecteduser@example.com');
});
