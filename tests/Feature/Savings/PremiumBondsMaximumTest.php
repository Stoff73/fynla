<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Investment\Recommendation\ContributionWaterfallService;
use App\Services\Investment\Recommendation\UserContextBuilder;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsStore;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * T82: the Premium Bonds maximum (NS&I: "You are only able to hold up to
 * £50,000 in Premium Bonds") was checked only on the web form; the store took
 * any balance, and the contribution waterfall read a second key with a typed-in
 * fallback and assumed no bonds were held ("not tracked individually").
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => '1985-01-01']);
});

function premiumBonds(User $user, float $balance): SavingsAccount
{
    return app(SavingsStore::class)->create([
        'account_name' => 'NS&I Premium Bonds', 'account_type' => 'premium_bonds', 'institution' => 'NS&I',
        'current_balance' => $balance, 'ownership_type' => 'individual',
    ], $user, IngestSource::FYN_AI);
}

it('refuses a Premium Bonds holding over the configured maximum on every surface', function () {
    expect(fn () => premiumBonds($this->user, 60000))->toThrow(StoreValidationException::class);
    expect((float) premiumBonds($this->user, 50000)->current_balance)->toBe(50000.0);
});

it('counts the Premium Bonds already held, against the configured maximum', function () {
    premiumBonds($this->user, 50000);
    $context = app(UserContextBuilder::class)->build($this->user);
    expect($context['premium_bonds_holding'])->toBe(50000.0);

    $step = (new ReflectionMethod(ContributionWaterfallService::class, 'stepPremiumBonds'))
        ->invoke(app(ContributionWaterfallService::class), 10000.0, $context, [], app(TaxConfigService::class)->get('investment.waterfall', []));
    expect($step['skipped'] ?? null)->toBeTrue();
    expect($step['skip_reason'])->toBe('Premium Bonds holding at maximum of £50,000.');
});

// Found on the T82 /m walk: the refusal reached the user as "Validation failed
// for account update." The store's own words are what the user needs.
it('tells the user the store\'s reason when Fyn\'s change is refused', function () {
    $bonds = premiumBonds($this->user, 10000);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'savings_account', 'entity_id' => $bonds->id, 'fields' => ['current_balance' => 60000],
    ], $this->user);

    expect($result['error_type'] ?? null)->toBe('validation_failed')
        ->and($result['message'])->toBe('Premium Bonds have a maximum holding of £50,000 per person.');
});
