<?php

namespace Tests\Feature\Api\V2;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for locale and country resolution.
 * This includes the proof test for Hypothesis H1 and all regression tests.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Don't seed DatabaseSeeder as it creates random data that interferes with tests
    }

    /**
     * Helper to create or get French language.
     */
    protected function createFrenchLanguage(): Language
    {
        return Language::firstOrCreate(
            ['code' => 'fr'],
            [
                'name' => 'French',
                'native_name' => 'Français',
                'flag' => '🇫🇷',
                'locale' => 'fr_FR',
                'carbon_locale' => 'fr',
                'direction' => 'ltr',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 1,
            ]
        );
    }

    /**
     * Helper to create or get English language.
     */
    protected function createEnglishLanguage(): Language
    {
        return Language::firstOrCreate(
            ['code' => 'en'],
            [
                'name' => 'English',
                'native_name' => 'English',
                'flag' => '🇬🇧',
                'locale' => 'en_US',
                'carbon_locale' => 'en',
                'direction' => 'ltr',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 2,
            ]
        );
    }

    /**
     * Helper to create Burkina Faso country.
     */
    protected function createBurkinaFaso(?Currency $currency = null, ?Language $language = null): Country
    {
        $currency = $currency ?? Currency::firstOrCreate(
            ['code' => 'XOF'],
            [
                'name' => 'Franc CFA',
                'symbol' => 'F CFA',
                'symbol_position' => 'after',
                'decimal_places' => 0,
                'decimal_separator' => ',',
                'thousands_separator' => ' ',
                'is_active' => true,
                'is_default' => true,
            ]
        );
        $language = $language ?? $this->createFrenchLanguage();

        return Country::firstOrCreate(
            ['code' => 'BF'],
            [
                'name' => 'Burkina Faso',
                'native_name' => 'Burkina Faso',
                'phone_code' => '+226',
                'currency_id' => $currency->id,
                'language_id' => $language->id,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    /**
     * Helper to create France country.
     */
    protected function createFrance(?Currency $currency = null, ?Language $language = null): Country
    {
        $currency = $currency ?? Currency::firstOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'Euro',
                'symbol' => '€',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'decimal_separator' => ',',
                'thousands_separator' => ' ',
                'is_active' => true,
                'is_default' => false,
            ]
        );
        $language = $language ?? $this->createFrenchLanguage();

        return Country::firstOrCreate(
            ['code' => 'FR'],
            [
                'name' => 'France',
                'native_name' => 'France',
                'phone_code' => '+33',
                'currency_id' => $currency->id,
                'language_id' => $language->id,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );
    }

    /**
     * Proof test for Hypothesis H1:
     * Currently, the middleware uses $request->user() (web guard) which returns null for API requests.
     * This means user profile language is NOT respected.
     * This test SHOULD FAIL before the fix, and PASS after.
     */
    public function test_hypothesis_h1_profile_language_should_be_respected(): void
    {
        $english = $this->createEnglishLanguage();

        // Create a user with English as their profile language
        $user = User::factory()->create([
            'language_id' => $english->id,
            'country_id' => null,
        ]);

        // Create a Sanctum token for the user
        $token = $user->createToken('test')->plainTextToken;

        // Make a request with Bearer token but NO Accept-Language header
        // The resolved language should be English (from profile)
        $response = $this
            ->withHeader('Authorization', "Bearer $token")
            ->get('/api/v2/countries/active');

        $response->assertHeader('Content-Language', 'en');
    }

    /**
     * Test GET /api/v2/locale endpoint - anonymous user, single active country
     */
    public function test_get_locale_anonymous_single_active_country(): void
    {
        $this->createFrenchLanguage();
        $this->createBurkinaFaso();

        $response = $this->get('/api/v2/locale');

        $response->assertStatus(200);
        $response->assertHeader('Content-Language', 'fr');
        $response->assertJsonStructure([
            'language',
            'country',
            'country_selection_required',
            'preferences',
            'supported_languages',
            'countries',
        ]);

        $response->assertJson([
            'country_selection_required' => false,
            'preferences' => [
                'language_id' => null,
                'country_id' => null,
            ],
        ]);

        $response->assertJsonPath('country.source', 'single_active');
        $response->assertJsonPath('language.source', 'country');
    }

    /**
     * Test GET /api/v2/locale endpoint - anonymous user, multiple active countries
     */
    public function test_get_locale_anonymous_multiple_active_countries(): void
    {
        $this->createFrenchLanguage();

        $this->createBurkinaFaso();
        $this->createFrance();

        $response = $this->get('/api/v2/locale');

        $response->assertStatus(200);
        $response->assertJson([
            'country' => null,
            'country_selection_required' => true,
            'preferences' => [
                'language_id' => null,
                'country_id' => null,
            ],
        ]);
    }

    /**
     * Test GET /api/v2/locale with Accept-Language header
     */
    public function test_get_locale_with_accept_language_header(): void
    {
        $french = $this->createFrenchLanguage();
        $english = $this->createEnglishLanguage();

        // Create a country but it should not affect the result
        $this->createBurkinaFaso();
        Country::where('code', 'BF')->update(['is_active' => false]); // Deactivate to test Accept-Language

        $response = $this
            ->withHeader('Accept-Language', 'en')
            ->get('/api/v2/locale');

        $response->assertStatus(200);
        $response->assertHeader('Content-Language', 'en');
        $response->assertJsonPath('language.code', 'en');
        $response->assertJsonPath('language.source', 'accept_language');
    }

    /**
     * Test GET /api/v2/locale with X-Locale-Override header
     */
    public function test_get_locale_with_x_locale_override_header(): void
    {
        $french = $this->createFrenchLanguage();
        $english = $this->createEnglishLanguage();

        // Create a country but it should not affect the result
        $this->createBurkinaFaso();
        Country::where('code', 'BF')->update(['is_active' => false]);

        $response = $this
            ->withHeader('X-Locale-Override', 'en')
            ->get('/api/v2/locale');

        $response->assertStatus(200);
        $response->assertHeader('Content-Language', 'en');
        $response->assertJsonPath('language.code', 'en');
        $response->assertJsonPath('language.source', 'override');
    }

    /**
     * Test connected user profile language priority
     */
    public function test_connected_user_profile_language_priority(): void
    {
        $french = $this->createFrenchLanguage();
        $english = $this->createEnglishLanguage();

        $this->createBurkinaFaso();
        Country::where('code', 'BF')->update(['is_active' => false]);

        // Create a user with English as their profile language
        $user = User::factory()->create([
            'language_id' => $english->id,
            'country_id' => null,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', "Bearer $token")
            ->withHeader('Accept-Language', 'fr')
            ->get('/api/v2/locale');

        $response->assertStatus(200);
        $response->assertHeader('Content-Language', 'en');
        $response->assertJsonPath('language.code', 'en');
        $response->assertJsonPath('language.source', 'profile');
    }

    /**
     * Test X-Locale-Override overrides profile
     */
    public function test_x_locale_override_overrides_profile(): void
    {
        $french = $this->createFrenchLanguage();
        $english = $this->createEnglishLanguage();

        $this->createBurkinaFaso();
        Country::where('code', 'BF')->update(['is_active' => false]);

        // Create a user with English as their profile language
        $user = User::factory()->create([
            'language_id' => $english->id,
            'country_id' => null,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        // X-Locale-Override should override profile
        $response = $this
            ->withHeader('Authorization', "Bearer $token")
            ->withHeader('X-Locale-Override', 'fr')
            ->get('/api/v2/locale');

        $response->assertStatus(200);
        $response->assertHeader('Content-Language', 'fr');
        $response->assertJsonPath('language.code', 'fr');
        $response->assertJsonPath('language.source', 'override');
    }

    /**
     * Test that profile language is NOT auto-persisted
     */
    public function test_no_auto_persistence_of_profile_language(): void
    {
        $french = $this->createFrenchLanguage();
        $english = $this->createEnglishLanguage();

        $this->createBurkinaFaso();

        // Create a user with NO language set (null)
        $user = User::factory()->create([
            'language_id' => null,
            'country_id' => null,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        // Make request with Accept-Language: fr
        $this
            ->withHeader('Authorization', "Bearer $token")
            ->withHeader('Accept-Language', 'fr')
            ->get('/api/v2/locale');

        // Refresh user from database
        $user->refresh();

        // language_id should still be null (no auto-persistence)
        $this->assertNull($user->language_id);
    }

    /**
     * Test PATCH /api/v2/locale - update language
     */
    public function test_patch_locale_update_language(): void
    {
        $french = $this->createFrenchLanguage();
        $english = $this->createEnglishLanguage();

        $user = User::factory()->create([
            'language_id' => $french->id,
            'country_id' => null,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        // Test without X-Locale-Override to ensure profile is used
        $response = $this
            ->withHeader('Authorization', "Bearer $token")
            ->patch('/api/v2/locale', [
                'language_id' => $english->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'locale' => [
                'language' => ['code', 'source'],
                'country',
                'country_selection_required',
                'preferences',
                'supported_languages',
                'countries',
            ],
            'user',
        ]);
        $response->assertJsonPath('locale.language.code', 'en');
        $response->assertJsonPath('locale.language.source', 'profile');

        // Verify database was updated
        $user->refresh();
        $this->assertEquals($english->id, $user->language_id);
    }

    /**
     * Test PATCH /api/v2/locale - reset to auto
     */
    public function test_patch_locale_reset_to_auto(): void
    {
        $french = $this->createFrenchLanguage();
        $this->createEnglishLanguage(); // Ensure English language exists

        $user = User::factory()->create([
            'language_id' => $french->id,
            'country_id' => null,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', "Bearer $token")
            ->withHeader('X-Locale-Override', 'en')
            ->patch('/api/v2/locale', [
                'reset_to_auto' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'locale' => [
                'language',
                'country',
                'country_selection_required',
                'preferences',
                'supported_languages',
                'countries',
            ],
            'user',
        ]);

        // Verify database was updated
        $user->refresh();
        $this->assertNull($user->language_id);
        $this->assertNull($user->country_id);
    }

    /**
     * Test PATCH /api/v2/locale - validation: empty body
     */
    public function test_patch_locale_validation_empty_body(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', "Bearer $token")
            ->patch('/api/v2/locale', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['fields']);
    }

    /**
     * Test PATCH /api/v2/locale - validation: reset_to_auto with language_id
     */
    public function test_patch_locale_validation_reset_with_language_id(): void
    {
        $french = $this->createFrenchLanguage();
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', "Bearer $token")
            ->patch('/api/v2/locale', [
                'reset_to_auto' => true,
                'language_id' => $french->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reset_to_auto']);
    }

    /**
     * Test old route PATCH /api/v2/auth/locale returns 404
     */
    public function test_old_patch_auth_locale_route_not_found(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', "Bearer $token")
            ->patch('/api/v2/auth/locale', [
                'language_id' => 1,
            ]);

        $response->assertStatus(404);
    }

    /**
     * Test Content-Language header on 401 response
     * Use a protected route that requires auth
     */
    public function test_content_language_header_on_401(): void
    {
        $response = $this
            ->withHeader('Authorization', 'Bearer invalid-token')
            ->patch('/api/v2/locale', ['language_id' => 1]);

        $response->assertStatus(401);
        $response->assertHeader('Content-Language');
    }

    /**
     * Test Content-Language header on 404 response
     */
    public function test_content_language_header_on_404(): void
    {
        $response = $this->get('/api/v2/nonexistent');

        $response->assertStatus(404);
        $response->assertHeader('Content-Language');
    }

    /**
     * Test Vary header is present
     */
    public function test_vary_header_present(): void
    {
        $response = $this->get('/api/v2/locale');

        $response->assertStatus(200);
        $response->assertHeader('Vary');
    }
}
