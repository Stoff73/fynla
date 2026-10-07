<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\StatePension;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Income\EmploymentIncomeService;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use App\Services\Onboarding\SpouseHoldingTransfer;
use App\Services\Onboarding\SpouseLinkingService;
use App\Services\Onboarding\WalkFormPrefill;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * CSJ 2026-09-16: the spouse facts held on the inviter's account during
 * onboarding are copied onto the spouse's account the moment it links, once.
 * The ISA is assumed to be a Stocks and Shares ISA.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    Mail::fake();
    Notification::fake();
});

afterEach(function (): void {
    Mockery::close();
});

it('copies the held spouse facts onto the linked account once', function (): void {
    $requester = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married', 'household_calculation_mode' => 'dual_earner']);
    $spouse = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => null, 'employment_status' => null, 'annual_employment_income' => null, 'annual_self_employment_income' => null]);
    TaxStrategyHouseholdInput::create([
        'user_id' => $requester->id, 'spouse_annual_income' => 32000, 'spouse_employment_status' => 'full_time',
        'spouse_isa_balance' => 9000, 'spouse_isa_provider' => 'Halifax',
        'spouse_pension_input_annual' => 2400, 'spouse_existing_pension_balance' => 31000, 'spouse_pension_provider' => 'Aviva',
        'spouse_existing_savings_balance' => 6500, 'spouse_existing_investment_balance' => 12000,
    ]);
    FamilyMember::create(['user_id' => $requester->id, 'relationship' => 'spouse', 'first_name' => 'Robin', 'last_name' => 'Walk', 'date_of_birth' => '1985-08-22', 'annual_income' => 38000]);

    app(SpouseLinkingService::class)->establishAcceptedLink($requester, $spouse);

    $spouse->refresh();
    expect($spouse->spouse_id)->toBe($requester->id)
        ->and($spouse->date_of_birth->format('Y-m-d'))->toBe('1985-08-22')
        ->and($spouse->employment_status)->toBe('full_time')
        ->and((float) $spouse->annual_employment_income)->toBe(32000.0);

    $savings = SavingsAccount::where('user_id', $spouse->id)->get();
    $investments = InvestmentAccount::where('user_id', $spouse->id)->get();
    $pensions = DCPension::where('user_id', $spouse->id)->get();
    expect($savings)->toHaveCount(1)
        ->and((float) $savings[0]->current_balance)->toBe(6500.0)
        ->and($investments)->toHaveCount(2)
        ->and((float) $investments->firstWhere('account_type', 'isa')->current_value)->toBe(9000.0)
        ->and($investments->firstWhere('account_type', 'isa')->provider)->toBe('Halifax')
        ->and((float) $investments->firstWhere('account_type', 'gia')->current_value)->toBe(12000.0)
        ->and($pensions)->toHaveCount(1)
        ->and($pensions[0]->provider)->toBe('Aviva')
        ->and((float) $pensions[0]->current_fund_value)->toBe(31000.0)
        ->and((float) $pensions[0]->monthly_contribution_amount)->toBe(200.0)
        ->and(TaxStrategyHouseholdInput::where('user_id', $requester->id)->first()->spouse_holding_transferred_at)->not->toBeNull();

    // A second link attempt copies nothing more.
    app(SpouseHoldingTransfer::class)->transfer($requester->fresh(), $spouse->fresh());
    expect(SavingsAccount::where('user_id', $spouse->id)->count())->toBe(1)
        ->and(InvestmentAccount::where('user_id', $spouse->id)->count())->toBe(2)
        ->and(DCPension::where('user_id', $spouse->id)->count())->toBe(1);
});

it('copies the journey spouse card alone when no household row was filled', function (): void {
    $requester = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married']);
    $spouse = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => null, 'employment_status' => 'full_time', 'annual_employment_income' => null]);
    FamilyMember::create(['user_id' => $requester->id, 'relationship' => 'spouse', 'first_name' => 'Robin', 'last_name' => 'Walk', 'date_of_birth' => '1985-08-22', 'annual_income' => 38000]);

    app(SpouseLinkingService::class)->establishAcceptedLink($requester, $spouse);

    $spouse->refresh();
    expect($spouse->date_of_birth->format('Y-m-d'))->toBe('1985-08-22')
        ->and((float) $spouse->annual_employment_income)->toBe(38000.0)
        ->and(SavingsAccount::where('user_id', $spouse->id)->count())->toBe(0);
});

