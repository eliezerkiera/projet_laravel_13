<?php

use App\Models\Language;
use App\Models\User;

test('migration clears Arabic user preferences and can reactivate Arabic on rollback', function () {
    $arabic = Language::create([
        'code' => 'ar',
        'name' => 'Arabic',
        'native_name' => 'العربية',
        'locale' => 'ar_SA',
        'carbon_locale' => 'ar',
        'direction' => 'rtl',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 3,
    ]);
    $user = User::factory()->create(['language_id' => $arabic->id]);

    $migration = require database_path('migrations/2026_10_10_152202_deactivate_arabic_language.php');
    $migration->up();

    expect($user->fresh()->language_id)->toBeNull()
        ->and($arabic->fresh()->is_active)->toBeFalse();

    $migration->down();

    expect($arabic->fresh()->is_active)->toBeTrue();
});
