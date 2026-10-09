<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->countryCode(),
            'name' => fake()->country(),
            'native_name' => fake()->country(),
            'phone_code' => '+'.fake()->numberBetween(1, 999),
            'currency_id' => Currency::factory(),
            'language_id' => Language::factory(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Create Burkina Faso.
     */
    public function burkinaFaso(): static
    {
        return $this->state(function (array $attributes) {
            $french = Language::where('code', 'fr')->first() ?? Language::factory()->french()->create();
            $xof = Currency::where('code', 'XOF')->first() ?? Currency::factory()->create(['code' => 'XOF']);

            return [
                'code' => 'BF',
                'name' => 'Burkina Faso',
                'native_name' => 'Burkina Faso',
                'phone_code' => '+226',
                'currency_id' => $xof->id,
                'language_id' => $french->id,
                'is_active' => true,
                'sort_order' => 1,
            ];
        });
    }

    /**
     * Create France.
     */
    public function france(): static
    {
        return $this->state(function (array $attributes) {
            $french = Language::where('code', 'fr')->first() ?? Language::factory()->french()->create();
            $eur = Currency::where('code', 'EUR')->first() ?? Currency::factory()->create(['code' => 'EUR']);

            return [
                'code' => 'FR',
                'name' => 'France',
                'native_name' => 'France',
                'phone_code' => '+33',
                'currency_id' => $eur->id,
                'language_id' => $french->id,
                'is_active' => true,
                'sort_order' => 2,
            ];
        });
    }

    /**
     * Create Senegal (inactive).
     */
    public function senegal(): static
    {
        return $this->state(function (array $attributes) {
            $french = Language::where('code', 'fr')->first() ?? Language::factory()->french()->create();
            $xof = Currency::where('code', 'XOF')->first() ?? Currency::factory()->create(['code' => 'XOF']);

            return [
                'code' => 'SN',
                'name' => 'Senegal',
                'native_name' => 'Sénégal',
                'phone_code' => '+221',
                'currency_id' => $xof->id,
                'language_id' => $french->id,
                'is_active' => false,
                'sort_order' => 3,
            ];
        });
    }

    /**
     * Mark country as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Mark country as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