/**
 * Pension relief is capped at relevant UK earnings (FA 2004 s189-190), and the
 * plan reads employment and self-employment income as those earnings. A
 * partner's income that is a pension or rent must not arrive as pay, or their
 * own plan can promise a Personal Allowance taper rescue relief law does not
 * give (a £25,100 contribution saving £15,060 at £125,140).
 */
function linkPartner(array $holding, ?string $spouseStatus = null): User
{
    $requester = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married', 'household_calculation_mode' => 'dual_earner']);
    $spouse = User::factory()->create(['is_preview_user' => false, 'employment_status' => $spouseStatus, 'annual_employment_income' => null, 'annual_self_employment_income' => null, 'annual_other_income' => null]);
    TaxStrategyHouseholdInput::create(['user_id' => $requester->id, ...$holding]);

    app(SpouseLinkingService::class)->establishAcceptedLink($requester, $spouse);

    return $spouse->refresh();
}

it('copies only the earnings from work as pay, and the rest as other income', function (): void {
    $spouse = linkPartner(['spouse_annual_income' => 125140, 'spouse_annual_earnings' => 40000, 'spouse_employment_status' => 'part_time']);

    expect((float) $spouse->annual_employment_income)->toBe(40000.0)
        ->and((float) $spouse->annual_other_income)->toBe(85140.0)
        ->and((float) ($spouse->annual_self_employment_income ?? 0))->toBe(0.0);
});

it('records nothing as drawn from a pension for a retired partner, and the household uses the figure given', function (): void {
    // Item 10 (CSJ 2026-10-07: "Partner's setup asks"): one figure cannot say
    // how much is State Pension, a final salary pension or drawn from a pot.
    // It was recorded as drawn from an invented personal pension.
    $spouse = linkPartner(['spouse_annual_income' => 125140, 'spouse_annual_earnings' => 0, 'spouse_employment_status' => 'retired']);

    expect((float) ($spouse->annual_employment_income ?? 0))->toBe(0.0)
        ->and((float) ($spouse->annual_self_employment_income ?? 0))->toBe(0.0)
        ->and((float) ($spouse->annual_other_income ?? 0))->toBe(0.0)
        ->and(DCPension::where('user_id', $spouse->id)->count())->toBe(0)
        ->and(app(SpouseHoldingTransfer::class)->inviterPensionIncome($spouse))->toBe(125140.0);

    // Pension income is not earnings: their own plan limits relief to the
    // basic amount (pension.relevant_earnings_minimum), not the £25,100 rescue.
    $basicAmount = (float) app(TaxConfigService::class)->getPensionAllowances()['relevant_earnings_minimum'];
    $rescue = collect(app(TaxStrategyCalculator::class)->calculate($spouse)->recommendations)->firstWhere('type', 'pa_taper_rescue');
    expect($rescue === null || (float) $rescue['suggested_contribution'] <= $basicAmount)->toBeTrue();
});

it('keeps the pension pot the inviter gave for a retired partner, with nothing recorded as drawn', function (): void {
    $spouse = linkPartner(['spouse_annual_income' => 30000, 'spouse_employment_status' => 'retired', 'spouse_existing_pension_balance' => 200000, 'spouse_pension_provider' => 'Aviva']);

    $pensions = DCPension::where('user_id', $spouse->id)->get();
    expect($pensions)->toHaveCount(1)
        ->and((float) $pensions[0]->current_fund_value)->toBe(200000.0)
        ->and((float) ($pensions[0]->annual_drawdown_income ?? 0))->toBe(0.0)
        ->and($pensions[0]->provider)->toBe('Aviva')
        ->and((float) ($spouse->annual_other_income ?? 0))->toBe(0.0);
});

