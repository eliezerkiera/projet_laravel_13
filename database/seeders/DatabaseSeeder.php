<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Order matters: countries depend on currencies and languages.
        $this->call([
            CurrencySeeder::class,
            LanguageSeeder::class,
            CountrySeeder::class,
        ]);
    }
}
