<?php

declare(strict_types=1);

use App\Models\Household;
use App\Models\User;
use App\Services\Onboarding\HouseholdProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B-2 — HouseholdProvisioner ensures a user has a household_id before
 * spouse / dependant / family_member rows are written. Without this, the
 * primary user AND the newly-linked spouse inherit NULL and every
 * household-scoped query silently returns nothing.
 */
beforeEach(function () {
    $this->provisioner = new HouseholdProvisioner;
});

it('creates a household when the user has none', function () {
    $user = User::factory()->create([
        'household_id' => null,
        'first_name' => 'Chris',
    ]);

    $id = $this->provisioner->ensureFor($user);

    expect($id)->toBeInt()->toBeGreaterThan(0);

    $user->refresh();
    expect($user->household_id)->toBe($id);

    $household = Household::find($id);
    expect($household)->not->toBeNull();
    expect($household->household_name)->toBe('Chris Household');
});

it('returns the existing household_id without creating a new one', function () {
    $existing = Household::create(['household_name' => 'Existing Household']);
    $user = User::factory()->create(['household_id' => $existing->id]);

    $countBefore = Household::count();

    $id = $this->provisioner->ensureFor($user);

    expect($id)->toBe($existing->id);
    expect(Household::count())->toBe($countBefore);
});

it('falls back to surname when first_name is missing', function () {
    // first_name is NOT NULL in the schema, but a stored empty string
    // still exercises the fallback branch.
    $user = User::factory()->create([
        'household_id' => null,
        'first_name' => '',
        'surname' => 'Jones',
    ]);

    $id = $this->provisioner->ensureFor($user);

    $household = Household::find($id);
    expect($household->household_name)->toBe('Jones Household');
});

it('is idempotent — two calls never double-create', function () {
    $user = User::factory()->create(['household_id' => null]);

    $first = $this->provisioner->ensureFor($user);
    $second = $this->provisioner->ensureFor($user->refresh());

    expect($first)->toBe($second);
});
