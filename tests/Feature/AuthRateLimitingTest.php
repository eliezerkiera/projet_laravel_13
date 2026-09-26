<?php

use App\Models\Country;
use App\Models\Language;
use App\Models\User;
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
