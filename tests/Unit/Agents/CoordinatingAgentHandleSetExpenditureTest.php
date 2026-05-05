<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\ExpenditureProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Covers PRD FR-M12 (F1) — post-onboarding `handleSetExpenditure` must sync
 * the total to `ExpenditureProfile.total_monthly_expenditure` alongside the
 * existing `users.monthly_expenditure` write.
 *
 * Same bug pattern as onboarding §4 (fixed in commit 88018a5), different
 * layer: the dashboard expenditure widget reads from `ExpenditureProfile`
 * so without this sync, Fyn reports "captured" while the dashboard still
 * shows a blank card.
 *
 * PRD: April/April20Updates/PRD-fyn-driven-onboarding.md §FR-M12
 */
function invokeSetExpenditure(User $user, array $input, bool $isPreview = false): array
{
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionMethod($agent, 'handleSetExpenditure');
    $reflection->setAccessible(true);

    return $reflection->invoke($agent, $input, $user, $isPreview);
}

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

describe('handleSetExpenditure → ExpenditureProfile sync (FR-M12)', function () {
    it('writes total_monthly_expenditure to ExpenditureProfile on first call', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        $result = invokeSetExpenditure($user, [
            'rent' => 1500,
            'utilities' => 300,
            'food_groceries' => 400,
        ]);

        expect($result['updated'] ?? false)->toBeTrue()
            ->and($result['total_monthly'])->toBe(2200.0);

        $user->refresh();
        expect((float) $user->monthly_expenditure)->toBe(2200.0);

        $profile = ExpenditureProfile::where('user_id', $user->id)->first();
        expect($profile)->not->toBeNull()
            ->and((float) $profile->total_monthly_expenditure)->toBe(2200.0);
    });

    it('updates the existing ExpenditureProfile row on second call (no duplicate)', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        invokeSetExpenditure($user, ['rent' => 1500]);
        invokeSetExpenditure($user, ['rent' => 1800, 'utilities' => 300]);

        $profiles = ExpenditureProfile::where('user_id', $user->id)->get();
        expect($profiles)->toHaveCount(1)
            ->and((float) $profiles->first()->total_monthly_expenditure)->toBe(2100.0);
    });

    it('does not write to ExpenditureProfile when no amounts are provided', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        $result = invokeSetExpenditure($user, []);

        expect($result['error'] ?? false)->toBeTrue();
        expect(ExpenditureProfile::where('user_id', $user->id)->exists())->toBeFalse();
    });

    it('blocks preview users and leaves ExpenditureProfile untouched', function () {
        $user = User::factory()->create(['is_preview_user' => true]);

        $result = invokeSetExpenditure($user, ['rent' => 1000], isPreview: true);

        expect($result['blocked'] ?? false)->toBeTrue();
        expect(ExpenditureProfile::where('user_id', $user->id)->exists())->toBeFalse();
    });
});
