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
        'first_name' => 'Charlie',
        'last_name' => 'Brown',
        'email' => 'charlie@example.com',
        'password' => Hash::make('MyPassword123!'),
        'country_id' => Country::first()->id,
        'language_id' => Language::first()->id,
    ]);

    $this->token = $this->user->createToken('Test Device')->plainTextToken;
});

test('it gets authenticated user profile', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v2/auth/me');

    $response->assertOk()
        ->assertJson([
            'user' => [
                'id' => $this->user->id,
                'email' => 'charlie@example.com',
                'first_name' => 'Charlie',
                'last_name' => 'Brown',
            ],
        ]);
});

test('it updates profile basic fields without email modification', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson('/api/v2/auth/profile', [
            'first_name' => 'Charles',
            'last_name' => 'Brown Jr',
        ]);

    $response->assertOk()
        ->assertJson([
            'message' => __('auth.profile_updated'),
            'user' => [
                'first_name' => 'Charles',
                'last_name' => 'Brown Jr',
            ],
        ]);

    $this->user->refresh();
    expect($this->user->first_name)->toBe('Charles')
        ->and($this->user->pending_email)->toBeNull();
});

test('it initiates OTP verification to new email when email is updated', function () {
    Notification::fake();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson('/api/v2/auth/profile', [
            'email' => 'charlie.new@example.com',
        ]);

    $response->assertOk()
        ->assertJson([
            'message' => __('auth.email_change_otp_sent'),
            'pending_email' => 'charlie.new@example.com',
        ]);

    $this->user->refresh();
    expect($this->user->email)->toBe('charlie@example.com') // original email still active
        ->and($this->user->pending_email)->toBe('charlie.new@example.com');

    Notification::assertSentOnDemand(SendOtpNotification::class, function ($notification, $channels, $notifiable) {
        return $notifiable->routes['mail'] === 'charlie.new@example.com';
    });
});

test('it confirms new email with OTP code', function () {
    $this->user->update(['pending_email' => 'charlie.new@example.com']);

    VerificationCode::create([
        'user_id' => $this->user->id,
        'email' => 'charlie.new@example.com',
        'code' => '556677',
        'type' => VerificationCode::TYPE_EMAIL_CHANGE,
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v2/auth/profile/verify-email', [
            'code' => '556677',
        ]);

    $response->assertOk()
        ->assertJson([
            'message' => __('auth.email_verified_success'),
            'user' => [
                'email' => 'charlie.new@example.com',
                'pending_email' => null,
            ],
        ]);

    $this->user->refresh();
    expect($this->user->email)->toBe('charlie.new@example.com')
        ->and($this->user->pending_email)->toBeNull();
});

test('it changes password when current password is valid', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson('/api/v2/auth/password', [
            'current_password' => 'MyPassword123!',
            'password' => 'NewSecuredPassword456!',
            'password_confirmation' => 'NewSecuredPassword456!',
        ]);

    $response->assertOk()
        ->assertJson([
            'message' => __('auth.password_changed'),
        ]);

    $this->user->refresh();
    expect(Hash::check('NewSecuredPassword456!', $this->user->password))->toBeTrue();
});

test('it soft deletes account with password confirmation', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/v2/auth/account', [
            'password' => 'MyPassword123!',
        ]);

    $response->assertOk()
        ->assertJson([
            'message' => __('auth.account_deleted'),
        ]);

    // Check soft delete in database
    expect(User::find($this->user->id))->toBeNull()
        ->and(User::withTrashed()->find($this->user->id))->not->toBeNull()
        ->and($this->user->tokens()->count())->toBe(0);
});