it('copies a retired partner\'s earnings as pay and leaves the rest to their walk', function (): void {
    $spouse = linkPartner(['spouse_annual_income' => 30000, 'spouse_annual_earnings' => 10000, 'spouse_employment_status' => 'retired']);

    expect((float) $spouse->annual_employment_income)->toBe(10000.0)
        ->and(DCPension::where('user_id', $spouse->id)->count())->toBe(0)
        ->and((float) ($spouse->annual_other_income ?? 0))->toBe(0.0)
        ->and(app(SpouseHoldingTransfer::class)->inviterPensionIncome($spouse))->toBe(20000.0);
});

it('opens a retired partner\'s personal pension form with what is left after their State Pension and final salary pension', function (): void {
    // £30,000 given; £11,500 State Pension being paid and a £10,000 final
    // salary pension being paid leave £8,500 drawn from a pot.
    $spouse = linkPartner(['spouse_annual_income' => 30000, 'spouse_employment_status' => 'retired']);
    $spouse->forceFill(['date_of_birth' => '1955-03-10'])->save();
    $conversation = AiConversation::create(['user_id' => $spouse->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding', 'metadata' => ['source' => 'fyn_onboarding']]);
    $prefill = fn (): ?array => app(WalkFormPrefill::class)->for($spouse->fresh(), $conversation, CaptureForms::PENSION_PERSONAL);

    expect((float) $prefill()['values']['personal']['annual_drawdown_income'])->toBe(30000.0)
        ->and($prefill()['record'])->toBeNull();

    StatePension::create(['user_id' => $spouse->id, 'already_receiving' => true, 'state_pension_forecast_annual' => 11500]);
    DBPension::create(['user_id' => $spouse->id, 'scheme_name' => 'Council Pension', 'scheme_type' => 'final_salary', 'scheme_status' => 'in_payment', 'accrued_annual_pension' => 10000]);
    expect((float) $prefill()['values']['personal']['annual_drawdown_income'])->toBe(8500.0);

    // A final salary pension not yet paid is not part of today's income.
    DBPension::create(['user_id' => $spouse->id, 'scheme_name' => 'Old Employer', 'scheme_type' => 'final_salary', 'scheme_status' => 'deferred', 'accrued_annual_pension' => 4000]);
    expect((float) $prefill()['values']['personal']['annual_drawdown_income'])->toBe(8500.0);

    // Nothing left: the form opens blank.
    DBPension::create(['user_id' => $spouse->id, 'scheme_name' => 'Second Scheme', 'scheme_type' => 'career_average', 'scheme_status' => 'in_payment', 'accrued_annual_pension' => 8500]);
    expect($prefill())->toBeNull();
});

it('opens the transferred pot with what is left filled in', function (): void {
    $spouse = linkPartner(['spouse_annual_income' => 30000, 'spouse_employment_status' => 'retired', 'spouse_existing_pension_balance' => 200000, 'spouse_pension_provider' => 'Aviva']);
    $conversation = AiConversation::create(['user_id' => $spouse->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding', 'metadata' => ['source' => 'fyn_onboarding']]);

    $prefill = app(WalkFormPrefill::class)->for($spouse, $conversation, CaptureForms::PENSION_PERSONAL);

    expect((float) $prefill['values']['personal']['annual_drawdown_income'])->toBe(30000.0)
        ->and($prefill['record']['type'])->toBe('dc_pension');
});

it('lets the partner\'s employment status decide when earnings were not given (CSJ 2026-09-29)', function (): void {
    $retired = linkPartner(['spouse_annual_income' => 125140, 'spouse_employment_status' => 'retired']);
    expect((float) ($retired->annual_employment_income ?? 0))->toBe(0.0)
        ->and((float) ($retired->annual_other_income ?? 0))->toBe(0.0)
        ->and(app(SpouseHoldingTransfer::class)->inviterPensionIncome($retired))->toBe(125140.0);

    // Not working and not retired: the income could be rent or anything else.
    $unemployed = linkPartner(['spouse_annual_income' => 20000, 'spouse_employment_status' => 'unemployed']);
    expect((float) $unemployed->annual_other_income)->toBe(20000.0)
        ->and(DCPension::where('user_id', $unemployed->id)->count())->toBe(0);

    $selfEmployed = linkPartner(['spouse_annual_income' => 60000, 'spouse_employment_status' => 'self_employed']);
    expect((float) $selfEmployed->annual_self_employment_income)->toBe(60000.0)
        ->and((float) ($selfEmployed->annual_other_income ?? 0))->toBe(0.0);

    // Neither earnings nor status known: the figure the inviter gave is still
    // transferred (ruling 50; CSJ 2026-09-29), as an estimate of pay that the
    // partner's own job replaces rather than adds to.
    $unknown = linkPartner(['spouse_annual_income' => 125140]);
    expect((float) $unknown->annual_employment_income)->toBe(125140.0)
        ->and($unknown->employments()->where('is_estimate', true)->count())->toBe(1)
        ->and((float) ($unknown->annual_other_income ?? 0))->toBe(0.0);
});

it('drops the pay estimate when the partner then says they are retired, and their walk places the figure', function (): void {
    // fynla.org 2026-09-30: the inviter gave £30,000 and a £200,000 pot with no
    // status; the partner answered "Retired". The estimate stayed as pay
    // beside their pension: £60,000, higher rate, and NI.
    $spouse = linkPartner(['spouse_annual_income' => 30000, 'spouse_existing_pension_balance' => 200000, 'spouse_pension_provider' => 'Legal & General']);
    expect((float) $spouse->annual_employment_income)->toBe(30000.0);

    $spouse->update(['employment_status' => 'retired']);
    $spouse->refresh();

    expect((float) ($spouse->annual_employment_income ?? 0))->toBe(0.0)
        ->and($spouse->employments()->count())->toBe(0)
        ->and((float) ($spouse->annual_other_income ?? 0))->toBe(0.0);
    $pension = DCPension::where('user_id', $spouse->id)->sole();
    expect((float) ($pension->annual_drawdown_income ?? 0))->toBe(0.0)
        ->and((float) $pension->current_fund_value)->toBe(200000.0);

    // Their pension step opens the pot with the figure filled in, to confirm.
    $conversation = AiConversation::create(['user_id' => $spouse->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding', 'metadata' => ['source' => 'fyn_onboarding']]);
    $prefill = app(WalkFormPrefill::class)->for($spouse, $conversation, CaptureForms::PENSION_PERSONAL);
    expect((float) $prefill['values']['personal']['annual_drawdown_income'])->toBe(30000.0);
});

it('restates the estimate as other income when the partner then says they are not working', function (): void {
    $spouse = linkPartner(['spouse_annual_income' => 20000]);

    $spouse->update(['employment_status' => 'unemployed']);
    $spouse->refresh();

    expect((float) ($spouse->annual_employment_income ?? 0))->toBe(0.0)
        ->and((float) $spouse->annual_other_income)->toBe(20000.0)
        ->and(DCPension::where('user_id', $spouse->id)->count())->toBe(0);
});

it('keeps what a retired partner already said they draw, and drops the estimate', function (): void {
    $spouse = linkPartner(['spouse_annual_income' => 30000, 'spouse_existing_pension_balance' => 200000]);
    DCPension::where('user_id', $spouse->id)->sole()->update(['annual_drawdown_income' => 24000]);

    $spouse->update(['employment_status' => 'retired']);
    $spouse->refresh();

    expect((float) ($spouse->annual_employment_income ?? 0))->toBe(0.0)
        ->and((float) DCPension::where('user_id', $spouse->id)->sole()->annual_drawdown_income)->toBe(24000.0)
        ->and((float) ($spouse->annual_other_income ?? 0))->toBe(0.0);

    // What they draw is already on the record: the form does not overwrite it.
    $conversation = AiConversation::create(['user_id' => $spouse->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding', 'metadata' => ['source' => 'fyn_onboarding']]);
    $prefill = app(WalkFormPrefill::class)->for($spouse, $conversation, CaptureForms::PENSION_PERSONAL);
    expect((float) $prefill['values']['personal']['annual_drawdown_income'])->toBe(24000.0);
});

it('leaves the estimate for the partner\'s own job when they say they work', function (): void {
    $spouse = linkPartner(['spouse_annual_income' => 30000]);

    $spouse->update(['employment_status' => 'full_time']);
    $spouse->refresh();

    expect((float) $spouse->annual_employment_income)->toBe(30000.0)
        ->and($spouse->employments()->where('is_estimate', true)->count())->toBe(1);
});

it('reports the other income among what it copied', function (): void {
    $requester = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married']);
    $spouse = User::factory()->create(['is_preview_user' => false, 'employment_status' => null, 'annual_employment_income' => null, 'annual_self_employment_income' => null, 'annual_other_income' => null]);
    TaxStrategyHouseholdInput::create(['user_id' => $requester->id, 'spouse_annual_income' => 50000, 'spouse_annual_earnings' => 20000, 'spouse_employment_status' => 'part_time']);

    expect(app(SpouseHoldingTransfer::class)->transfer($requester, $spouse))->toContain('income', 'other income');
});

/*
 * Production 2026-09-29 (docs/testing/2026-09-29-prod-savetax-mobile-couple.md,
 * C1): the inviter's estimate of the spouse's income was copied across as an
 * unnamed job, then the spouse's own named job at the same £32,000 became a
 * second row and the two were summed — £64,000, taxed at the higher rate.
 * The copied figure is an estimate; the spouse's own figure replaces it.
 */
it('replaces the copied income estimate with the spouse\'s own job instead of adding to it', function (float $estimate, float $own): void {
    $requester = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married', 'household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 72000]);
    $spouse = User::factory()->create(['is_preview_user' => false, 'employment_status' => null, 'annual_employment_income' => null, 'annual_self_employment_income' => null]);
    TaxStrategyHouseholdInput::create([
        'user_id' => $requester->id, 'spouse_annual_income' => $estimate, 'spouse_annual_earnings' => $estimate, 'spouse_employment_status' => 'full_time',
    ]);

    app(SpouseLinkingService::class)->establishAcceptedLink($requester, $spouse);
    expect((float) $spouse->fresh()->annual_employment_income)->toBe($estimate);

    // The spouse's own onboarding: the work form's one write.
    app(CoordinatingAgent::class)->executeTool('capture_work_details', [
        'employer' => 'Harbour Lane Primary School', 'occupation' => 'Teacher', 'annual_income' => $own,
    ], $spouse->fresh());

    $spouse->refresh();
    $jobs = $spouse->employments;
    expect($jobs)->toHaveCount(1)
        ->and($jobs->first()->employer)->toBe('Harbour Lane Primary School')
        ->and($jobs->first()->occupation)->toBe('Teacher')
        ->and((bool) $jobs->first()->is_estimate)->toBeFalse()
        ->and((float) $spouse->annual_employment_income)->toBe($own)
        ->and((float) $requester->fresh()->annual_employment_income)->toBe(72000.0);
})->with([
    'the same figure' => [32000.0, 32000.0],
    'a different figure' => [30000.0, 32000.0],
]);

it('keeps a second job the spouse adds after replacing the estimate', function (): void {
    $requester = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married']);
    $spouse = User::factory()->create(['is_preview_user' => false, 'employment_status' => 'full_time', 'annual_employment_income' => null]);
    TaxStrategyHouseholdInput::create(['user_id' => $requester->id, 'spouse_annual_income' => 32000]);

    app(SpouseLinkingService::class)->establishAcceptedLink($requester, $spouse);
    $agent = app(CoordinatingAgent::class);
    $agent->executeTool('capture_work_details', ['employer' => 'Harbour Lane Primary School', 'occupation' => 'Teacher', 'annual_income' => 32000], $spouse->fresh());
    $agent->executeTool('capture_work_details', ['employer' => 'Bluewater Tutoring', 'occupation' => 'Tutor', 'annual_income' => 4000], $spouse->fresh());

    expect($spouse->fresh()->employments)->toHaveCount(2)
        ->and((float) $spouse->fresh()->annual_employment_income)->toBe(36000.0);
});

it('copies the rest when the income cannot be copied, instead of failing the link', function (): void {
    // The transfer runs after the link has committed; an income over the
    // capture_work_details cap is refused and logged, and savings still copy.
    $requester = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married']);
    $spouse = User::factory()->create(['is_preview_user' => false, 'employment_status' => null, 'annual_employment_income' => null, 'annual_self_employment_income' => null]);
    TaxStrategyHouseholdInput::create([
        // A working status, so the income is copied as pay and reaches the cap.
        'user_id' => $requester->id, 'spouse_annual_income' => EmploymentIncomeService::MAX_ANNUAL_INCOME + 1, 'spouse_employment_status' => 'full_time',
        'spouse_existing_savings_balance' => 6500,
    ]);

    app(SpouseLinkingService::class)->establishAcceptedLink($requester, $spouse);

    expect((float) ($spouse->fresh()->annual_employment_income ?? 0))->toBe(0.0)
        ->and(SavingsAccount::where('user_id', $spouse->id)->count())->toBe(1)
        ->and(TaxStrategyHouseholdInput::where('user_id', $requester->id)->first()->spouse_holding_transferred_at)->not->toBeNull();
});

it('asks a retired partner their State Pension, then final salary pensions, then the personal pension', function (): void {
    // Item 10 (CSJ 2026-10-07): before the personal pension form, which opens
    // with what is left of the figure their partner gave.
    $spouse = linkPartner(['spouse_annual_income' => 30000, 'spouse_employment_status' => 'retired', 'spouse_existing_pension_balance' => 200000, 'spouse_pension_provider' => 'Aviva']);
    $spouse->forceFill(['onboarding_completed' => false, 'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax', 'employment_status' => 'retired', 'date_of_birth' => '1958-03-10', 'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['pension']]])->save();

    expect(OnboardingStateMachine::getNextStateId(OnboardingStateMachine::STATE_CAMPAIGN_DOB, '10/03/1958', $spouse->fresh()))
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_RETIRED_STATE_PENSION)
        ->and(OnboardingStateMachine::getNextStateId(OnboardingStateMachine::STATE_CAMPAIGN_RETIRED_STATE_PENSION, '', $spouse->fresh()))
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_RETIRED_DB_PENSION)
        ->and(OnboardingStateMachine::getNextStateId(OnboardingStateMachine::STATE_CAMPAIGN_RETIRED_DB_PENSION, '', $spouse->fresh()))
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_PENSION_CONTRIBS);
});

it('takes a retired partner who ticked no pension to the personal pension form only while part of the figure is unplaced', function (): void {
    $spouse = linkPartner(['spouse_annual_income' => 20000, 'spouse_employment_status' => 'retired']);
    $spouse->forceFill(['onboarding_completed' => false, 'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax', 'employment_status' => 'retired', 'date_of_birth' => '1955-03-10', 'funnel_answers' => ['campaign' => 'savetax', 'assets' => []]])->save();

    expect(OnboardingStateMachine::getNextStateId(OnboardingStateMachine::STATE_CAMPAIGN_RETIRED_DB_PENSION, '', $spouse->fresh()))
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_PENSION_CONTRIBS);

    StatePension::create(['user_id' => $spouse->id, 'already_receiving' => true, 'state_pension_forecast_annual' => 12000]);
    DBPension::create(['user_id' => $spouse->id, 'scheme_name' => 'Council Pension', 'scheme_type' => 'final_salary', 'scheme_status' => 'in_payment', 'accrued_annual_pension' => 8000]);

    expect(OnboardingStateMachine::getNextStateId(OnboardingStateMachine::STATE_CAMPAIGN_RETIRED_DB_PENSION, '', $spouse->fresh()))
        ->not->toBe(OnboardingStateMachine::STATE_CAMPAIGN_PENSION_CONTRIBS)
        ->not->toBe(OnboardingStateMachine::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME);
});

it('asks no retired-partner steps of someone whose partner gave no figure', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => false, 'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax', 'employment_status' => 'retired', 'date_of_birth' => '1958-03-10', 'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['pension']]]);

    expect(OnboardingStateMachine::getNextStateId(OnboardingStateMachine::STATE_CAMPAIGN_DOB, '10/03/1958', $user))
        ->not->toBe(OnboardingStateMachine::STATE_CAMPAIGN_RETIRED_STATE_PENSION);
});

it('uses the figure given for a retired partner in the household plan until their records hold income', function (): void {
    $spouse = linkPartner(['spouse_annual_income' => 30000, 'spouse_employment_status' => 'retired']);

    // No income on their records yet, so the linked records are not the answer.
    expect(app(TaxStrategyMath::class)->linkedSpouseWithIncome($spouse->liveSpouse()))->toBeNull();
});

it('walks a retired partner through the State Pension and final salary forms with no model call', function (): void {
    $spouse = linkPartner(['spouse_annual_income' => 30000, 'spouse_employment_status' => 'retired']);
    $spouse->forceFill(['onboarding_completed' => false, 'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax', 'employment_status' => 'retired', 'date_of_birth' => '1955-03-10',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_RETIRED_STATE_PENSION, 'funnel_answers' => ['campaign' => 'savetax', 'assets' => []]])->save();
    $conversation = AiConversation::create(['user_id' => $spouse->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding', 'metadata' => ['source' => 'fyn_onboarding']]);
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);

    $emitted = iterator_to_array($director->emitTurnForState($spouse->fresh(), $conversation, OnboardingStateMachine::STATE_CAMPAIGN_RETIRED_STATE_PENSION, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_RETIRED_STATE_PENSION)), false);
    expect(collect($emitted)->firstWhere('type', 'capture_form')['form']['name'])->toBe(CaptureForms::STATE_PENSION);

    $submit = function (array $form) use ($spouse, $conversation, $director): array {
        FynStreamHarness::fake()->bind(); // no turns queued: any model call fails the test

        return iterator_to_array($director->handleUserMessage($spouse->fresh(), $conversation, CaptureForms::summarise($form), null, true, $form), false);
    };

    $submit(['name' => CaptureForms::STATE_PENSION, 'answers' => [CaptureForms::LEAD => ['already_receiving' => 'yes', 'forecast_annual' => 11500]]]);
    expect((float) StatePension::where('user_id', $spouse->id)->sole()->state_pension_forecast_annual)->toBe(11500.0)
        ->and($spouse->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_RETIRED_DB_PENSION);

    $events = $submit(['name' => CaptureForms::DB_PENSION, 'answers' => ['final_salary' => [
        'scheme_name' => 'Council Pension', 'scheme_status' => 'in_payment', 'accrued_annual_pension' => 10000, 'pensionable_service_years' => 25,
        'inflation_protection' => 'fixed', 'revaluation_rate' => 2.5,
    ]]]);
    $db = DBPension::where('user_id', $spouse->id)->sole();
    expect($db->scheme_type)->toBe('final_salary')
        ->and($db->scheme_status)->toBe('in_payment')
        ->and((float) $db->accrued_annual_pension)->toBe(10000.0)
        ->and($db->revaluation_method)->toBe('2.5%');

    // £30,000 less £11,500 and £10,000: the personal pension form opens with £8,500.
    $form = collect($events)->firstWhere('type', 'capture_form');
    expect($spouse->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_PENSION_CONTRIBS)
        ->and($form['form']['name'])->toBe(CaptureForms::PENSION_PERSONAL)
        ->and((float) $form['values']['personal']['annual_drawdown_income'])->toBe(8500.0);
});

it('takes "none" on the final salary form as the answer', function (): void {
    $form = ['name' => CaptureForms::DB_PENSION, 'answers' => []];

    expect(CaptureForms::toolInputs($form))->toBe([])
        ->and(CaptureForms::summarise($form))->toBe('I have none of these.');
});
