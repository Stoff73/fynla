<?php

declare(strict_types=1);

use App\Models\Estate\IHTCalculation;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * Wave 2.4 — REVIEW §4 High #22.
 *
 * `IHTCalculationService::calculate()` used to unconditionally write a
 * row into `iht_calculations` on every call, including from read-only
 * flows (dashboards, advice queries, chat tool calls). Persistence is
 * now opt-in via `persist: true`.
 */

it('does NOT write to iht_calculations on the default read-only path', function () {
    $user = User::factory()->create();

    $before = IHTCalculation::where('user_id', $user->id)->count();

    app(IHTCalculationService::class)->calculate($user);

    $after = IHTCalculation::where('user_id', $user->id)->count();

    expect($after)->toBe($before);
});

it('writes one iht_calculations row when persist: true is opted in', function () {
    $user = User::factory()->create();

    expect(IHTCalculation::where('user_id', $user->id)->count())->toBe(0);

    app(IHTCalculationService::class)->calculate(
        $user,
        null,
        false,
        persist: true,
    );

    expect(IHTCalculation::where('user_id', $user->id)->count())->toBe(1);
});
