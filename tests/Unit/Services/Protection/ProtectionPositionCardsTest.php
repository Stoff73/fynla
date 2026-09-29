<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Coordination\PlanSources\ProtectionStrategySource;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(ProtectionActionDefinitionSeeder::class);
});

function mortgagedEarner(array $profile = []): User
{
    $user = User::factory()->create(['employment_status' => 'employed', 'annual_employment_income' => 72000, 'annual_self_employment_income' => 0,
        'annual_rental_income' => 0, 'annual_dividend_income' => 0, 'annual_other_income' => 0, 'annual_expenditure' => 36000,
        'date_of_birth' => now()->subYears(40)]);
    ProtectionProfile::factory()->create(array_merge(['user_id' => $user->id, 'annual_income' => 72000, 'monthly_expenditure' => 3000,
        'mortgage_balance' => 200000, 'other_debts' => 0, 'number_of_dependents' => 0, 'dependents_ages' => [],
        'employer_benefits_recorded_at' => now()], $profile));
    // The mortgage rules read the user's mortgage records (W-0227), not the profile.
    if (($profile['mortgage_balance'] ?? 200000) > 0) {
        $property = Property::factory()->create(['user_id' => $user->id, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
        Mortgage::factory()->create(['user_id' => $user->id, 'property_id' => $property->id, 'mortgage_type' => 'repayment',
            'outstanding_balance' => $profile['mortgage_balance'] ?? 200000, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
    }

    return $user->fresh();
}

function protectionTypes(User $user): array
{
    return collect(app(ProtectionStrategySource::class)->recommendations($user))->pluck('type')->all();
}

it('folds the overlapping gap cards into one card per cover type', function () {
    $types = protectionTypes(mortgagedEarner());

    expect($types)->toContain('life_cover_position', 'critical_illness_position', 'income_protection_position')
        ->and($types)->not->toContain('life_insurance_gap')
        ->and($types)->not->toContain('mortgage_no_decreasing_term')
        ->and($types)->not->toContain('critical_illness_gap')
        ->and($types)->not->toContain('no_ci_with_mortgage')
        ->and($types)->not->toContain('income_protection_gap')
        ->and($types)->not->toContain('ip_gap_after_state_benefits')
        // Separate cards stay.
        ->and($types)->toContain('no_policies_warning');
});

it('carries the position and every reason that fired to the card', function () {
    $life = collect(app(ProtectionStrategySource::class)->recommendations(mortgagedEarner()))->firstWhere('type', 'life_cover_position');

    expect($life->title)->toStartWith('Your life cover is £')
        ->and($life->extra['figures'])->toMatchArray(['is_short' => true, 'mortgage_no_decreasing_term' => true])
        ->and($life->extra['figures']['mortgage_amount'])->toStartWith('£')
        ->and($life->extra['figures'])->not->toHaveKey('gap_amount');
});

it('keeps the card id when the shortfall changes', function () {
    $user = mortgagedEarner();
    $before = collect(app(ProtectionStrategySource::class)->recommendations($user))->firstWhere('type', 'life_cover_position');
    $user->protectionProfile->update(['mortgage_balance' => 150000]);
    Mortgage::where('user_id', $user->id)->update(['outstanding_balance' => 150000]);
    $after = collect(app(ProtectionStrategySource::class)->recommendations($user->fresh()))->firstWhere('type', 'life_cover_position');

    expect($after->type)->toBe($before->type)->and($after->title)->not->toBe($before->title);
});

it('shows the life card when the cover depends on the job even with no shortfall', function () {
    // Death in service 20 x £72,000 = £1,440,000 covers the need, all of it through the job.
    $user = mortgagedEarner(['death_in_service_multiple' => 20, 'mortgage_balance' => 0]);
    $life = collect(app(ProtectionStrategySource::class)->recommendations($user))->firstWhere('type', 'life_cover_position');

    expect($life)->not->toBeNull()
        ->and($life->extra['figures'])->toMatchArray(['depends_on_job' => true, 'is_short' => false]);
});
