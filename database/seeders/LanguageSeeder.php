<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Seed reference languages via factory states.
     */
    public function run(): void
    {
        Language::factory()->french()->create();
        Language::factory()->english()->create();
    }
}
