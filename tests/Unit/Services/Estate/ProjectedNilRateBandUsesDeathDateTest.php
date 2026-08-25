<?php

declare(strict_types=1);

use App\Models\Estate\Gift;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0361 — the projected nil rate band belongs to the projected date of death.
 *
 * `projected_nrb_available` reused the CURRENT column's band, whose gift deduction is
 * measured from today. A chargeable transfer made in 2020 therefore still consumed the
 * band at a death modelled decades away — long after IHTA 1984 s7(1) drops it out of
 * cumulation. It OVERSTATED projected tax.
 *
 * The docblock defending the reuse argued the band is "a statutory amount reduced by
 * chargeable transfers already made, neither of which is a function of the estate's
 * size". True, and beside the point: it is a function of the DATE OF DEATH, and the
 * two columns have different ones.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('does not charge the projected band for a gift that has dropped out of cumulation', function () {
    // Young enough that the projection runs decades out, so a gift made recently is
    // far outside the seven-year window at the modelled death.
    $user = User::factory()->create([
        'marital_status' => 'single',
        'date_of_birth' => '1990-01-01',
        'gender' => 'female',
    ]);

    Gift::factory()->create([
        'user_id' => $user->id,
        'gift_type' => 'clt',
        'gift_value' => 150_000,
        'gift_date' => now()->subYears(2),
    ]);

    $result = app(IHTCalculationService::class)->calculate($user->fresh(), null, false);

    // The current column is right to charge it — death today is inside the window.
    expect((float) $result['nrb_gift_deduction'])->toBeGreaterThan(0.0);

    // The projected column must not. Under the defect these two were the same number.
    expect((float) $result['projected_nrb_gift_deduction'])->toEqualWithDelta(0.0, 0.01);
    expect((float) $result['projected_nrb_available'])
        ->toBeGreaterThan((float) $result['nrb_available']);
});

it('still charges the projected band for someone whose death is modelled inside the window', function () {
    // Old enough that the modelled death is within the seven years following the gift.
    $user = User::factory()->create([
        'marital_status' => 'single',
        'date_of_birth' => '1930-01-01',
        'gender' => 'male',
    ]);

    Gift::factory()->create([
        'user_id' => $user->id,
        'gift_type' => 'clt',
        'gift_value' => 150_000,
        'gift_date' => now()->subYears(2),
    ]);

    $result = app(IHTCalculationService::class)->calculate($user->fresh(), null, false);

    expect((float) $result['projected_nrb_gift_deduction'])->toBeGreaterThan(0.0);
});
