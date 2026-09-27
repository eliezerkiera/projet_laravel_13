<?php

use App\Models\Country;
use App\Models\Language;
use App\Models\User;
use App\Services\LocaleDetectionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LocaleDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_it_auto_assigns_the_only_active_country_to_an_authenticated_user_without_a_country(): void
    {
        $user = User::factory()->create([
            'country_id' => null,
            'language_id' => null,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v2/auth/me')
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'country_id' => Country::query()->where('code', 'BF')->value('id'),
            'country_source' => 'auto',
        ]);
    }

    public function test_it_resolves_the_only_active_country_for_a_guest_without_persisting_any_preference(): void
    {
        $request = Request::create('/api/v2/countries/active');
        $request->headers->remove('Accept-Language');
        $detection = app(LocaleDetectionService::class)->detect($request);

        $this->assertSame('BF', $detection['country']?->code);
        $this->assertSame('fr', $detection['language']?->code);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_accept_language_is_used_for_an_unauthenticated_visitor(): void
    {
        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v2/auth/register/send-code')
            ->assertUnprocessable();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_x_locale_override_takes_priority_for_an_unauthenticated_visitor(): void
    {
        $this->withHeader('X-Locale-Override', 'en')
            ->postJson('/api/v2/auth/register/send-code')
            ->assertUnprocessable();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_it_does_not_auto_assign_a_country_when_multiple_countries_are_active(): void
    {
        Country::query()->where('code', 'SN')->update(['is_active' => true]);
        $user = User::factory()->create([
            'country_id' => null,
            'language_id' => null,
        ]);

        $this->actingAs($user)
            ->withHeader('Accept-Language', '')
            ->getJson('/api/v2/auth/me')
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'country_id' => null,
        ]);
        $this->assertSame(config('app.locale'), app()->getLocale());
    }

    public function test_active_countries_endpoint_signals_when_manual_selection_is_required(): void
    {
        Country::query()->where('code', 'SN')->update(['is_active' => true]);

        $this->getJson('/api/v2/countries/active')
            ->assertOk()
            ->assertJsonPath('country_selection_required', true)
            ->assertJsonCount(2, 'countries')
            ->assertJsonPath('countries.0.code', 'BF')
            ->assertJsonPath('countries.1.code', 'SN');
    }

    public function test_existing_country_is_never_recalculated_even_when_multiple_countries_are_active(): void
    {
        Country::query()->where('code', 'SN')->update(['is_active' => true]);
        $country = Country::query()->where('code', 'BF')->firstOrFail();
        $user = User::factory()->create([
            'country_id' => $country->id,
            'country_source' => 'auto',
            'language_id' => null,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v2/auth/me')
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'country_id' => $country->id,
            'country_source' => 'auto',
        ]);
    }

    public function test_country_language_is_used_when_no_language_header_or_preference_exists(): void
    {
        $user = User::factory()->create([
            'country_id' => null,
            'language_id' => null,
        ]);

        $this->actingAs($user)
            ->withHeader('Accept-Language', '')
            ->getJson('/api/v2/auth/me')
            ->assertOk();

        $this->assertSame('fr', app()->getLocale());
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'language_id' => Language::query()->where('code', 'fr')->value('id'),
            'language_source' => 'auto',
        ]);
    }

    public function test_it_uses_the_application_locale_when_no_country_can_be_resolved(): void
    {
        Country::query()->where('code', 'SN')->update(['is_active' => true]);

        $detection = app(LocaleDetectionService::class)->detect(Request::create('/api/v2/countries/active'));
        app(LocaleDetectionService::class)->applyLocale($detection);

        $this->assertNull($detection['country']);
        $this->assertSame(config('app.locale'), app()->getLocale());
    }

    public function test_manual_preferences_are_not_overridden_by_request_headers(): void
    {
        $country = Country::query()->where('code', 'BF')->firstOrFail();
        $language = Language::query()->where('code', 'fr')->firstOrFail();
        $user = User::factory()->create([
            'country_id' => $country->id,
            'language_id' => $language->id,
            'country_source' => 'manual',
            'language_source' => 'manual',
        ]);

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en')
            ->getJson('/api/v2/auth/me')
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'country_id' => $country->id,
            'language_id' => $language->id,
            'country_source' => 'manual',
            'language_source' => 'manual',
        ]);
        $this->assertSame('fr', app()->getLocale());
    }

    public function test_locale_endpoint_marks_selected_preferences_as_manual(): void
    {
        $user = User::factory()->create();
        $country = Country::query()->where('code', 'BF')->firstOrFail();
        $language = Language::query()->where('code', 'en')->firstOrFail();

        $this->actingAs($user)
            ->patchJson('/api/v2/auth/locale', [
                'country_id' => $country->id,
                'language_id' => $language->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'country_id' => $country->id,
            'language_id' => $language->id,
            'country_source' => 'manual',
            'language_source' => 'manual',
        ]);
    }

    public function test_locale_endpoint_requires_a_preference_or_reset_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v2/auth/locale', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fields']);
    }
}
