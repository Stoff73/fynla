<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0372 — the amendment copy restated a date the service had already read.
 *
 * `inheritance_tax.pension_iht_inclusion.effective_date` is parsed into
 * `$effectiveDate` and published as its own field, and then the sentence the user
 * actually reads said "From April 2027" as a literal. Move the configured date —
 * which is what a Budget does — and the figures follow it while the words do not.
 *
 * Same family as W-0371, filed apart because it is a date rather than a rate and a
 * sweep for `%` would not have found it.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function amendmentWithEffectiveDate(string $date): array
{
    // The tax year is ONE `config_data` array; there is no `inheritance_tax`
    // column, and writing to one is silently dropped because it is not fillable.
    $config = TaxConfiguration::where('is_active', true)->firstOrFail();
    $data = $config->config_data;
    $data['inheritance_tax']['pension_iht_inclusion']['effective_date'] = $date;
    $config->update(['config_data' => $data]);

    // The service caches the active config for the request and the store caches
    // the row behind it, so the edit above is invisible until both are dropped.
    app(TaxConfigService::class)->clearCache();
    app()->forgetInstance(IHTCalculationService::class);

    $user = User::factory()->create([
        'marital_status' => 'single',
        'date_of_birth' => '1955-01-01',
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 500_000,
    ]);

    return app(IHTCalculationService::class)->calculate($user, null, false)['pension_amendment'];
}

it('takes the amendment date in the copy from configuration, not a literal', function () {
    $a = amendmentWithEffectiveDate('2027-04-06');

    expect($a['effective_date'])->toBe('2027-04-06')
        ->and($a['post_2027_rules']['description'])->toContain('April 2027');
});

it('follows the configured date when it moves', function () {
    // A Budget defers the change by a year. The published field moves; the sentence
    // beside it must move with it or the user reads two different answers.
    $a = amendmentWithEffectiveDate('2028-04-06');

    expect($a['effective_date'])->toBe('2028-04-06')
        ->and($a['post_2027_rules']['description'])->toContain('April 2028')
        ->and($a['post_2027_rules']['description'])->not->toContain('April 2027')
        ->and($a['impact_summary'])->not->toContain('2027');
});
