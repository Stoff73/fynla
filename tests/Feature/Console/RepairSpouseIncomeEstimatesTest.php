<?php

declare(strict_types=1);

use App\Models\Employment;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Income\EmploymentIncomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Production 2026-09-29, C1: the repair for spouse income rows the onboarding
 * copy wrote before employments.is_estimate existed. Dry-run by default;
 * idempotent; soft-deletes only.
 */
uses(RefreshDatabase::class);

/**
 * A household exactly as production left it: the copied row is unnamed and
 * unflagged, created in the seconds before the transfer was stamped.
 *
 * @return array{0: User, 1: User, 2: Employment}
 */
function linkedWithCopiedIncome(float $copied): array
{
    $requester = User::factory()->create(['is_preview_user' => false, 'employment_status' => 'full_time']);
    $spouse = User::factory()->create(['is_preview_user' => false, 'employment_status' => 'full_time']);
    $requester->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $requester->id]);

    $stamp = now()->subDays(3);
    $row = Employment::create(['user_id' => $spouse->id, 'income_type' => 'employment', 'annual_income' => $copied, 'is_estimate' => false]);
    $row->timestamps = false;
    $row->forceFill(['created_at' => $stamp->copy()->subSeconds(5), 'updated_at' => $stamp->copy()->subSeconds(5)])->saveQuietly();

    TaxStrategyHouseholdInput::create(['user_id' => $requester->id, 'spouse_annual_income' => $copied, 'spouse_holding_transferred_at' => $stamp]);
    app(EmploymentIncomeService::class)->syncTotals($spouse);

    return [$requester->fresh(), $spouse->fresh(), $row->fresh()];
}

it('changes nothing without --force', function (): void {
    [, $spouse, $copied] = linkedWithCopiedIncome(32000.0);
    app(EmploymentIncomeService::class)->recordJob($spouse, 'Harbour Lane Primary School', 'Teacher', 32000.0);
    expect((float) $spouse->fresh()->annual_employment_income)->toBe(64000.0);

    $this->artisan('income:repair-spouse-estimates')
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('DOUBLED')
        ->assertSuccessful();

    expect((float) $spouse->fresh()->annual_employment_income)->toBe(64000.0)
        ->and($copied->fresh()->trashed())->toBeFalse();
});

it('removes the copied row where the spouse has since recorded their own job', function (): void {
    [$requester, $spouse, $copied] = linkedWithCopiedIncome(32000.0);
    app(EmploymentIncomeService::class)->recordJob($requester, 'Northwind Ltd', 'Engineer', 72000.0);
    app(EmploymentIncomeService::class)->recordJob($spouse, 'Harbour Lane Primary School', 'Teacher', 32000.0);
    expect((float) $spouse->fresh()->annual_employment_income)->toBe(64000.0);

    $this->artisan('income:repair-spouse-estimates', ['--force' => true])
        ->expectsOutputToContain('DOUBLED')
        ->assertSuccessful();

    $spouse->refresh();
    expect((float) $spouse->annual_employment_income)->toBe(32000.0)
        ->and($spouse->employments()->count())->toBe(1)
        ->and($spouse->employments()->first()->employer)->toBe('Harbour Lane Primary School')
        ->and(Employment::withTrashed()->find($copied->id)->trashed())->toBeTrue()
        ->and((float) $requester->fresh()->annual_employment_income)->toBe(72000.0);

    // A second run finds nothing.
    $this->artisan('income:repair-spouse-estimates', ['--force' => true])
        ->expectsOutputToContain('Nothing to repair')
        ->assertSuccessful();
});

it('flags the copied row as an estimate where it is still the only job', function (): void {
    [, $spouse, $copied] = linkedWithCopiedIncome(32000.0);

    $this->artisan('income:repair-spouse-estimates', ['--force' => true])
        ->expectsOutputToContain('PENDING')
        ->assertSuccessful();

    expect($copied->fresh()->is_estimate)->toBeTrue()
        ->and((float) $spouse->fresh()->annual_employment_income)->toBe(32000.0);

    // The deployed fix then replaces it rather than adding to it.
    app(EmploymentIncomeService::class)->recordJob($spouse->fresh(), 'Harbour Lane Primary School', 'Teacher', 32000.0);
    expect($spouse->fresh()->employments()->count())->toBe(1)
        ->and((float) $spouse->fresh()->annual_employment_income)->toBe(32000.0);
});

it('leaves a copied row the spouse has since edited, and says so', function (): void {
    [, $spouse, $copied] = linkedWithCopiedIncome(32000.0);
    $copied->forceFill(['annual_income' => 35000, 'updated_at' => now()])->saveQuietly();
    app(EmploymentIncomeService::class)->recordJob($spouse, 'Bluewater Tutoring', 'Tutor', 4000.0);

    $this->artisan('income:repair-spouse-estimates', ['--force' => true])
        ->expectsOutputToContain('SKIPPED')
        ->assertSuccessful();

    expect(Employment::withTrashed()->find($copied->id)->trashed())->toBeFalse()
        ->and($copied->fresh()->is_estimate)->toBeFalse()
        ->and((float) $spouse->fresh()->annual_employment_income)->toBe(39000.0);
});

it('ignores a named job created around the transfer and rows outside the window', function (): void {
    // Not a copy: named. The transfer never writes a name.
    [$requester, $spouse] = linkedWithCopiedIncome(32000.0);
    $spouse->employments()->delete();
    $stamp = TaxStrategyHouseholdInput::where('user_id', $requester->id)->value('spouse_holding_transferred_at');
    $named = Employment::create(['user_id' => $spouse->id, 'income_type' => 'employment', 'annual_income' => 32000, 'employer' => 'Own Ltd']);
    $named->forceFill(['created_at' => $stamp->copy()->subSeconds(5), 'updated_at' => $stamp->copy()->subSeconds(5)])->saveQuietly();
    // Not a copy: unnamed but created a day after the transfer, by the spouse.
    $later = Employment::create(['user_id' => $spouse->id, 'income_type' => 'employment', 'annual_income' => 9000]);
    $later->forceFill(['created_at' => $stamp->copy()->addDay(), 'updated_at' => $stamp->copy()->addDay()])->saveQuietly();

    $this->artisan('income:repair-spouse-estimates', ['--force' => true])
        ->expectsOutputToContain('Nothing to repair')
        ->assertSuccessful();

    expect(Employment::withTrashed()->where('user_id', $spouse->id)->whereNull('deleted_at')->count())->toBe(2);
});
