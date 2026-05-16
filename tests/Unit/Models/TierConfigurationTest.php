<?php

declare(strict_types=1);

use App\Models\TierConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('casts JSON columns to arrays and booleans correctly', function () {
    $row = TierConfiguration::create(tierConfigFixture('tier2'));
    $fresh = $row->fresh();

    expect($fresh->capability_matrix)->toBeArray()
        ->and($fresh->count_caps)->toBeArray()
        ->and($fresh->open_api_affordance)->toBeBool()
        ->and($fresh->is_active)->toBeTrue();
});
