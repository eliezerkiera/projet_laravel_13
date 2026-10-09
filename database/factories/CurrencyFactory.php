<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->randomElement(['USD', 'EUR', 'XOF', 'GBP', 'JPY', 'CAD', 'AUD']),
            'name' => fake()->word(),
            'symbol' => fake()->randomElement(['$', '€', 'F CFA', '£', '¥', 'C$', 'A$']),
            'symbol_position' => fake()->randomElement(['before', 'after']),
            'decimal_places' => fake()->numberBetween(0, 2),
            'decimal_separator' => fake()->randomElement(['.', ',']),
            'thousands_separator' => fake()->randomElement([' ', ',', '.']),
            'is_active' => true,
            'is_default' => false,
        ];
    }

    /**
     * Create XOF currency.
     */
    public function xof(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'XOF',
            'name' => 'Franc CFA',
            'symbol' => 'F CFA',
            'symbol_position' => 'after',
            'decimal_places' => 0,
            'decimal_separator' => ',',
            'thousands_separator' => ' ',
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    /**
     * Create EUR currency.
     */
    public function eur(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'symbol_position' => 'after',
            'decimal_places' => 2,
            'decimal_separator' => ',',
            'thousands_separator' => ' ',
            'is_active' => true,
            'is_default' => false,
        ]);
    }
}
