<?php

declare(strict_types=1);

use App\Models\DiscountCode;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deactivates historical trial extension codes without changing monetary codes', function () {
    $legacy = DiscountCode::factory()->create([
        'code' => 'HISTORICALTRIAL',
        'type' => 'trial_extension',
        'value' => 14,
        'is_active' => true,
    ]);
    $monetary = DiscountCode::factory()->percentage(20)->create([
        'code' => 'KEEP20',
        'is_active' => true,
    ]);

    $migration = require database_path('migrations/2026_07_15_000004_deactivate_trial_extension_discount_codes.php');
    $migration->up();

    expect($legacy->fresh()->is_active)->toBeFalse()
        ->and($monetary->fresh()->is_active)->toBeTrue();
});
