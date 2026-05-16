<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

/**
 * Regression: AdvicePromptBuilder::buildExistingRecordsSummary must not
 * lazy-load the `jointOwner` relation.
 *
 * On staging (csjones.co/fynla, APP_ENV=staging) AppServiceProvider::boot
 * calls Model::preventLazyLoading(! app()->isProduction()) — TRUE on
 * staging. The shared $ownershipLabel closure falls back to
 * $record->jointOwner?->first_name when joint_owner_name is null. The
 * savings_accounts / investment_accounts tables have NO joint_owner_name
 * column at all, so for any joint account the fallback ALWAYS fires —
 * lazy-loading jointOwner and throwing LazyLoadingViolationException
 * while building the AI existing-records prompt. Property carries a
 * joint_owner_name column but still violates when it is null.
 *
 * NOTE on the >1 row requirement: Laravel only stamps the per-instance
 * `preventsLazyLoading` flag when a query hydrates MORE THAN ONE model
 * (Illuminate\Database\Eloquent\Builder::hydrate, count($items) > 1).
 * Real staging users have several accounts, so the guard engages there.
 * Each record type below therefore seeds two rows (one individual + one
 * joint) so the queried collection reproduces the staging condition.
 *
 * The fix eager-loads `jointOwner` on every collection that feeds the
 * shared ownership/value closures (savings via SavingsStore, plus
 * investments and properties). This test pins all three.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    Cache::flush();
    Model::preventLazyLoading(true);
});

afterEach(function () {
    Model::preventLazyLoading(true);
});

it('builds the existing-records prompt for joint savings / investment / property without lazy-loading jointOwner', function () {
    $owner = User::factory()->create(['first_name' => 'Primary', 'is_preview_user' => false]);
    $coOwner = User::factory()->create(['first_name' => 'Joanna', 'is_preview_user' => false]);

    // Two savings accounts so the queried collection has count > 1 and
    // Laravel stamps the preventsLazyLoading flag on each instance.
    // savings_accounts has no joint_owner_name column → the co-owner can
    // only resolve via the jointOwner relation. Joint ISAs are illegal,
    // so the joint account stays a non-ISA cash account.
    SavingsAccount::factory()->create([
        'user_id' => $owner->id,
        'ownership_type' => 'individual',
        'is_isa' => false,
        'account_name' => 'Solo Saver',
        'current_balance' => 5000,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => $coOwner->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.00,
        'is_isa' => false,
        'account_name' => 'Joint Easy Access Saver',
        'current_balance' => 12000,
    ]);

    // Two investment accounts (one joint, non-ISA GIA).
    InvestmentAccount::factory()->create([
        'user_id' => $owner->id,
        'ownership_type' => 'individual',
        'account_type' => 'gia',
        'current_value' => 30000,
    ]);
    InvestmentAccount::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => $coOwner->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.00,
        'account_type' => 'gia',
        'current_value' => 80000,
    ]);

    // Two properties; the joint one has joint_owner_name explicitly null
    // so it must resolve the co-owner via the jointOwner relation.
    Property::factory()->create([
        'user_id' => $owner->id,
        'ownership_type' => 'individual',
        'property_type' => 'main_residence',
        'current_value' => 350000,
    ]);
    Property::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => $coOwner->id,
        'joint_owner_name' => null,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.00,
        'property_type' => 'buy_to_let',
        'current_value' => 500000,
    ]);

    $summary = app(AdvicePromptBuilder::class)->buildExistingRecordsSummary($owner);

    expect($summary)
        ->toContain('SAVINGS:')
        ->toContain('INVESTMENTS:')
        ->toContain('PROPERTIES:')
        // Co-owner name resolved through the eager-loaded jointOwner
        // relation (sanitiser wraps it but leaves a plain name intact).
        ->toContain('Joanna');
});
