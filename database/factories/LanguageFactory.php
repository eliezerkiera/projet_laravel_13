<?php

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->randomElement(['fr', 'en', 'es', 'de', 'it', 'pt', 'ar']),
            'name' => fake()->word(),
            'native_name' => fake()->word(),
            'flag' => fake()->randomElement(['🇫🇷', '🇬🇧', '🇪🇸', '🇩🇪', '🇮🇹', '🇵🇹', '🇸🇦']),
            'locale' => fake()->unique()->randomElement(['fr_FR', 'en_US', 'es_ES', 'de_DE', 'it_IT', 'pt_PT', 'ar_SA']),
            'carbon_locale' => fake()->randomElement(['fr', 'en', 'es', 'de', 'it', 'pt', 'ar']),
            'direction' => 'ltr',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Create a French language.
     */
    public function french(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'fr',
            'name' => 'French',
            'native_name' => 'Français',
            'flag' => '🇫🇷',
            'locale' => 'fr_FR',
            'carbon_locale' => 'fr',
            'direction' => 'ltr',
            'is_active' => true,
        ]);
    }

    /**
     * Create an English language.
     */
    public function english(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'flag' => '🇬🇧',
            'locale' => 'en_US',
            'carbon_locale' => 'en',
            'direction' => 'ltr',
            'is_active' => true,
        ]);
    }

    /**
     * Mark language as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
