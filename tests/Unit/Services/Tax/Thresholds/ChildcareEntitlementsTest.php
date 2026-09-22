<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Tax\Thresholds\ChildcareEntitlements;
use App\Services\TaxConfigService;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    Carbon::setTestNow('2026-09-21');
    $this->entitlements = app(ChildcareEntitlements::class);
});

afterEach(fn () => Carbon::setTestNow());

function thresholdChild(User $user, string $dob): FamilyMember
{
    return FamilyMember::create(['user_id' => $user->id, 'first_name' => 'Kid', 'last_name' => 'Test', 'relationship' => 'child', 'date_of_birth' => $dob]);
}

it('returns nothing for a household with no children', function () {
    $user = User::factory()->create(['childcare' => 800]);
    expect($this->entitlements->for($user))->toBe([]);
});

it('caps Tax-Free Childcare per child and prices the extended hours by age', function () {
    $user = User::factory()->create(['childcare' => 1000]); // monthly
    thresholdChild($user, '2023-03-01'); // three
    thresholdChild($user, '2018-01-01'); // eight

    $items = $this->entitlements->for($user);
    $labels = array_column($items, 'label');

    expect($labels)->toContain('Tax-Free Childcare')->toContain('Funded childcare hours');
    $tfc = collect($items)->firstWhere('label', 'Tax-Free Childcare');
    // 25% of £12,000 = £3,000, under the £4,000 cap for two children.
    expect($tfc['amount'])->toBe(3000.0);
    $hours = collect($items)->firstWhere('label', 'Funded childcare hours');
    // The three-year-old's extra 15 hours × 38 weeks × the seeded rate for the active year.
    $rate = (float) app(TaxConfigService::class)->getEarlyYearsFunding()['working_parents_30hrs']['hourly_rate'];
    expect($hours['amount'])->toBe(round(15 * 38 * $rate, 2));
    expect($hours['detail'])->toContain('30 hours drops to 15 for your 3-year-old');
});

it('bands children at the exact month boundaries using the configured ages, not literal cutoffs', function () {
    $funding = app(TaxConfigService::class)->getEarlyYearsFunding();
    $priced = fn (string $band, bool $extensionOnly = false): float => round(
        ((float) $funding[$band]['hours_per_week'] - ($extensionOnly ? (float) $funding['universal_15hrs']['hours_per_week'] : 0))
            * (float) $funding[$band]['weeks_per_year']
            * (float) $funding[$band]['hourly_rate'],
        2
    );

    $u30 = User::factory()->create(['childcare' => null]);
    thresholdChild($u30, Carbon::today()->subMonths(36)->toDateString());
    $item30 = collect($this->entitlements->for($u30))->firstWhere('label', 'Funded childcare hours');
    expect($item30['amount'])->toBe($priced('working_parents_30hrs', extensionOnly: true));

    $u2 = User::factory()->create(['childcare' => null]);
    thresholdChild($u2, Carbon::today()->subMonths(24)->toDateString());
    $item2 = collect($this->entitlements->for($u2))->firstWhere('label', 'Funded childcare hours');
    expect($item2['amount'])->toBe($priced('working_parents_2yr'));

    $uUnder2 = User::factory()->create(['childcare' => null]);
    thresholdChild($uUnder2, Carbon::today()->subMonths(9)->toDateString());
    $itemUnder2 = collect($this->entitlements->for($uUnder2))->firstWhere('label', 'Funded childcare hours');
    expect($itemUnder2['amount'])->toBe($priced('working_parents_under_2'));

    $uNone = User::factory()->create(['childcare' => null]);
    thresholdChild($uNone, Carbon::today()->subMonths(60)->toDateString());
    expect(collect($this->entitlements->for($uNone))->firstWhere('label', 'Funded childcare hours'))->toBeNull();
});

it('gives no Tax-Free Childcare when no spend is recorded', function () {
    $user = User::factory()->create(['childcare' => null]);
    thresholdChild($user, '2020-01-01');

    expect(collect($this->entitlements->for($user))->firstWhere('label', 'Tax-Free Childcare'))->toBeNull();
});

it('applies the higher cap and the later age limit to a disabled child, from the seeded figures', function () {
    $tfc = app(TaxConfigService::class)->getTaxFreeChildcare();
    $user = User::factory()->create(['childcare' => 1500]); // £18,000 a year
    $fourteen = thresholdChild($user, '2012-06-01');
    $fourteen->update(['is_disabled' => true]);
    thresholdChild($user, '2012-06-01'); // the same age, no disability: past the ordinary limit

    $item = collect($this->entitlements->for($user))->firstWhere('label', 'Tax-Free Childcare');

    // Only the disabled fourteen-year-old qualifies, at the disabled cap.
    expect($item)->not->toBeNull()
        ->and($item['amount'])->toBe(round(min(18000 * (float) $tfc['government_top_up_rate'], (float) $tfc['max_disabled_contribution']), 2))
        ->and($item['detail'])->toContain('One child eligible')
        ->and($item['detail'])->toContain('£'.number_format((float) $tfc['max_disabled_contribution']).' for a disabled child');
});
