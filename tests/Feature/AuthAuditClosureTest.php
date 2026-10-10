<?php

use App\Jobs\SendOtpEmail;
use App\Models\Country;
use App\Models\Language;
use App\Models\RefreshToken;
use App\Models\User;
use App\Models\VerificationCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->user = User::create([
        'first_name' => 'Helen',
        'last_name' => 'Hunt',
        'email' => 'helen@example.com',
        'password' => Hash::make('Secret123!'),
        'country_id' => Country::first()->id,
        'language_id' => Language::first()->id,
    ]);

    $this->token = $this->user->createToken('iPhone 15')->plainTextToken;
});

test('verify-pending-email only accepts the pending address', function () {
    $this->user->update(['pending_email' => 'helen.new@example.com']);

    VerificationCode::create([
        'user_id' => $this->user->id,
        'email' => 'helen.new@example.com',
        'code' => '999887',
        'type' => VerificationCode::TYPE_EMAIL_CHANGE,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson('/api/v2/auth/verify-pending-email', [
        'email' => 'helen@example.com',
        'code' => '999887',
    ])
        ->assertStatus(422)
        ->assertJson(['code' => 'invalid_token']);

    $this->user->refresh();
    expect($this->user->email)->toBe('helen@example.com')
        ->and($this->user->pending_email)->toBe('helen.new@example.com');

    $this->postJson('/api/v2/auth/verify-pending-email', [
        'email' => 'helen.new@example.com',
        'code' => '999887',
    ])->assertOk();

    $this->user->refresh();
    expect($this->user->email)->toBe('helen.new@example.com')
        ->and($this->user->pending_email)->toBeNull();
});

test('otp emails are dispatched only after the HTTP response', function () {
    Queue::fake();
    Notification::fake();

    $pushedWhenResponseWasBuilt = null;

    // Registered before the request: runs first during kernel terminate, i.e. right
    // after the response has been sent and before the deferred job callbacks.
    $this->app->terminating(function () use (&$pushedWhenResponseWasBuilt) {
        $pushedWhenResponseWasBuilt = Queue::pushed(SendOtpEmail::class)->count();
    });

    $response = $this->postJson('/api/v2/auth/register/send-code', [
        'email' => 'deferred@example.com',
    ]);

    $response->assertOk();

    expect($pushedWhenResponseWasBuilt)->toBe(0);

    Queue::assertPushed(SendOtpEmail::class, fn (SendOtpEmail $job) => $job->email === 'deferred@example.com');
    Notification::assertNothingSent();
});

test('account deletion requires the password and revokes every token', function () {
    $refreshToken = RefreshToken::create([
        'user_id' => $this->user->id,
        'token' => Str::random(64),
        'device_name' => 'iPhone 15',
        'expires_at' => now()->addDays(30),
        'revoked' => false,
        'family_id' => (string) Str::uuid(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/v2/auth/account', [])
        ->assertStatus(422)
        ->assertJson(['code' => 'validation_failed']);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/v2/auth/account', ['password' => 'WrongPassword'])
        ->assertStatus(422)
        ->assertJson(['code' => 'password_current_incorrect']);

    expect($this->user->tokens()->count())->toBe(1)
        ->and(RefreshToken::where('revoked', false)->count())->toBe(1);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/v2/auth/account', ['password' => 'Secret123!'])
        ->assertOk()
        ->assertJson(['message' => __('auth.account_deleted')]);

    expect($this->user->tokens()->count())->toBe(0)
        ->and(RefreshToken::where('id', $refreshToken->id)->first()->revoked)->toBeTrue()
        ->and(RefreshToken::where('id', $refreshToken->id)->first()->revoked_reason)->toBe('account_deleted');
});

test('reusing a rotated refresh token revokes the whole family', function () {
    $familyId = (string) Str::uuid();
    RefreshToken::create([
        'user_id' => $this->user->id,
        'token' => $originalToken = Str::random(64),
        'device_name' => 'iPhone 15',
        'expires_at' => now()->addDays(30),
        'revoked' => false,
        'family_id' => $familyId,
    ]);

    $firstUse = $this->postJson('/api/v2/auth/refresh', ['refresh_token' => $originalToken]);
    $firstUse->assertOk();

    $rotated = RefreshToken::where('token', $originalToken)->first();
    expect($rotated->revoked)->toBeTrue()
        ->and($rotated->revoked_reason)->toBe('rotated')
        ->and($rotated->rotated_at)->not->toBeNull()
        ->and($rotated->family_id)->toBe($familyId);

    expect(RefreshToken::where('family_id', $familyId)->where('token', $originalToken)->count())->toBe(1)
        ->and(RefreshToken::where('family_id', $familyId)->where('revoked', false)->count())->toBe(1);

    $reuse = $this->postJson('/api/v2/auth/refresh', ['refresh_token' => $originalToken]);

    $reuse->assertStatus(422)->assertJson(['code' => 'invalid_refresh_token']);

    expect(RefreshToken::where('family_id', $familyId)->where('revoked', false)->count())->toBe(0);
});

test('refresh keeps the device family id stable and does not duplicate devices', function () {
    $familyId = (string) Str::uuid();
    RefreshToken::create([
        'user_id' => $this->user->id,
        'token' => $originalToken = Str::random(64),
        'device_name' => 'iPhone 15',
        'expires_at' => now()->addDays(30),
        'revoked' => false,
        'family_id' => $familyId,
    ]);

    $before = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v2/auth/devices');

    $before->assertOk()->assertJsonCount(1, 'devices')
        ->assertJsonPath('devices.0.id', $familyId);

    $refreshed = $this->postJson('/api/v2/auth/refresh', ['refresh_token' => $originalToken]);
    $refreshed->assertOk()
        ->assertJsonStructure(['access_token', 'refresh_token']);

    expect($refreshed->json('refresh_token'))->not->toBe($originalToken);

    $after = $this->withHeader('Authorization', 'Bearer '.$refreshed->json('access_token'))
        ->getJson('/api/v2/auth/devices');

    $after->assertOk()->assertJsonCount(1, 'devices')
        ->assertJsonPath('devices.0.id', $familyId);
});
