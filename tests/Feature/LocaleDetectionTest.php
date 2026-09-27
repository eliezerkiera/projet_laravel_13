<?php

use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LocaleDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the database with countries and languages
        $this->seed(DatabaseSeeder::class);
    }

    public function test_manual_preferences_are_never_overridden_by_auto_detection(): void
    {
        $user = User::factory()->create([
            'country_id' => Country::where('code', 'BF')->first()->id,
            'language_id' => Language::where('code', 'fr')->first()->id,
            'country_source' => 'manual',
            'language_source' => 'manual',
        ]);

        $this->actingAs($user)
            ->get('/api/v2/auth/me')
            ->assertStatus(200);

        // Verify manual preferences are preserved
        $user->refresh();
        $this->assertEquals('manual', $user->country_source);
        $this->assertEquals('manual', $user->language_source);
        $this->assertEquals('BF', $user->country->code);
        $this->assertEquals('fr', $user->language->code);
    }

    public function test_auto_preferences_in_database_are_not_re_detected(): void
    {
        $user = User::factory()->create([
            'country_id' => Country::where('code', 'BF')->first()->id,
            'language_id' => Language::where('code', 'fr')->first()->id,
            'country_source' => 'auto',
            'language_source' => 'auto',
        ]);

        $this->actingAs($user)
            ->get('/api/v2/auth/me')
            ->assertStatus(200);

        // Verify auto preferences are preserved (not re-detected)
        $user->refresh();
        $this->assertEquals('auto', $user->country_source);
        $this->assertEquals('auto', $user->language_source);
        $this->assertEquals('BF', $user->country->code);
        $this->assertEquals('fr', $user->language->code);
    }

    public function test_country_detection_by_ip_with_language_fallback(): void
    {
        // This test would require mocking the Location package
        // For now, we'll test the fallback behavior
        $user = User::factory()->create([
            'country_id' => null,
            'language_id' => null,
            'country_source' => null,
            'language_source' => null,
        ]);

        // Set a test IP in env
        Config::set('location.test_ip', '102.164.48.2'); // Burkina Faso IP

        $this->actingAs($user)
            ->get('/api/v2/auth/me')
            ->assertStatus(200);

        // After auto-detection, user should have country and language set
        $user->refresh();
        $this->assertNotNull($user->country_id);
        $this->assertNotNull($user->language_id);
        $this->assertEquals('auto', $user->country_source);
        $this->assertEquals('auto', $user->language_source);
    }

    public function test_accept_language_header_takes_priority_over_country_fallback(): void
    {
        $user = User::factory()->create([
            'country_id' => null,
            'language_id' => null,
            'country_source' => null,
            'language_source' => null,
        ]);

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en')
            ->get('/api/v2/auth/me')
            ->assertStatus(200);

        // Verify English language is used (Accept-Language priority)
        $user->refresh();
        $this->assertEquals('en', $user->language->code);
    }

    public function test_x_locale_override_header_takes_priority_over_all_detection(): void
    {
        $user = User::factory()->create([
            'country_id' => Country::where('code', 'BF')->first()->id,
            'language_id' => Language::where('code', 'fr')->first()->id,
            'country_source' => 'manual',
            'language_source' => 'manual',
        ]);

        $this->actingAs($user)
            ->withHeader('X-Locale-Override', 'en')
            ->get('/api/v2/auth/me')
            ->assertStatus(200);

        // The override should take priority
        $this->assertEquals('en', app()->getLocale());
    }

    public function test_x_locale_override_works_for_unauthenticated_users(): void
    {
        $this->withHeader('X-Locale-Override', 'en')
            ->get('/api/v2/auth/register/send-code')
            ->assertStatus(422); // Validation error, but request processed

        // Verify the override took effect
        $this->assertEquals('en', app()->getLocale());
    }

    public function test_locale_endpoint_validation_and_manual_flag(): void
    {
        $user = User::factory()->create([
            'country_id' => Country::where('code', 'BF')->first()->id,
            'language_id' => Language::where('code', 'fr')->first()->id,
            'country_source' => 'auto',
            'language_source' => 'auto',
        ]);

        $countryBF = Country::where('code', 'BF')->first();
        $languageEn = Language::where('code', 'en')->first();

        $this->actingAs($user)
            ->patchJson('/api/v2/auth/locale', [
                'country_id' => $countryBF->id,
                'language_id' => $languageEn->id,
            ])
            ->assertStatus(200);

        $user->refresh();
        $this->assertEquals('manual', $user->country_source);
        $this->assertEquals('manual', $user->language_source);
        $this->assertEquals('en', $user->language->code);
    }

    public function test_locale_endpoint_requires_at_least_one_field(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v2/auth/locale', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields']);
    }

    public function test_locale_endpoint_reset_to_auto(): void
    {
        $user = User::factory()->create([
            'country_id' => Country::where('code', 'BF')->first()->id,
            'language_id' => Language::where('code', 'fr')->first()->id,
            'country_source' => 'manual',
            'language_source' => 'manual',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v2/auth/locale', [
                'reset_to_auto' => true,
            ])
            ->assertStatus(200);

        $user->refresh();
        $this->assertEquals('auto', $user->country_source);
        $this->assertEquals('auto', $user->language_source);
    }

    public function test_manual_preferences_not_overridden_after_update(): void
    {
        $user = User::factory()->create([
            'country_id' => Country::where('code', 'BF')->first()->id,
            'language_id' => Language::where('code', 'fr')->first()->id,
            'country_source' => 'auto',
            'language_source' => 'auto',
        ]);

        $countryBF = Country::where('code', 'BF')->first();
        $languageEn = Language::where('code', 'en')->first();

        // Update to manual
        $this->actingAs($user)
            ->patchJson('/api/v2/auth/locale', [
                'country_id' => $countryBF->id,
                'language_id' => $languageEn->id,
            ])
            ->assertStatus(200);

        // Make another request to ensure manual preferences are preserved
        $this->actingAs($user)
            ->get('/api/v2/auth/me')
            ->assertStatus(200);

        $user->refresh();
        $this->assertEquals('manual', $user->country_source);
        $this->assertEquals('manual', $user->language_source);
        $this->assertEquals('en', $user->language->code);
    }

    public function test_complete_fallback_to_app_defaults(): void
    {
        // Test with no signals at all (no IP, no Accept-Language, no user preferences)
        $user = User::factory()->create([
            'country_id' => null,
            'language_id' => null,
            'country_source' => null,
            'language_source' => null,
        ]);

        // Simulate no IP detection (private IP)
        Config::set('location.test_ip', '127.0.0.1');

        $this->actingAs($user)
            ->get('/api/v2/auth/me')
            ->assertStatus(200);

        // Should fallback to app defaults
        $this->assertEquals(config('app.locale', 'en'), app()->getLocale());
    }

    public function test_inactive_country_fallback_behavior(): void
    {
        // Create an inactive country
        $inactiveCountry = Country::where('code', 'BF')->first();
        $inactiveCountry->update(['is_active' => false]);

        $user = User::factory()->create([
            'country_id' => null,
            'language_id' => null,
            'country_source' => null,
            'language_source' => null,
        ]);

        // The system should fallback to default country (BF should be active in real scenario)
        // But since we made BF inactive, it should still work with a warning
        $this->actingAs($user)
            ->get('/api/v2/auth/me')
            ->assertStatus(200);

        // Reset for other tests
        $inactiveCountry->update(['is_active' => true]);
    }

    public function test_locale_endpoint_validates_country_and_language_exist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v2/auth/locale', [
                'country_id' => 99999, // Non-existent
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['country_id']);

        $this->actingAs($user)
            ->patchJson('/api/v2/auth/locale', [
                'language_id' => 99999, // Non-existent
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['language_id']);
    }
}
