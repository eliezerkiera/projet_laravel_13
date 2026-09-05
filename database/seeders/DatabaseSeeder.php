<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Language;
use App\Models\User;
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
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);


        $currencies = [
    [
        'code'                => 'XOF',
        'name'                => 'Franc CFA',
        'symbol'              => 'F CFA',
        'symbol_position'     => 'after',
        'decimal_places'      => 0,
        'decimal_separator'   => ',',
        'thousands_separator' => ' ',
        'is_active'           => true,
        'is_default'          => true,   // Devise principale
    ],
    [
        'code'                => 'EUR',
        'name'                => 'Euro',
        'symbol'              => '€',
        'symbol_position'     => 'after',
        'decimal_places'      => 2,
        'decimal_separator'   => ',',
        'thousands_separator' => ' ',
        'is_active'           => true,
        'is_default'          => false,
    ],
    [
        'code'                => 'USD',
        'name'                => 'Dollar américain',
        'symbol'              => '$',
        'symbol_position'     => 'before',
        'decimal_places'      => 2,
        'decimal_separator'   => '.',
        'thousands_separator' => ',',
        'is_active'           => true,
        'is_default'          => false,
    ],
];

    foreach($currencies as $currency)
        {
            Currency::create($currency);
        }






        
$languages = [
    [
        'code'          => 'fr',
        'name'          => 'French',
        'native_name'   => 'Français',
        'flag'          => '🇫🇷',
        'locale'        => 'fr_FR',
        'carbon_locale' => 'fr',
        'direction'     => 'ltr',
        'is_active'     => true,
        'is_default'    => true,
        'sort_order'    => 1,
    ],
    [
        'code'          => 'en',
        'name'          => 'English',
        'native_name'   => 'English',
        'flag'          => '🇬🇧',
        'locale'        => 'en_US',
        'carbon_locale' => 'en',
        'direction'     => 'ltr',
        'is_active'     => true,
        'is_default'    => false,
        'sort_order'    => 2,
    ],
    [
        'code'          => 'ar',
        'name'          => 'Arabic',
        'native_name'   => 'العربية',
        'flag'          => '🇸🇦',
        'locale'        => 'ar_SA',
        'carbon_locale' => 'ar',
        'direction'     => 'rtl',
        'is_active'     => true,
        'is_default'    => false,
        'sort_order'    => 3,
    ],
];

foreach($languages as $language)
    {
        Language::create($language);
    }





    
       // Récupérer les devises et langues par leur code
        $xof = Currency::where('code', 'XOF')->first();
        $eur = Currency::where('code', 'EUR')->first();

        $fr  = Language::where('code', 'fr')->first();
        $en  = Language::where('code', 'en')->first();
        $ar  = Language::where('code', 'ar')->first();

        $countries = [
            [
                'code'        => 'BF',
                'name'        => 'Burkina Faso',
                'native_name' => 'Burkina Faso',
                'phone_code'  => '+226',
                'currency_id' => $xof->id,
                'language_id' => $fr->id,
                'is_active'   => true,
                'sort_order'  => 1,
            ],
            [
                'code'        => 'SN',
                'name'        => 'Senegal',
                'native_name' => 'Sénégal',
                'phone_code'  => '+221',
                'currency_id' => $xof->id,
                'language_id' => $fr->id,
                'is_active'   => true,
                'sort_order'  => 2,
            ],
            [
                'code'        => 'CI',
                'name'        => "Ivory Coast",
                'native_name' => "Côte d'Ivoire",
                'phone_code'  => '+225',
                'currency_id' => $xof->id,
                'language_id' => $fr->id,
                'is_active'   => true,
                'sort_order'  => 3,
            ],
            [
                'code'        => 'FR',
                'name'        => 'France',
                'native_name' => 'France',
                'phone_code'  => '+33',
                'currency_id' => $eur->id,
                'language_id' => $fr->id,
                'is_active'   => true,
                'sort_order'  => 4,
            ],
        ];

        foreach ($countries as $country) {
            Country::firstOrCreate(
                ['code' => $country['code']],
                $country
            );
        }
    }
}
