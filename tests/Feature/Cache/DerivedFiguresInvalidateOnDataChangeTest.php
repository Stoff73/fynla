<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\SavingsAccount;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * W-0239 — a figure must not outlive the data it describes.
 *
 * `MobileDashboardAggregator` caches the whole dashboard for 24 hours under
 * `mobile_dashboard_{id}` and the comment beside the constant said "invalidated
 * on data change". These tests are what that comment now has to earn.
 *
 * Every case here asserts on the KEY, not on a rebuilt figure, deliberately. A
 * test that reads the dashboard twice and compares numbers passes whenever the
 * numbers happen to match — including when nothing was invalidated and the
 * figure simply had not moved. The key is the mechanism; the figure is a
 * consequence.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->spouse = User::factory()->create();

    // spouse_id is not in User's $fillable — an update() array would be dropped
    // in silence, and every assertion below it would then pass for the wrong
    // reason.
    $this->owner->spouse_id = $this->spouse->id;
    $this->owner->save();
    $this->spouse->spouse_id = $this->owner->id;
    $this->spouse->save();

    expect($this->owner->fresh()->spouse_id)->toBe($this->spouse->id);
});

/**
 * Seed every key the assertions below look at, so a cleared key is proof of an
 * invalidation rather than proof the key was never written.
 */
function seedDerivedCacheKeys(array $userIds): void
{
    foreach ($userIds as $id) {
        Cache::put("mobile_dashboard_{$id}", ['sentinel' => true], 3600);
        Cache::put("investment_analysis_{$id}", ['sentinel' => true], 3600);
        Cache::put("savings_analysis_{$id}", ['sentinel' => true], 3600);
        Cache::put("protection_analysis_{$id}", ['sentinel' => true], 3600);
        Cache::put('net_worth:user_'.$id.':date_'.now()->toDateString(), ['sentinel' => true], 3600);
        Cache::put("mobile_module_savings_{$id}", ['sentinel' => true], 3600);
    }
}

describe('a write invalidates the recording owner', function () {
    it('clears the dashboard blob and the module analysis when a savings account changes', function () {
        $account = SavingsAccount::factory()->create([
            'user_id' => $this->owner->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        seedDerivedCacheKeys([$this->owner->id]);

        $account->update(['current_balance' => 12345.67]);

        expect(Cache::has("mobile_dashboard_{$this->owner->id}"))->toBeFalse()
            ->and(Cache::has("savings_analysis_{$this->owner->id}"))->toBeFalse()
            ->and(Cache::has("mobile_module_savings_{$this->owner->id}"))->toBeFalse();
    });

    it('clears the date-keyed net worth blob, which only the deleted NetWorthCacheObserver used to reach', function () {
        $account = SavingsAccount::factory()->create(['user_id' => $this->owner->id]);

        seedDerivedCacheKeys([$this->owner->id]);

        $account->update(['current_balance' => 999.00]);

        expect(Cache::has('net_worth:user_'.$this->owner->id.':date_'.now()->toDateString()))->toBeFalse();
    });

    it('clears investment_analysis, the key no invalidation path in the application cleared', function () {
        $account = InvestmentAccount::factory()->create(['user_id' => $this->owner->id]);

        seedDerivedCacheKeys([$this->owner->id]);

        $account->update(['current_value' => 50000.00]);

        expect(Cache::has("investment_analysis_{$this->owner->id}"))->toBeFalse();
    });

    it('clears on create and on delete, not only on update', function () {
        seedDerivedCacheKeys([$this->owner->id]);
        $account = SavingsAccount::factory()->create(['user_id' => $this->owner->id]);
        expect(Cache::has("mobile_dashboard_{$this->owner->id}"))->toBeFalse();

        seedDerivedCacheKeys([$this->owner->id]);
        $account->delete();
        expect(Cache::has("mobile_dashboard_{$this->owner->id}"))->toBeFalse();
    });
});

describe('a write invalidates the co-owner of the single shared record', function () {
    it('clears the joint owner when the joint record is written on the other account', function () {
        $account = SavingsAccount::factory()->create([
            'user_id' => $this->owner->id,
            'joint_owner_id' => $this->spouse->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
        ]);

        seedDerivedCacheKeys([$this->owner->id, $this->spouse->id]);

        $account->update(['current_balance' => 4500.00]);

        expect(Cache::has("mobile_dashboard_{$this->spouse->id}"))->toBeFalse()
            ->and(Cache::has("mobile_dashboard_{$this->owner->id}"))->toBeFalse();
    });
});

describe('a write invalidates the household where the record names no counterparty', function () {
    it('clears the spouse when a joint-life policy changes, which carries no joint_owner_id at all', function () {
        // life_insurance_policies has `joint_life` and no ownership columns, so
        // LifeCoverReach finds the second life assured through users.spouse_id
        // (W-0186). Following only user_id/joint_owner_id leaves that spouse's
        // protection figure stale — which is exactly what was observed.
        $policy = LifeInsurancePolicy::factory()->create([
            'user_id' => $this->owner->id,
            'joint_life' => true,
        ]);

        seedDerivedCacheKeys([$this->owner->id, $this->spouse->id]);

        $policy->update(['sum_assured' => 500000]);

        expect(Cache::has("mobile_dashboard_{$this->spouse->id}"))->toBeFalse()
            ->and(Cache::has("protection_analysis_{$this->spouse->id}"))->toBeFalse();
    });
});

describe('a write on a record with no user_id of its own', function () {
    it('clears the owner of the account a holding hangs off', function () {
        $account = InvestmentAccount::factory()->create(['user_id' => $this->owner->id]);

        $holding = Holding::factory()->create([
            'holdable_id' => $account->id,
            'holdable_type' => InvestmentAccount::class,
        ]);

        seedDerivedCacheKeys([$this->owner->id]);

        $holding->update(['current_value' => 25000.00]);

        expect(Cache::has("mobile_dashboard_{$this->owner->id}"))->toBeFalse()
            ->and(Cache::has("investment_analysis_{$this->owner->id}"))->toBeFalse();
    });
});

describe('an unrelated user is left alone', function () {
    it('does not clear a stranger who is neither owner, co-owner nor spouse', function () {
        $stranger = User::factory()->create();

        SavingsAccount::factory()->create(['user_id' => $this->owner->id]);

        seedDerivedCacheKeys([$stranger->id]);

        SavingsAccount::factory()->create(['user_id' => $this->owner->id]);

        expect(Cache::has("mobile_dashboard_{$stranger->id}"))->toBeTrue();
    });
});
