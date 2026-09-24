<?php

use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->user = User::create([
        'first_name' => 'David',
        'last_name' => 'Miller',
        'email' => 'david@example.com',
        'password' => Hash::make('Secret123!'),
        'country_id' => Country::first()->id,
        'language_id' => Language::first()->id,
    ]);

    $this->device1 = $this->user->createToken('iPhone 15');
    $this->device2 = $this->user->createToken('iPad Air');
    $this->device3 = $this->user->createToken('MacBook Pro');
});

test('it lists all connected devices', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->device1->plainTextToken)
        ->getJson('/api/v2/auth/devices');

    $response->assertOk()
        ->assertJsonCount(3, 'devices')
        ->assertJsonStructure([
            'devices' => [
                '*' => ['id', 'name', 'is_current', 'last_used_at', 'created_at'],
            ],
        ]);
});

test('it disconnects a specific device', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->device1->plainTextToken)
        ->deleteJson('/api/v2/auth/devices/'.$this->device2->accessToken->id);

    $response->assertOk()
        ->assertJson([
            'message' => __('auth.device_disconnected'),
        ]);

    expect($this->user->tokens()->where('id', $this->device2->accessToken->id)->exists())->toBeFalse()
        ->and($this->user->tokens()->where('id', $this->device1->accessToken->id)->exists())->toBeTrue();
});

test('it disconnects all other devices except current one', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->device1->plainTextToken)
        ->deleteJson('/api/v2/auth/devices');

    $response->assertOk()
        ->assertJson([
            'message' => __('auth.other_devices_disconnected'),
        ]);

    expect($this->user->tokens()->count())->toBe(1)
        ->and($this->user->tokens()->first()->id)->toBe($this->device1->accessToken->id);
});

test('it logs out the current device session', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->device1->plainTextToken)
        ->postJson('/api/v2/auth/logout');

    $response->assertOk()
        ->assertJson([
            'message' => __('auth.logged_out'),
        ]);

    expect($this->user->tokens()->where('id', $this->device1->accessToken->id)->exists())->toBeFalse()
        ->and($this->user->tokens()->count())->toBe(2);
});
