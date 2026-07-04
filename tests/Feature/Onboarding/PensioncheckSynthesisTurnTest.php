<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(RetirementActionDefinitionSeeder::class);
});

function pensioncheckSynthesisAdvice(User $user): ?string
{
    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'buildSectionAdvice');
    $ref->setAccessible(true);

    return $ref->invoke($director, $user, 'synthesis');
}

it('voices bullets mirroring the composed retirement plan (no numbering, no locked tease)', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1980-05-15',
        'marital_status' => 'single',
        'annual_employment_income' => 50000,
        'employment_status' => 'full_time',
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);
    RetirementProfile::factory()->create([
        'user_id' => $user->id,
        'current_age' => 45,
        'target_retirement_age' => 65,
        'target_retirement_income' => 30000,
    ]);
    // Zero-contribution DC pension fires Start_contributions → increase_pension_contribution.
    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_type' => 'workplace',
        'current_fund_value' => 50000,
        'employee_contribution_percent' => 0,
        'employer_contribution_percent' => 0,
        'monthly_contribution_amount' => 0,
    ]);

    $text = pensioncheckSynthesisAdvice($user->fresh());

    expect($text)->not->toBeNull()
        ->and($text)->toContain('- ')                              // markdown bullets
        ->and($text)->toContain('pension picture')                 // pensioncheck lead-in
        ->and($text)->toContain('qualified financial adviser')     // FCA signposting
        ->and($text)->not->toContain('tax plan')                   // NOT the savetax lead-in
        ->and($text)->not->toMatch('/^\d+\./m')                    // no numbered bullets
        ->and($text)->not->toContain('One more strategy is waiting'); // no locked tease (PR #592)
});

it('uses the pensioncheck lead-in not the savetax lead-in', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1980-05-15',
        'marital_status' => 'single',
        'annual_employment_income' => 50000,
        'employment_status' => 'full_time',
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);
    RetirementProfile::factory()->create([
        'user_id' => $user->id,
        'current_age' => 45,
        'target_retirement_age' => 65,
        'target_retirement_income' => 30000,
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_type' => 'workplace',
        'current_fund_value' => 50000,
        'employee_contribution_percent' => 0,
        'employer_contribution_percent' => 0,
        'monthly_contribution_amount' => 0,
    ]);

    $text = pensioncheckSynthesisAdvice($user->fresh());

    expect($text)->not->toBeNull()
        ->and($text)->toContain("Here's your pension picture, built from what you told me");
});

it('omits the combined-saving line when retirement items carry no £ aggregate', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1980-05-15',
        'marital_status' => 'single',
        'annual_employment_income' => 50000,
        'employment_status' => 'full_time',
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);
    RetirementProfile::factory()->create([
        'user_id' => $user->id,
        'current_age' => 45,
        'target_retirement_age' => 65,
        'target_retirement_income' => 30000,
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_type' => 'workplace',
        'current_fund_value' => 50000,
        'employee_contribution_percent' => 0,
        'employer_contribution_percent' => 0,
        'monthly_contribution_amount' => 0,
    ]);

    $text = pensioncheckSynthesisAdvice($user->fresh());

    // Retirement items have no estimated_annual_tax_saved — combined saving is 0.
    // The "Together these are worth roughly £X a year" line must not appear.
    expect($text)->not->toBeNull()
        ->and($text)->not->toContain('Together these are worth');
});

