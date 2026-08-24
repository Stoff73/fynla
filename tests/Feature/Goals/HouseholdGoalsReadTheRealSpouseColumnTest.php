<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\User;
use App\Services\Goals\GoalsProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * W-0471 — three Goals consumers read a column that does not exist.
 *
 * `users` has `spouse_id`. It has never had `spouse_user_id`. A missing attribute
 * read off an Eloquent **model** returns null silently — unlike the same name in a
 * query builder, which throws — so `if ($household && $user->spouse_user_id)` was
 * false for every household that has one, and the joint branch never ran.
 *
 * Measured before the fix: user 16 has `spouse_id = 17` and
 * `spouse_user_id = NULL`.
 *
 * This is the silent half of the phantom-column family recorded in
 * `tests/CLAUDE.md`. The throwing half (`db_pensions.transfer_value`,
 * `mortgages.end_date`) was found long ago because it errors; these three read
 * perfectly naturally and simply do nothing.
 */
uses(RefreshDatabase::class);

it('has no spouse_user_id column, so nothing may read one', function () {
    // The premise. If someone ever adds the column, this test should be revisited
    // deliberately rather than the greps below quietly becoming meaningless.
    expect(Schema::hasColumn('users', 'spouse_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'spouse_user_id'))->toBeFalse();
});

it('reads the real column everywhere a household goal or life event is gathered', function () {
    // The sweep W-0471's acceptance asks for, scoped to the attribute that was
    // wrong. A model read of a non-existent attribute cannot fail at runtime, so a
    // source sweep is the only thing that can catch a regression here.
    $offenders = [];

    foreach (['app/Http/Controllers', 'app/Services', 'app/Agents'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir)));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            foreach (file($file->getPathname()) as $number => $line) {
                $trimmed = ltrim($line);

                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
                    continue;
                }

                // `->spouse_user_id` is the read. A response KEY of that name is
                // fine — `CoordinatingAgent` publishes one deliberately — so the
                // arrow is what distinguishes a column read from a payload field.
                if (str_contains($line, '->spouse_user_id')) {
                    $offenders[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($number + 1);
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('gathers a spouse household goal that was invisible before', function () {
    // The behavioural half. Without it the sweep above could pass while the branch
    // was removed entirely rather than corrected.
    $user = User::factory()->create(['marital_status' => 'married']);
    $spouse = User::factory()->create(['marital_status' => 'married']);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);

    Goal::factory()->create([
        'user_id' => $spouse->id,
        'show_in_household_view' => true,
        'status' => 'active',
    ]);

    $service = app(GoalsProjectionService::class);

    $gather = new ReflectionMethod($service, 'getGoalsForProjection');
    $gather->setAccessible(true);

    $household = $gather->invoke($service, $user->fresh(), true);
    $individual = $gather->invoke($service, $user->fresh(), false);

    // The spouse's household-visible goal appears in the household gather and not
    // in the individual one. Asserting BOTH, so a change that simply returns
    // everything cannot pass.
    expect($household)->toHaveCount(1)
        ->and($individual)->toHaveCount(0);
});

it('stops gathering the spouse once their account is deleted', function () {
    // `liveSpouseId()` rather than `spouse_id`: the raw column survives the partner
    // deleting their account, deliberately (retention, CSJ D1/D2 2026-08-19), and a
    // household view built on it would keep reading a closed account's goals.
    $user = User::factory()->create(['marital_status' => 'married']);
    $spouse = User::factory()->create(['marital_status' => 'married']);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);

    Goal::factory()->create([
        'user_id' => $spouse->id,
        'show_in_household_view' => true,
        'status' => 'active',
    ]);

    $spouse->delete();

    $service = app(GoalsProjectionService::class);
    $gather = new ReflectionMethod($service, 'getGoalsForProjection');
    $gather->setAccessible(true);

    expect($gather->invoke($service, $user->fresh(), true))->toHaveCount(0);
});
