<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Seed reference currencies via factory states.
     */
    public function run(): void
    {
        Currency::factory()->xof()->create();
        Currency::factory()->eur()->create();
        Currency::factory()->usd()->create();
    }
}
