<?php

declare(strict_types=1);

use App\Models\SpousePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W-0529 — one derivation of `$dataSharingEnabled`, because there were eight.
 *
 * **CSJ, 2026-08-29**, on whether `EstateAgent` should derive it from the permission the
 * way `IHTController` does: *"Yes it should."*
 *
 * The eight sites answered one question in six shapes. Two pooled on the link alone, so
 * Fyn pooled an estate the screen would not have and quoted a different figure from the
 * one the user was looking at. Three asked for consent without checking a spouse was
 * there to give it. The other three each spelled out "a spouse, and permission" again.
 *
 * These assert the rule itself. Every site now calls it, so a site that stops agreeing
 * with this is a site that stopped calling it.
 */
// `Unit/Models` is not bound in `tests/Pest.php`, so the application is not booted
// there and a factory call dies with "A facade root has not been set" — a test that
// cannot run rather than one that fails. Bound here rather than widening the global
// binding, which would hand `RefreshDatabase` to every other file in this directory.
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['marital_status' => 'married']);
    $this->spouse = User::factory()->create(['marital_status' => 'married']);

    $this->link = function (bool $reciprocal): void {
        $this->user->update(['spouse_id' => $this->spouse->id]);
        $this->spouse->update(['spouse_id' => $reciprocal ? $this->user->id : null]);
    };

    $this->permission = function (string $status): void {
        SpousePermission::create([
            'user_id' => $this->user->id,
            'spouse_id' => $this->spouse->id,
            'status' => $status,
            'requested_at' => now(),
            'responded_at' => $status === 'pending' ? null : now(),
        ]);
    };
});

it('does not pool without a spouse at all', function () {
    expect($this->user->fresh()->sharesFinancialDataWithSpouse())->toBeFalse();
});

it('does not pool on a link the other account never returned', function () {
    ($this->link)(reciprocal: false);
    ($this->permission)('accepted');

    expect($this->user->fresh()->sharesFinancialDataWithSpouse())->toBeFalse();
});

it('does not pool while the invitation is still pending', function () {
    // The `EstateAgent` defect CSJ ruled on: it pooled here, while every screen did not.
    // This is the live population on the development database — 8 of the 12 reciprocal
    // couples sit at `pending`, asked and unanswered.
    ($this->link)(reciprocal: true);
    ($this->permission)('pending');

    expect($this->user->fresh()->sharesFinancialDataWithSpouse())->toBeFalse();
});

it('does not pool once consent is refused', function () {
    // `rejected`, not `withdrawn`: the column is enum('pending','accepted','rejected'),
    // while `User::hasAcceptedSpousePermission()`'s docblock talks about withdrawal.
    // Terminology, not behaviour — but the enum is what the database will accept.
    ($this->link)(reciprocal: true);
    ($this->permission)('rejected');

    expect($this->user->fresh()->sharesFinancialDataWithSpouse())->toBeFalse();
});

it('pools on a reciprocal link with NO permission row, which is deliberate', function () {
    // `hasAcceptedSpousePermission()` FAILS OPEN when no row exists: a link predating
    // the consent flow is honoured, because since W-0347 a reciprocal link cannot be
    // created without someone accepting. Asserted so the default is visible rather than
    // incidental — it is the branch W-0347 G9 flagged as the one a future write path
    // could walk through. There are zero such rows on the development database today.
    ($this->link)(reciprocal: true);

    expect($this->user->fresh()->sharesFinancialDataWithSpouse())->toBeTrue();
});

it('pools when the link is returned and consent is given', function () {
    ($this->link)(reciprocal: true);
    ($this->permission)('accepted');

    expect($this->user->fresh()->sharesFinancialDataWithSpouse())->toBeTrue();
});

it('still resolves the spouse when pooling is off, so a couple never reads as single', function () {
    // The estate engine reads `$spouse` for `$isMarried`. Callers must keep passing it
    // even when this returns false, or a married couple reports as a single person —
    // the misleading artefact W-0154 recorded as a near-miss.
    ($this->link)(reciprocal: true);
    ($this->permission)('pending');

    $user = $this->user->fresh();

    expect($user->sharesFinancialDataWithSpouse())->toBeFalse()
        ->and($user->reciprocalLiveSpouse()?->id)->toBe($this->spouse->id);
});
