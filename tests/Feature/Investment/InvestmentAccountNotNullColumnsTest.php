<?php

declare(strict_types=1);

use App\Http\Requests\StoreInvestmentAccountRequest;
use App\Http\Requests\UpdateInvestmentAccountRequest;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Stores\Normalisers\InvestmentAccountNormaliser;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * W-0052 — a null reaching a NOT NULL column 500'd every investment account create.
 *
 * The regression: `advisor_fee_percent` is NOT NULL DEFAULT '0.0000'. Adding a
 * `nullable` validation rule for it (W-0008) meant `validated()` stopped stripping
 * it, so the explicit null the form sends whenever the additional-information panel
 * is collapsed — the DEFAULT state a real user hits — reached the column.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    Sanctum::actingAs($this->user);
});

it('creates an investment account with the additional-information panel collapsed', function () {
    // Exactly what AccountForm.vue:965-972 sends when the panel is closed: every
    // collapsed field explicitly nulled. This is the payload that 500'd.
    $response = $this->postJson('/api/investment/accounts', [
        'account_type' => 'gia',
        'account_name' => 'General Investment Account',
        'provider' => 'AJ Bell',
        'current_value' => 50000.00,
        'ownership_type' => 'individual',
        'country' => null,
        'platform' => null,
        'planned_lump_sum_amount' => null,
        'planned_lump_sum_date' => null,
        'platform_fee_percent' => null,
        'platform_fee_amount' => null,
        'advisor_fee_percent' => null,
    ]);

    $response->assertCreated();

    $account = InvestmentAccount::where('user_id', $this->user->id)
        ->where('provider', 'AJ Bell')
        ->latest('id')
        ->firstOrFail();

    // The NOT NULL columns fall back to their database defaults rather than
    // rejecting the insert.
    expect((float) $account->advisor_fee_percent)->toEqual(0.0)
        ->and($account->country)->toBe('United Kingdom')
        ->and($account->platform_fee_type)->toBe('percentage')
        ->and($account->platform_fee_frequency)->toBe('annually');
});

it('updates an investment account with the panel collapsed', function () {
    // The update path took the same W-0008 rule and needs the same protection.
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $this->user->id,
        'advisor_fee_percent' => 0.75,
    ]);

    $this->putJson("/api/investment/accounts/{$account->id}", [
        'current_value' => 60000.00,
        'country' => null,
        'platform_fee_percent' => null,
        'advisor_fee_percent' => null,
    ])->assertOk();

    $account->refresh();

    // Dropping the key leaves the stored value alone, which is exactly what
    // happened before W-0008 added the rule. Collapsing a panel must not wipe a
    // figure the user cannot currently see.
    expect((float) $account->advisor_fee_percent)->toEqual(0.75)
        ->and($account->country)->not->toBeNull();
});

it('still stores an adviser fee that IS supplied', function () {
    // The W-0008 fix itself must survive its own regression fix.
    $this->postJson('/api/investment/accounts', [
        'account_type' => 'isa',
        'account_name' => 'Stocks and Shares ISA',
        'provider' => 'Hargreaves Lansdown',
        'current_value' => 85000.00,
        'ownership_type' => 'individual',
        'advisor_fee_percent' => 0.75,
    ])->assertCreated();

    $account = InvestmentAccount::where('user_id', $this->user->id)
        ->where('provider', 'Hargreaves Lansdown')
        ->latest('id')
        ->firstOrFail();

    expect((float) $account->advisor_fee_percent)->toEqual(0.75);
});

it('keeps the NOT NULL list in step with the actual schema', function () {
    // The guard that stops this recurring: if a NOT NULL column with a default is
    // added to investment_accounts and not listed, this fails rather than waiting
    // for a 500 in front of a user.
    // The invariant: every NOT NULL column a CLIENT CAN SEND must be null-dropped.
    // Scoping to the request rules is what makes this catch the actual mistake —
    // W-0008 added a `nullable` rule for a NOT NULL column, which is precisely the
    // event that moves a column into this set.
    $notNull = collect(DB::select(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND IS_NULLABLE = ?
           AND COLUMN_DEFAULT IS NOT NULL',
        ['investment_accounts', 'NO']
    ))->pluck('COLUMN_NAME');

    $reachable = array_merge(
        array_keys((new StoreInvestmentAccountRequest)->rules()),
        array_keys((new UpdateInvestmentAccountRequest)->rules()),
    );

    $actual = $notNull->intersect($reachable)->sort()->values()->all();

    $declared = collect(InvestmentAccountNormaliser::NOT_NULL_WITH_DEFAULT)->sort()->values()->all();

    expect($declared)->toBe($actual);
});
