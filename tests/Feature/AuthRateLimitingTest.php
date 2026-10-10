<?php

use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use App\Models\VerificationCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    User::create([
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'email' => 'alice@example.com',
        'password' => Hash::make('Password123!'),
        'country_id' => Country::first()->id,
        'language_id' => Language::first()->id,
    ]);
});

test('it returns 429 after five failed login attempts from the same email and IP address', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v2/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'WrongPassword',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v2/auth/login', [
        'email' => 'alice@example.com',
        'password' => 'WrongPassword',
    ])->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

test('it returns 429 after five registration OTP verification attempts from the same email and IP address', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v2/auth/register/verify-code', [
            'email' => 'newuser@example.com',
            'code' => '123456',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v2/auth/register/verify-code', [
        'email' => 'newuser@example.com',
        'code' => '123456',
    ])->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

test('it applies otp-verify to profile email verification per user and IP', function () {
    $user = User::where('email', 'alice@example.com')->firstOrFail();
    $user->update(['pending_email' => 'alice.pending@example.com']);
    $userToken = $user->createToken('User Device')->plainTextToken;

    VerificationCode::create([
        'user_id' => $user->id,
        'email' => $user->pending_email,
        'code' => '123456',
        'type' => VerificationCode::TYPE_EMAIL_CHANGE,
        'expires_at' => now()->addMinutes(10),
    ]);

    $otherUser = User::factory()->create([
        'pending_email' => 'other.pending@example.com',
    ]);
    $otherUserToken = $otherUser->createToken('Other Device')->plainTextToken;

    VerificationCode::create([
        'user_id' => $otherUser->id,
        'email' => $otherUser->pending_email,
        'code' => '123456',
        'type' => VerificationCode::TYPE_EMAIL_CHANGE,
        'expires_at' => now()->addMinutes(10),
    ]);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->withHeader('Authorization', 'Bearer '.$userToken)
            ->postJson('/api/v2/auth/profile/verify-email', ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'otp_invalid');
    }

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$otherUserToken)
        ->postJson('/api/v2/auth/profile/verify-email', ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp_invalid');

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$userToken)
        ->postJson('/api/v2/auth/profile/verify-email', ['code' => '000000'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('code', 'too_many_requests');
});

test('it returns 429 after sixty public authentication requests from the same IP address', function () {
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $this->postJson('/api/v2/auth/register/verify-code', [
            'email' => "newuser{$attempt}@example.com",
            'code' => '123456',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v2/auth/register/verify-code', [
        'email' => 'newuser61@example.com',
        'code' => '123456',
    ])->assertTooManyRequests()
        ->assertHeader('Retry-After');
});
