<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\Normalisers\PensionNormaliser;
use App\Services\Stores\PensionStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('persists a DC pension extraction via PensionStore with IngestSource::UPLOAD', function () {
    $user = User::factory()->create();

    $extraction = [
        'scheme_name' => 'Standard Life',
        'provider' => 'Standard Life',
        'pension_type' => 'personal',
        'current_fund_value' => 32500,
    ];

    $canonical = app(PensionNormaliser::class)->fromUploadDc($extraction);
    $pension = app(PensionStore::class)->createDc(
        $canonical,
        $user,
        IngestSource::UPLOAD
    );

    expect($pension)->toBeInstanceOf(DCPension::class);
    expect($pension->user_id)->toBe($user->id);
    expect($pension->scheme_name)->toBe('Standard Life');
    expect((float) $pension->current_fund_value)->toBe(32500.00);
});
