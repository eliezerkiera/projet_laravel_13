<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Seed reference countries via factory states.
     * Requires currencies and languages to be seeded first.
     */
    public function run(): void
    {
        Country::factory()->burkinaFaso()->create();
        Country::factory()->senegal()->create();
        Country::factory()->ivoryCoast()->create();
        Country::factory()->france()->create();
    }
}
