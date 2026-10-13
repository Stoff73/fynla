<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\DBPension;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use App\Services\Onboarding\RecordEditForms;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * T24 (walk 2026-10-11, RC7): Fyn's forms dropped facts the database has
 * columns for. Brett gave Premium Bonds and a daughter's Junior ISA (#3790),
 * an interest-only mortgage with BM Solutions at 5.2%, a letting agent and a
 * service charge (#3985), a life policy written in trust (#4021); none had a
 * box. Each now has one, written through the record's own handler.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
});

function t24User(string $step): User
{
    return User::factory()->create([
        'is_preview_user' => false, 'onboarding_completed' => false, 'first_name' => 'Priya', 'marital_status' => 'married',
        'employment_status' => 'full_time', 'annual_employment_income' => 98000,
        'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_step' => $step, 'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => ['campaign' => 'savetax', 'income' => '50271_100000', 'assets' => ['savings', 'property', 'pension']],
    ]);
}

/** @param  array<string, array<string, mixed>>  $answers */
function t24Submit(User $user, string $name, array $answers): array
{
    FynStreamHarness::fake()->bind(); // no model call
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();
    $form = ['name' => $name, 'answers' => $answers];

    return iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, CaptureForms::summarise($form, $user), null, true, $form), false);
}

it('saves Premium Bonds and a child\'s Junior ISA from the savings form', function (): void {
    $user = t24User(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS);

    $events = t24Submit($user, CaptureForms::SAVINGS, [
        'premium_bonds' => ['current_value' => 10000],
        'junior_isa' => ['provider' => 'Fidelity', 'current_value' => 4200, 'child_first_name' => 'Maya', 'child_date_of_birth' => '2015-03-14'],
    ]);

    $bonds = SavingsAccount::where('user_id', $user->id)->where('account_type', 'premium_bonds')->sole();
    $jisa = SavingsAccount::where('user_id', $user->id)->where('account_type', 'junior_isa')->sole();
    expect(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and($bonds->institution)->toBe('NS&I')
        ->and((float) $bonds->current_balance)->toBe(10000.0)
        ->and($bonds->interest_rate)->toBeNull()
        ->and($bonds->ownership_type)->toBe('individual')
        ->and($jisa->institution)->toBe('Fidelity')
        ->and((bool) $jisa->is_isa)->toBeTrue()
        ->and($jisa->beneficiary_name)->toBe('Maya')
        ->and($jisa->beneficiary_dob->format('Y-m-d'))->toBe('2015-03-14');
});

it('saves the town, the mortgage details and the letting costs from the property form', function (): void {
    $user = t24User(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY);

    $events = t24Submit($user, CaptureForms::PROPERTY, [
        'buy_to_let' => [
            'current_value' => 320000, 'city' => 'Woking', 'mortgage_outstanding_balance' => 180000,
            'mortgage_lender' => 'BM Solutions', 'mortgage_type' => 'interest_only', 'mortgage_interest_rate' => 5.2,
            'mortgage_monthly_payment' => 780, 'mortgage_years_left' => 18,
            'monthly_rental_income' => 1450, 'managing_agent_fee' => 145, 'monthly_service_charge' => 150,
            'ownership_type' => 'joint', 'ownership_percentage' => 50,
        ],
    ]);

    $property = Property::where('user_id', $user->id)->sole();
    $mortgage = Mortgage::where('property_id', $property->id)->sole();
    expect(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and($property->city)->toBe('Woking')
        ->and((float) $property->managing_agent_fee)->toBe(145.0)
        ->and((float) $property->monthly_service_charge)->toBe(150.0)
        ->and($mortgage->lender_name)->toBe('BM Solutions')
        ->and($mortgage->mortgage_type)->toBe('interest_only')
        ->and((float) $mortgage->interest_rate)->toBe(5.2)
        ->and((float) $mortgage->monthly_payment)->toBe(780.0)
        ->and($mortgage->maturity_date->format('Y'))->toBe((string) now()->addYears(18)->year);
});

it('records a life policy written in trust', function (): void {
    $user = t24User(OnboardingStateMachine::STATE_JOURNEY_PROTECTION);

    t24Submit($user, CaptureForms::PROTECTION, [
        'life' => ['provider' => 'Aviva', 'sum_assured' => 500000, 'premium_amount' => 32, 'policy_term_years' => 20, 'in_trust' => 'yes'],
    ]);

    expect((bool) LifeInsurancePolicy::where('user_id', $user->id)->sole()->in_trust)->toBeTrue();
});

it('saves a final salary pension from the workplace pension step', function (): void {
    $user = t24User(OnboardingStateMachine::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME);

    $events = t24Submit($user, CaptureForms::PENSION, [
        'defined_benefit' => ['db_scheme_type' => 'final_salary', 'scheme_name' => 'NHS Pension Scheme', 'scheme_status' => 'active', 'accrued_annual_pension' => 9400, 'pensionable_service_years' => 12],
    ]);

    $db = DBPension::where('user_id', $user->id)->sole();
    expect(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and($db->scheme_name)->toBe('NHS Pension Scheme')
        ->and($db->scheme_type)->toBe('final_salary')
        ->and((float) $db->accrued_annual_pension)->toBe(9400.0);
});

it('opens a saved property with its town and mortgage details, saves a changed lender, and never asks the mortgage type', function (): void {
    $user = t24User(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY);
    t24Submit($user, CaptureForms::PROPERTY, [
        'main_residence' => ['current_value' => 725000, 'city' => 'Guildford', 'mortgage_outstanding_balance' => 310000,
            'mortgage_lender' => 'Nationwide', 'mortgage_type' => 'repayment', 'mortgage_interest_rate' => 4.1, 'ownership_type' => 'individual'],
    ]);
    $property = Property::where('user_id', $user->id)->sole();
    $forms = app(RecordEditForms::class);

    $form = $forms->formFor($user->fresh(), 'property', (int) $property->id);
    expect($form['answers']['main_residence'])->toMatchArray(['city' => 'Guildford', 'mortgage_lender' => 'Nationwide', 'mortgage_interest_rate' => 4.1])
        ->and($form['schema']['kinds'][0]['fields'])->not->toContain('mortgage_type');

    $answers = $form['answers'];
    $answers['main_residence']['mortgage_lender'] = 'Halifax';
    $result = $forms->update($user->fresh(), ['name' => CaptureForms::PROPERTY, 'answers' => $answers, 'record' => $form['record']], 0);

    expect($result['error'] ?? false)->toBeFalse()
        ->and(Mortgage::where('property_id', $property->id)->sole()->lender_name)->toBe('Halifax')
        ->and(Mortgage::where('property_id', $property->id)->sole()->mortgage_type)->toBe('repayment');
});

it('keeps a saved mortgage\'s maturity date when the years left are saved unchanged', function (): void {
    $user = t24User(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY);
    t24Submit($user, CaptureForms::PROPERTY, [
        'main_residence' => ['current_value' => 500000, 'mortgage_outstanding_balance' => 200000, 'mortgage_years_left' => 17, 'ownership_type' => 'individual'],
    ]);
    $property = Property::where('user_id', $user->id)->sole();
    $mortgage = Mortgage::where('property_id', $property->id)->sole();
    $maturity = now()->addYears(17)->subMonths(5)->format('Y-m-d');
    $mortgage->forceFill(['maturity_date' => $maturity])->save();
    $forms = app(\App\Services\Onboarding\RecordEditForms::class);

    $form = $forms->formFor($user->fresh(), 'property', (int) $property->id);
    expect($form['answers']['main_residence']['mortgage_years_left'])->toBe(17);
    $forms->update($user->fresh(), ['name' => CaptureForms::PROPERTY, 'answers' => $form['answers'], 'record' => $form['record']], 0);

    expect($mortgage->fresh()->maturity_date->format('Y-m-d'))->toBe($maturity);
});