it('persists the pensioncheck synthesis to ai_messages', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1980-05-15',
        'marital_status' => 'single',
        'annual_employment_income' => 50000,
        'employment_status' => 'full_time',
        'onboarding_fyn_selection' => 'pensioncheck',
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS,
        'onboarding_fyn_path' => 'campaign',
    ]);
    RetirementProfile::factory()->create([
        'user_id' => $user->id,
        'current_age' => 45,
        'target_retirement_age' => 65,
        'target_retirement_income' => 30000,
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_type' => 'workplace',
        'current_fund_value' => 50000,
        'employee_contribution_percent' => 0,
        'employer_contribution_percent' => 0,
        'monthly_contribution_amount' => 0,
    ]);

    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);
    $stateId = OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS;
    $state = OnboardingStateMachine::getState($stateId);

    $m = new ReflectionMethod($director, 'emitAdviceTurn');
    $m->setAccessible(true);
    $events = iterator_to_array($m->invoke($director, $user->fresh(), $conv, $stateId, $state), false);

    $voiced = collect($events)->firstWhere('type', 'content')['text'] ?? null;

    $row = AiMessage::where('conversation_id', $conv->id)
        ->where('role', 'assistant')
        ->get()
        ->first(fn (AiMessage $msg): bool => ($msg->metadata['advice_section'] ?? null) === 'synthesis');

    expect($voiced)->not->toBeNull()
        ->and($row)->not->toBeNull()
        ->and($row->content)->toBe($voiced)
        ->and($row->metadata['onboarding_step'])->toBe($stateId);
});

it('degrades to a sensible closing line for a pensioncheck user with no retirement data', function () {
    // D1 Fix 6 — this previously returned null (silent synthesis turn). The final
    // recap turn must never fall silent; with no strategies it degrades to an
    // honest forward-looking line instead.
    $user = User::factory()->create([
        'onboarding_fyn_selection' => 'pensioncheck',
        'annual_employment_income' => 0,
        'first_name' => 'Robin',
    ]);

    $text = pensioncheckSynthesisAdvice($user->fresh());

    expect($text)->not->toBeNull()
        ->and($text)->toContain('Robin')
        ->and($text)->toContain('retirement')
        ->and($text)->not->toContain('- '); // no bullet block when the plan is empty
});

it('savetax synthesis is byte-identical: still mirrors the tax plan for savetax users', function () {
    // Regression guard — savetax synthesis must be unchanged. This test is
    // deliberately parallel to CampaignSynthesisTurnTest to prove the savetax
    // path is untouched by the campaign-aware refactor.
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19',
        'marital_status' => 'married',
        'employment_status' => 'full_time',
        'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000,
        'household_calculation_mode' => 'single_earner_couple',
        'onboarding_fyn_selection' => null, // defaults to savetax path
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 81000, 'interest_rate' => 3.25,
    ]);

    // Reuse the savetax seeder that the original test depends on.
    $this->seed(TaxActionDefinitionSeeder::class);

    $text = pensioncheckSynthesisAdvice($user->fresh());

    expect($text)->not->toBeNull()
        ->and($text)->toContain('tax plan')         // savetax lead-in
        ->and($text)->not->toContain('pension picture') // NOT the pensioncheck lead-in
        ->and($text)->toContain('- ')
        ->and($text)->toContain('qualified financial adviser');
});

// D1 Fix 6 — empty-plan graceful degrade. In the live walk no pensions were
// written (the capture refusals), so the composed retirement plan was empty and
// buildSynthesisAdvice returned null → the synthesis turn was silent. With no
// applicable strategies the synthesis must still voice a sensible closing line,
// never silence.
it('pensioncheck synthesis degrades to a sensible line when the plan is empty', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1990-03-01',
        'marital_status' => 'single',
        'employment_status' => 'full_time',
        'annual_employment_income' => 24000,
        'onboarding_fyn_selection' => 'pensioncheck',
        'first_name' => 'Pat',
    ]);
    // No pensions, no retirement profile → composed plan has no items.

    $text = pensioncheckSynthesisAdvice($user->fresh());

    expect($text)->not->toBeNull()
        ->and(trim((string) $text))->not->toBe('')
        ->and($text)->toContain('Pat');
});

it('synthesis fallback voices a campaign-specific closing line, never silence', function () {
    $user = User::factory()->create(['first_name' => 'Sam']);
    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'synthesisFallbackMessage');
    $ref->setAccessible(true);

    $pensioncheck = $ref->invoke($director, $user, 'pensioncheck');
    $savetax = $ref->invoke($director, $user, 'savetax');

    expect($pensioncheck)->toContain('Sam')
        ->and($pensioncheck)->toContain('retirement')
        ->and($savetax)->toContain('Sam')
        ->and($savetax)->toContain('tax strategy')
        ->and($savetax)->not->toContain('retirement picture');
});
