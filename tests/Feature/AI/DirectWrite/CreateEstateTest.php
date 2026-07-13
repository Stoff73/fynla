<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\Liability;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

// 0.5.h create_asset

it('create_asset persists an estate Asset', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_asset', [
        'asset_name' => 'Antique Watch',
        'asset_type' => 'other',
        'current_value' => 5000,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('estate_asset');

    $asset = Asset::find($result['entity_id']);
    expect($asset)->not->toBeNull();
    expect($asset->asset_name)->toBe('Antique Watch');
    expect((float) $asset->current_value)->toBe(5000.0);
    expect($asset->ownership_type)->toBe('individual');
});

it('create_asset returns validation_failed without required fields', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $result = app(CoordinatingAgent::class)->executeTool('create_asset', [
        'asset_name' => 'X',
    ], $user);
    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'])->toBe('validation_failed');
});

it('create_asset blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);
    $result = app(CoordinatingAgent::class)->executeTool('create_asset', [
        'asset_name' => 'X', 'asset_type' => 'other', 'current_value' => 1,
    ], $user);
    expect($result['blocked'])->toBeTrue();
});

// 0.5.i create_liability

it('create_liability persists an estate Liability', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = $this->executeCaptureToolWithEvidence('create_liability', [
        'liability_name' => 'Barclaycard',
        'liability_type' => 'credit_card',
        'current_balance' => 2500,
        'interest_rate' => 19.5,
        'monthly_payment' => 100,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('estate_liability');

    $liability = Liability::find($result['entity_id']);
    expect($liability)->not->toBeNull();
    expect($liability->liability_name)->toBe('Barclaycard');
    expect($liability->liability_type)->toBe('credit_card');
    expect((float) $liability->current_balance)->toBe(2500.0);
});

it('create_liability maps generic loan to personal_loan', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $result = $this->executeCaptureToolWithEvidence('create_liability', [
        'liability_name' => 'Loan',
        'liability_type' => 'loan',
        'current_balance' => 1000,
    ], $user);
    expect(Liability::find($result['entity_id'])->liability_type)->toBe('personal_loan');
});

it('create_liability blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);
    $result = $this->executeCaptureToolWithEvidence('create_liability', [
        'liability_name' => 'X', 'liability_type' => 'loan', 'current_balance' => 1,
    ], $user);
    expect($result['blocked'])->toBeTrue();
});

// 0.5.j create_estate_gift

it('create_estate_gift persists an estate Gift', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_estate_gift', [
        'gift_date' => '2024-06-15',
        'recipient' => 'Jane Doe',
        'gift_type' => 'pet',
        'gift_value' => 50000,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('estate_gift');

    $gift = Gift::find($result['entity_id']);
    expect($gift)->not->toBeNull();
    expect($gift->recipient)->toBe('Jane Doe');
    expect($gift->gift_type)->toBe('pet');
    expect((float) $gift->gift_value)->toBe(50000.0);
});

it('create_estate_gift blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);
    $result = app(CoordinatingAgent::class)->executeTool('create_estate_gift', [
        'gift_date' => '2024-06-15', 'recipient' => 'X', 'gift_type' => 'pet', 'gift_value' => 100,
    ], $user);
    expect($result['blocked'])->toBeTrue();
});

it('estate handlers return shapes have no fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $a = app(CoordinatingAgent::class)->executeTool('create_asset', [
        'asset_name' => 'A', 'asset_type' => 'other', 'current_value' => 1,
    ], $user);
    $l = $this->executeCaptureToolWithEvidence('create_liability', [
        'liability_name' => 'L', 'liability_type' => 'loan', 'current_balance' => 1,
    ], $user);
    $g = app(CoordinatingAgent::class)->executeTool('create_estate_gift', [
        'gift_date' => '2024-01-01', 'recipient' => 'R', 'gift_type' => 'pet', 'gift_value' => 1,
    ], $user);

    foreach ([$a, $l, $g] as $r) {
        expect($r)->not->toHaveKey('action');
        expect($r)->not->toHaveKey('fields');
    }
});
