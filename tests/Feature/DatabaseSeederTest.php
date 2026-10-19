<?php

use App\Models\Country;
use App\Models\Currency;
use App\Models\Language;
use Database\Seeders\DatabaseSeeder;

test('database seeder fills currencies languages and countries from factories', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Currency::count())->toBe(3)
        ->and(Language::count())->toBe(2)
        ->and(Country::count())->toBe(4);

    $this->assertDatabaseHas('currencies', [
        'code' => 'XOF',
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->assertDatabaseHas('currencies', ['code' => 'EUR', 'is_default' => false]);
    $this->assertDatabaseHas('currencies', ['code' => 'USD', 'is_default' => false]);

    $this->assertDatabaseHas('languages', [
        'code' => 'fr',
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->assertDatabaseHas('languages', [
        'code' => 'en',
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('countries', [
        'code' => 'BF',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $this->assertDatabaseHas('countries', [
        'code' => 'SN',
        'is_active' => false,
        'sort_order' => 2,
    ]);
    $this->assertDatabaseHas('countries', [
        'code' => 'CI',
        'is_active' => false,
        'sort_order' => 3,
    ]);
    $this->assertDatabaseHas('countries', [
        'code' => 'FR',
        'is_active' => false,
        'sort_order' => 4,
    ]);

    $burkina = Country::where('code', 'BF')->firstOrFail();
    expect($burkina->currency->code)->toBe('XOF')
        ->and($burkina->language->code)->toBe('fr');

    $france = Country::where('code', 'FR')->firstOrFail();
    expect($france->currency->code)->toBe('EUR')
        ->and($france->language->code)->toBe('fr');
});
