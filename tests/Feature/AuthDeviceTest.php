<?php

use App\Models\Country;
use App\Models\Language;
use App\Models\RefreshToken;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

    $this->device1Token = $this->user->createToken('iPhone 15')->plainTextToken;
    $this->user->createToken('iPad Air');
    $this->user->createToken('MacBook Pro');

    $this->family1 = (string) Str::uuid();
    $this->family2 = (string) Str::uuid();
    $this->family3 = (string) Str::uuid();

    foreach ([
        ['iPhone 15', $this->family1],
        ['iPad Air', $this->family2],
        ['MacBook Pro', $this->family3],
    ] as [$deviceName, $familyId]) {
        RefreshToken::create([
            'user_id' => $this->user->id,
            'token' => Str::random(64),
            'device_name' => $deviceName,
            'expires_at' => now()->addDays(30),
            'revoked' => false,
            'family_id' => $familyId,
        ]);
    }
});

test('it lists all connected devices', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->device1Token)
        ->getJson('/api/v2/auth/devices');

    $response->assertOk()
        ->assertJsonCount(3, 'devices')
        ->assertJsonStructure([
            'devices' => [
                '*' => ['id', 'name', 'is_current', 'last_used_at', 'created_at'],
            ],
        ])
        ->assertJson([
            'devices' => [
                ['id' => $this->family1, 'name' => 'iPhone 15', 'is_current' => true],
            ],
        ]);
});

test('it disconnects a specific device by its family id', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->device1Token)
        ->deleteJson('/api/v2/auth/devices/'.$this->family2);

    $response->assertOk()
        ->assertJson([
            'message' => __('auth.device_disconnected'),
        ]);

    expect(RefreshToken::where('family_id', $this->family2)->where('revoked', false)->count())->toBe(0)
        ->and($this->user->tokens()->where('name', 'iPad Air')->exists())->toBeFalse()
        ->and($this->user->tokens()->where('name', 'iPhone 15')->exists())->toBeTrue();
});

test('it returns device_not_found for an unknown or foreign device', function () {
    $otherUser = User::create([
        'first_name' => 'Eve',
        'last_name' => 'Other',
        'email' => 'eve@example.com',
        'password' => Hash::make('Secret123!'),
        'country_id' => Country::first()->id,
        'language_id' => Language::first()->id,
    ]);

    $foreignFamily = (string) Str::uuid();
    RefreshToken::create([
        'user_id' => $otherUser->id,
        'token' => Str::random(64),
        'device_name' => 'Pixel 9',
        'expires_at' => now()->addDays(30),
        'revoked' => false,
        'family_id' => $foreignFamily,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$this->device1Token)
        ->deleteJson('/api/v2/auth/devices/'.(string) Str::uuid())
        ->assertStatus(404)
        ->assertJson(['code' => 'device_not_found']);

    $this->withHeader('Authorization', 'Bearer '.$this->device1Token)
        ->deleteJson('/api/v2/auth/devices/'.$foreignFamily)
        ->assertStatus(404)
        ->assertJson(['code' => 'device_not_found']);
});

test('it disconnects all other devices except current one', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->device1Token)
        ->deleteJson('/api/v2/auth/devices');

    $response->assertOk()
        ->assertJson([
            'message' => __('auth.other_devices_disconnected'),
        ]);

    expect($this->user->refreshTokens()->where('revoked', false)->count())->toBe(1)
        ->and($this->user->refreshTokens()->where('revoked', false)->first()->family_id)->toBe($this->family1)
        ->and($this->user->tokens()->count())->toBe(1);
});

test('it logs out the current device session', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->device1Token)
        ->postJson('/api/v2/auth/logout');

    $response->assertOk()
        ->assertJson([
            'message' => __('auth.logged_out'),
        ]);

    expect($this->user->refreshTokens()->where('device_name', 'iPhone 15')->where('revoked', false)->count())->toBe(0)
        ->and($this->user->tokens()->where('name', 'iPhone 15')->exists())->toBeFalse()
        ->and($this->user->tokens()->count())->toBe(2);
});
