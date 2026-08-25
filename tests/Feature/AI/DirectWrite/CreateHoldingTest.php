<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('create_holding persists a Holding row attached to the matching account', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $account = InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'provider' => 'Vanguard',
        'account_name' => 'Vanguard ISA',
        'current_value' => 20000,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_holding', [
        'account_name' => 'Vanguard',
        'security_name' => 'FTSE Global All Cap Index Fund',
        'asset_type' => 'fund',
        'allocation_percent' => 60,
        'ocf_percent' => 0.23,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['created'])->toBeTrue();
    expect($result['entity_type'])->toBe('investment_holding');
    expect($result['entity_id'])->toBeInt();

    $holding = Holding::find($result['entity_id']);
    expect($holding)->not->toBeNull();
    expect($holding->holdable_id)->toBe($account->id);
    expect($holding->holdable_type)->toBe(InvestmentAccount::class);
    expect($holding->security_name)->toBe('FTSE Global All Cap Index Fund');
    expect($holding->asset_type)->toBe('fund');
    expect((float) $holding->allocation_percent)->toBe(60.0);
    expect((float) $holding->current_value)->toBe(12000.0); // 60% of 20000
});

it('create_holding errors when no account matches', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_holding', [
        'account_name' => 'NonExistent',
        'security_name' => 'Some Fund',
        'asset_type' => 'fund',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue();
    expect(Holding::count())->toBe(0);
});

it('create_holding returns validation_failed on missing required field', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_holding', [
        'account_name' => 'Vanguard',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'])->toBe('validation_failed');
});

it('create_holding blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);

    $result = app(CoordinatingAgent::class)->executeTool('create_holding', [
        'account_name' => 'X',
        'security_name' => 'Y',
        'asset_type' => 'fund',
    ], $user);

    expect($result['blocked'])->toBeTrue();
});

it('create_holding return shape has no fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'provider' => 'Vanguard',
        'current_value' => 10000,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_holding', [
        'account_name' => 'Vanguard',
        'security_name' => 'X',
        'asset_type' => 'fund',
    ], $user);

    expect($result)->not->toHaveKey('action');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('route');
});

/**
 * W-0122 — `create_holding` reads the one valuation rule rather than carrying its own.
 *
 * The acceptance is that this path READS `App\Support\HoldingValuation`, not that it
 * happens to compute the same answer: a second mechanism agreeing today is still a
 * second mechanism, and it is the reason W-0121's fix did not reach every write path.
 * These assert the stored row, because the tool result would look identical either way.
 */
it('create_holding derives the unit count through the one shared valuation rule', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'provider' => 'Vanguard',
        'current_value' => 20000,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_holding', [
        'account_name' => 'Vanguard',
        'security_name' => 'Vanguard FTSE All-World',
        'asset_type' => 'etf',
        'allocation_percent' => 50,
        'current_price' => 100,
        'purchase_price' => 80,
    ], $user);

    $holding = Holding::findOrFail($result['entity_id']);

    // 50% of £20,000 is £10,000, and at £100 a unit that is 100 units — the same
    // back-calculation the API path has always done. Before this, a holding created
    // by talking to Fyn carried no unit count at all.
    expect((float) $holding->current_value)->toBe(10000.0)
        ->and((float) $holding->quantity)->toBe(100.0)
        ->and((float) $holding->cost_basis)->toBe(8000.0);
});

it('create_holding invents no unit count when nothing values the holding', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'provider' => 'Vanguard',
        'current_value' => 20000,
    ]);

    // No allocation and no price: nothing here says how much of the account this is
    // or what a unit costs, so a unit count would be fabricated rather than derived.
    $result = app(CoordinatingAgent::class)->executeTool('create_holding', [
        'account_name' => 'Vanguard',
        'security_name' => 'Unpriced Fund',
        'asset_type' => 'fund',
    ], $user);

    $holding = Holding::findOrFail($result['entity_id']);

    expect($holding->quantity)->toBeNull()
        // The NOT NULL column still needs a figure, and zero is the honest one.
        ->and((float) $holding->current_value)->toBe(0.0);
});
