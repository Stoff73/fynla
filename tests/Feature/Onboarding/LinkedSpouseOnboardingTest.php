<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\DCPension;
use App\Models\Employment;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\SpousePermission;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Coordination\HouseholdFinancialContext;
use App\Services\GDPR\ConsentService;
use App\Services\Income\EmploymentIncomeService;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use App\Services\Onboarding\RecordEditForms;
use App\Services\Onboarding\SpouseLinkingService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * Production /m, 2026-09-29: Alex was invited by Sam and the accounts are
 * linked. Save Tax onboarding then asked Alex for Sam's earnings band, "Does
 * your spouse work?" and Sam's income by hand — all already on Sam's own
 * record, which Alex's profile shows through the link — and opened Alex's
 * own income form blank although Sam had entered Alex's salary.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    Mail::fake();
    Notification::fake();
    OnboardingStateMachine::flushTransitionTableCache();
});

afterEach(function (): void {
    Mockery::close();
});

/**
 * Sam (earning £72,000 at Acme) invited Alex and entered Alex's £32,000;
 * Alex registered from the link and is starting the Save Tax walk.
 *
 * @return array{0: User, 1: User}
 */
function linkedSpouseOnboardingCouple(float $samIncome = 72000, array $alexExtra = []): array
{
    $sam = User::factory()->create(['is_preview_user' => false, 'first_name' => 'Sam', 'marital_status' => 'married', 'employment_status' => 'full_time', 'household_calculation_mode' => 'dual_earner']);
    if ($samIncome > 0) {
        app(EmploymentIncomeService::class)->recordJob($sam, 'Acme', 'Engineer', $samIncome);
    }
    TaxStrategyHouseholdInput::create(['user_id' => $sam->id, 'spouse_annual_income' => 32000, 'spouse_employment_status' => 'full_time']);

    $alex = User::factory()->create(array_merge([
        'is_preview_user' => false,
        'first_name' => 'Alex',
        'onboarding_completed' => false,
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => ['campaign' => 'savetax'],
        'employment_status' => null,
        'marital_status' => null,
        'household_calculation_mode' => null,
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
    ], $alexExtra));
    app(SpouseLinkingService::class)->establishAcceptedLink($sam, $alex);
    app(ConsentService::class)->recordConsent($alex, UserConsent::TYPE_AI_CHAT, true);

    return [$sam->fresh(), $alex->fresh()];
}

function linkedSpouseOnboardingDirector(): OnboardingChatDirector
{
    FynStreamHarness::fake()->bind(); // no turns queued: any model call fails the test
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);

    return $director;
}

function linkedSpouseOnboardingConversation(User $user): AiConversation
{
    return AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding', 'metadata' => ['source' => 'fyn_onboarding']])->fresh();
}

function linkedSpouseOnboardingEmitStep(User $user, AiConversation $conversation, string $step): array
{
    $user->forceFill(['onboarding_fyn_step' => $step])->save();

    return iterator_to_array(linkedSpouseOnboardingDirector()->handleAction($user->fresh(), $conversation, 'continue'), false);
}

describe('the partner questions the link already answers', function (): void {
    it("reads the linked partner's earnings from their own record", function (): void {
        [, $alex] = linkedSpouseOnboardingCouple();

        expect(app(HouseholdFinancialContext::class)->linkedSpouseEarnings($alex))
            ->toBe(['earnings' => 72000.0, 'total_income' => 72000.0]);
    });

    it("does not ask an invitee for their partner's earnings band", function (): void {
        [, $alex] = linkedSpouseOnboardingCouple();

        expect(OnboardingStateMachine::firstMissingFunnelState($alex))
            ->not->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_SPOUSE_INCOME);
    });

    it('skips "Does your spouse work?" and records the mode the answer would have', function (): void {
        [, $alex] = linkedSpouseOnboardingCouple();

        expect(OnboardingStateMachine::applySkipRules(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_WORK, $alex))
            ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD);

        $alex->refresh();
        expect($alex->household_calculation_mode)->toBe('dual_earner')
            ->and((bool) $alex->marriage_allowance_eligible)->toBeFalse();
    });

    it("opens the working-spouse form with the partner's income filled in from the link", function (): void {
        [, $alex] = linkedSpouseOnboardingCouple();
        $alex->forceFill(['household_calculation_mode' => 'dual_earner'])->save();
        $conversation = linkedSpouseOnboardingConversation($alex);

        $events = linkedSpouseOnboardingEmitStep($alex, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD);

        $form = collect($events)->firstWhere('type', 'capture_form');
        expect($form['form']['name'])->toBe(CaptureForms::SPOUSE_HOUSEHOLD)
            // What they do comes from their own account too (item 11).
            ->and($form['values'][CaptureForms::LEAD])->toBe(['spouse_employment_status' => 'full_time', 'spouse_annual_income' => 72000.0, 'spouse_annual_earnings' => 72000.0])
            ->and($form)->not->toHaveKey('record');
        $saved = $conversation->messages()->where('role', 'assistant')->latest('id')->first();
        expect($saved->metadata['capture_form_values'][CaptureForms::LEAD]['spouse_annual_income'])->toBe(72000);
    });

    it("does not read what the partner does from their account when they do not share it", function (): void {
        // Their account is read only through the sharing permission (W-0530),
        // as their income is: no status, no income filled in.
        [$sam, $alex] = linkedSpouseOnboardingCouple();
        SpousePermission::query()->whereIn('user_id', [$sam->id, $alex->id])->delete();
        // A withdrawal is a row marked rejected (SpousePermissionController::revoke).
        SpousePermission::create(['user_id' => $alex->id, 'spouse_id' => $sam->id, 'status' => 'rejected']);
        $alex->forceFill(['household_calculation_mode' => 'dual_earner'])->save();

        $events = linkedSpouseOnboardingEmitStep($alex->fresh(), linkedSpouseOnboardingConversation($alex), OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD);

        expect($alex->fresh()->financiallySharedSpouse())->toBeNull()
            ->and(collect($events)->firstWhere('type', 'capture_form'))->not->toHaveKey('values');
    });

    it('keeps a figure the user already gave for their spouse', function (): void {
        [, $alex] = linkedSpouseOnboardingCouple();
        $alex->forceFill(['household_calculation_mode' => 'dual_earner'])->save();
        TaxStrategyHouseholdInput::updateOrCreate(['user_id' => $alex->id], ['spouse_annual_income' => 70000]);

        $events = linkedSpouseOnboardingEmitStep($alex, linkedSpouseOnboardingConversation($alex), OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD);

        // Only what they do is filled in; the figure given is left as given.
        expect(collect($events)->firstWhere('type', 'capture_form')['values'][CaptureForms::LEAD])->toBe(['spouse_employment_status' => 'full_time']);
    });
});

describe("the linked partner's holdings are on their own account", function (): void {
    // ice-cube #978 follow-up 3; fynla.org 2026-09-30: Pat was asked about
    // Sam's ISAs, pension and investments, already on Sam's own account.
    it('asks only their income on the working-spouse form', function (): void {
        [, $alex] = linkedSpouseOnboardingCouple();
        $alex->forceFill(['household_calculation_mode' => 'dual_earner'])->save();

        $events = linkedSpouseOnboardingEmitStep($alex, linkedSpouseOnboardingConversation($alex), OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD);

        $form = collect($events)->firstWhere('type', 'capture_form')['form'];
        expect($form['name'])->toBe(CaptureForms::SPOUSE_HOUSEHOLD)
            ->and($form['kinds'])->toBe([])
            ->and($form)->not->toHaveKey('kinds_prompt')
            ->and($form['lead_fields'])->toBe(['spouse_employment_status', 'spouse_annual_income', 'spouse_annual_earnings']);
    });

    it('asks only their income in words, for a client without forms', function (): void {
        [, $alex] = linkedSpouseOnboardingCouple();
        $state = OnboardingStateMachine::states()[OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD];

        $prompt = OnboardingStateMachine::resolvePromptText($state, $alex);

        expect($prompt)->toContain('income a year')
            ->and($prompt)->toContain('retired')
            ->and($prompt)->not->toContain('ISA')
            ->and($prompt)->not->toContain('pension');
    });

    it('opens "Your spouse\'s details" with only their income', function (): void {
        [, $alex] = linkedSpouseOnboardingCouple();
        TaxStrategyHouseholdInput::updateOrCreate(['user_id' => $alex->id], ['spouse_annual_income' => 72000]);

        $form = app(RecordEditForms::class)->formFor($alex->fresh(), 'spouse_household', $alex->id);

        expect($form['schema']['kinds'])->toBe([]);
    });

    it('still asks them when the couple do not share financial data', function (): void {
        [$sam, $alex] = linkedSpouseOnboardingCouple();
        SpousePermission::create(['user_id' => $sam->id, 'spouse_id' => $alex->id, 'status' => 'rejected']);
        $alex->forceFill(['household_calculation_mode' => 'dual_earner'])->save();
        $state = OnboardingStateMachine::states()[OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD];

        $events = linkedSpouseOnboardingEmitStep($alex, linkedSpouseOnboardingConversation($alex), OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD);

        expect(array_column(collect($events)->firstWhere('type', 'capture_form')['form']['kinds'], 'key'))->toBe(['savings', 'isa', 'pension', 'investments'])
            ->and(OnboardingStateMachine::resolvePromptText($state, $alex->fresh()))->toContain('ISAs');
    });
});

describe('when the link cannot answer, the questions are asked as before', function (): void {
    it('asks a married user with no linked account', function (): void {
        $user = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married', 'employment_status' => 'full_time', 'household_calculation_mode' => null, 'funnel_answers' => ['campaign' => 'savetax']]);

        expect(OnboardingStateMachine::firstMissingFunnelState($user))->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_SPOUSE_INCOME)
            ->and(OnboardingStateMachine::applySkipRules(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_WORK, $user))->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_WORK)
            ->and($user->fresh()->household_calculation_mode)->toBeNull();
    });

    it('asks when the couple do not share financial data', function (): void {
        [$sam, $alex] = linkedSpouseOnboardingCouple();
        SpousePermission::create(['user_id' => $sam->id, 'spouse_id' => $alex->id, 'status' => 'rejected']);

        expect(app(HouseholdFinancialContext::class)->linkedSpouseEarnings($alex->fresh()))->toBeNull()
            ->and(OnboardingStateMachine::firstMissingFunnelState($alex->fresh()))->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_SPOUSE_INCOME)
            ->and(OnboardingStateMachine::applySkipRules(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_WORK, $alex->fresh()))->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_WORK);
    });

    it("asks when the linked partner's record holds no earnings", function (): void {
        [, $alex] = linkedSpouseOnboardingCouple(samIncome: 0);

        expect(app(HouseholdFinancialContext::class)->linkedSpouseEarnings($alex))->toBeNull()
            ->and(OnboardingStateMachine::firstMissingFunnelState($alex))->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_SPOUSE_INCOME)
            ->and(OnboardingStateMachine::applySkipRules(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_WORK, $alex))->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_WORK)
            ->and($alex->fresh()->household_calculation_mode)->toBeNull();
    });
});

describe("the invitee's own income form", function (): void {
    it('opens the income their partner entered as an edit of that job', function (): void {
        [, $alex] = linkedSpouseOnboardingCouple();
        $job = Employment::where('user_id', $alex->id)->sole();
        $conversation = linkedSpouseOnboardingConversation($alex);

        $events = linkedSpouseOnboardingEmitStep($alex, $conversation, OnboardingStateMachine::STATE_BASE_WORK);

        $form = collect($events)->firstWhere('type', 'capture_form');
        expect($form['form']['name'])->toBe(CaptureForms::WORK)
            ->and($form['values'][CaptureForms::LEAD])->toBe(['annual_income' => 32000.0])
            ->and($form['record'])->toBe(['type' => 'employment', 'id' => $job->id]);
        $saved = $conversation->messages()->where('role', 'assistant')->latest('id')->first();
        expect($saved->metadata['capture_form_record'])->toEqual(['type' => 'employment', 'id' => $job->id])
            ->and($saved->metadata['capture_form_values'][CaptureForms::LEAD]['annual_income'])->toBe(32000);
    });

    it('saves over that job instead of adding a second, and the walk moves on', function (): void {
        [, $alex] = linkedSpouseOnboardingCouple();
        $job = Employment::where('user_id', $alex->id)->sole();
        $conversation = linkedSpouseOnboardingConversation($alex);
        linkedSpouseOnboardingEmitStep($alex, $conversation, OnboardingStateMachine::STATE_BASE_WORK);

        Sanctum::actingAs($alex->fresh());
        FynStreamHarness::fake()->bind();
        $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['form' => [
            'name' => CaptureForms::WORK,
            'answers' => [CaptureForms::LEAD => ['employer' => 'Globex', 'occupation' => 'Analyst', 'annual_income' => 34000]],
            'record' => ['type' => 'employment', 'id' => $job->id],
        ]])->assertOk()->streamedContent();

        $alex->refresh();
        $jobs = Employment::where('user_id', $alex->id)->get();
        expect($jobs)->toHaveCount(1)
            ->and($jobs[0]->employer)->toBe('Globex')
            ->and((float) $jobs[0]->annual_income)->toBe(34000.0)
            ->and((float) $alex->annual_employment_income)->toBe(34000.0)
            ->and($alex->onboarding_fyn_step)->not->toBe(OnboardingStateMachine::STATE_BASE_WORK);
    });

    it('checks a saved figure against the funnel band, as a new record is checked', function (): void {
        // The prefilled form posts as an edit; it must get #991's income
        // challenge too, or an invitee's salary is never cross-checked.
        [, $alex] = linkedSpouseOnboardingCouple(72000, ['funnel_answers' => ['campaign' => 'savetax', 'income' => '100001_125140']]);
        $job = Employment::where('user_id', $alex->id)->sole();
        $conversation = linkedSpouseOnboardingConversation($alex);
        linkedSpouseOnboardingEmitStep($alex, $conversation, OnboardingStateMachine::STATE_BASE_WORK);

        Sanctum::actingAs($alex->fresh());
        FynStreamHarness::fake()->bind();
        $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['form' => [
            'name' => CaptureForms::WORK,
            'answers' => [CaptureForms::LEAD => ['employer' => 'Globex', 'occupation' => 'Analyst', 'annual_income' => 6500]],
            'record' => ['type' => 'employment', 'id' => $job->id],
        ]])->assertOk()->streamedContent();

        expect($alex->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_WORK);
    });

    it('opens a blank form for another job once a work form has been saved', function (): void {
        [, $alex] = linkedSpouseOnboardingCouple();
        $conversation = linkedSpouseOnboardingConversation($alex);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Globex, £34,000', 'metadata' => ['form' => ['name' => CaptureForms::WORK, 'answers' => []]]]);

        $events = linkedSpouseOnboardingEmitStep($alex, $conversation, OnboardingStateMachine::STATE_BASE_WORK);

        $form = collect($events)->firstWhere('type', 'capture_form');
        expect($form)->not->toHaveKey('values')->and($form)->not->toHaveKey('record');
    });

    it('opens a blank form when there is no job on file', function (): void {
        $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => false, 'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax', 'employment_status' => 'full_time']);

        $events = linkedSpouseOnboardingEmitStep($user, linkedSpouseOnboardingConversation($user), OnboardingStateMachine::STATE_BASE_WORK);

        $form = collect($events)->firstWhere('type', 'capture_form');
        expect($form['form']['name'])->toBe(CaptureForms::WORK)
            ->and($form)->not->toHaveKey('values');
    });
});

/**
 * CSJ 2026-09-29 (ruling 50): what Sam gave for Alex is transferred onto
 * Alex's account and stored as usual — so Alex's own walk must open those
 * records to confirm, not ask again and add each a second time.
 *
 * @return array{0: User, 1: User}
 */
function linkedSpouseWithTransferredHoldings(): array
{
    $sam = User::factory()->create(['is_preview_user' => false, 'first_name' => 'Sam', 'marital_status' => 'married', 'employment_status' => 'full_time', 'household_calculation_mode' => 'single_earner_couple']);
    TaxStrategyHouseholdInput::create([
        'user_id' => $sam->id,
        'spouse_existing_savings_balance' => 5000,
        'spouse_isa_balance' => 20000,
        'spouse_isa_provider' => 'Vanguard',
        'spouse_existing_investment_balance' => 15000,
        'spouse_existing_pension_balance' => 40000,
        'spouse_pension_input_annual' => 2400,
        'spouse_pension_provider' => 'Aviva',
    ]);
    $alex = User::factory()->create([
        'is_preview_user' => false, 'first_name' => 'Alex', 'onboarding_completed' => false,
        'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['savings', 'isa', 'investments', 'pension']],
        'employment_status' => 'unemployed',
    ]);
    app(SpouseLinkingService::class)->establishAcceptedLink($sam, $alex);
    app(ConsentService::class)->recordConsent($alex, UserConsent::TYPE_AI_CHAT, true);

    return [$sam->fresh(), $alex->fresh()];
}

describe('the records the transfer made open as edits in the walk', function (): void {
    it('opens each transferred record in its own walk form, narrowed to its kind', function (string $step, string $formName, string $type, string $kind, string $field, float $value): void {
        [, $alex] = linkedSpouseWithTransferredHoldings();
        $conversation = linkedSpouseOnboardingConversation($alex);

        $events = linkedSpouseOnboardingEmitStep($alex, $conversation, $step);

        $form = collect($events)->firstWhere('type', 'capture_form');
        expect($form['form']['name'])->toBe($formName)
            ->and(array_column($form['form']['kinds'], 'key'))->toBe([$kind])
            ->and($form['record']['type'])->toBe($type)
            ->and((float) $form['values'][$kind][$field])->toBe($value);
        $saved = $conversation->messages()->where('role', 'assistant')->latest('id')->first();
        expect($saved->metadata['capture_form_record']['type'])->toBe($type)
            ->and(array_column($saved->metadata['capture_form']['kinds'], 'key'))->toBe([$kind]);
    })->with([
        'savings' => [OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS, CaptureForms::SAVINGS, 'savings_account', 'easy_access', 'current_value', 5000.0],
        'ISA' => [OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS, CaptureForms::ISA, 'investment_account', 'stocks_shares_isa', 'current_value', 20000.0],
        'investments' => [OnboardingStateMachine::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS, CaptureForms::INVESTMENT, 'investment_account', 'gia', 'current_value', 15000.0],
        'personal pension' => [OnboardingStateMachine::STATE_CAMPAIGN_PENSION_CONTRIBS, CaptureForms::PENSION_PERSONAL, 'dc_pension', 'personal', 'current_value', 40000.0],
    ]);

    it('saves over the transferred ISA instead of adding a second, and the walk moves on', function (): void {
        [, $alex] = linkedSpouseWithTransferredHoldings();
        $conversation = linkedSpouseOnboardingConversation($alex);
        $events = linkedSpouseOnboardingEmitStep($alex, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS);
        $record = collect($events)->firstWhere('type', 'capture_form')['record'];

        Sanctum::actingAs($alex->fresh());
        FynStreamHarness::fake()->bind();
        $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['form' => [
            'name' => CaptureForms::ISA,
            'answers' => ['stocks_shares_isa' => ['provider' => 'Vanguard', 'current_value' => 21500]],
            'record' => $record,
        ]])->assertOk()->streamedContent();

        $isas = InvestmentAccount::where('user_id', $alex->id)->whereNotNull('isa_type')->get();
        expect($isas)->toHaveCount(1)
            ->and((float) $isas[0]->current_value)->toBe(21500.0)
            ->and($alex->fresh()->onboarding_fyn_step)->not->toBe(OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS);
    });

    it('opens a blank form for another once that form has been saved in the conversation', function (): void {
        [, $alex] = linkedSpouseWithTransferredHoldings();
        $conversation = linkedSpouseOnboardingConversation($alex);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Savings £5,000', 'metadata' => ['form' => ['name' => CaptureForms::SAVINGS, 'answers' => []]]]);

        $events = linkedSpouseOnboardingEmitStep($alex, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS);

        $form = collect($events)->firstWhere('type', 'capture_form');
        expect($form)->not->toHaveKey('values')->and($form)->not->toHaveKey('record')
            ->and(count($form['form']['kinds']))->toBeGreaterThan(1);
    });

    it('opens a blank form when there are two records of that kind', function (): void {
        [, $alex] = linkedSpouseWithTransferredHoldings();
        SavingsAccount::create(['user_id' => $alex->id, 'account_name' => 'Second', 'account_type' => 'easy_access', 'current_balance' => 100, 'ownership_type' => 'individual']);
        $conversation = linkedSpouseOnboardingConversation($alex);

        $events = linkedSpouseOnboardingEmitStep($alex, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS);

        expect(collect($events)->firstWhere('type', 'capture_form'))->not->toHaveKey('record');
    });

    it('does not open a workplace pension in the personal-pension-only form', function (): void {
        $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => false, 'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax', 'employment_status' => 'unemployed']);
        // Stored as the onboarding form stores a workplace pension (pension_type).
        DCPension::create(['user_id' => $user->id, 'scheme_name' => 'Old job', 'pension_type' => 'occupational', 'current_fund_value' => 10000]);

        $events = linkedSpouseOnboardingEmitStep($user, linkedSpouseOnboardingConversation($user), OnboardingStateMachine::STATE_CAMPAIGN_PENSION_CONTRIBS);

        expect(collect($events)->firstWhere('type', 'capture_form'))->not->toHaveKey('record');
    });
});

it('opens a workplace pension in its Edit form as a workplace pension', function (): void {
    // scheme_type is never 'occupational' (enum workplace/sipp/personal), so
    // the old test sent every workplace pension to the personal kind.
    $user = User::factory()->create(['is_preview_user' => false]);
    $pension = DCPension::create(['user_id' => $user->id, 'scheme_name' => 'Acme scheme', 'pension_type' => 'occupational', 'current_fund_value' => 10000, 'employee_contribution_percent' => 5]);

    $form = app(RecordEditForms::class)->formFor($user, 'dc_pension', $pension->id);

    expect(array_keys($form['answers']))->toBe(['workplace'])
        ->and((float) $form['answers']['workplace']['employee_contribution_percent'])->toBe(5.0);
});

it('opens a stored General Investment Account in its Edit form as a GIA', function (): void {
    // CoordinatingAgent stores the form's personal_investment_account as 'gia'.
    $user = User::factory()->create(['is_preview_user' => false]);
    $account = InvestmentAccount::create(['user_id' => $user->id, 'account_name' => 'Investments', 'account_type' => 'gia', 'provider' => 'AJ Bell', 'current_value' => 15000, 'ownership_type' => 'individual']);

    $form = app(RecordEditForms::class)->formFor($user, 'investment_account', $account->id);

    expect(array_keys($form['answers']))->toBe(['gia']);
});

it('keeps the dividends an edited investment account pays, and moves the taxable total by the change', function (): void {
    // csjones walk 2026-09-30: £400 typed into "Dividends it pays you each
    // year" on the transferred GIA's edit form was dropped.
    [, $alex] = linkedSpouseWithTransferredHoldings();
    $alex->forceFill(['annual_dividend_income' => 1000])->save();
    $conversation = linkedSpouseOnboardingConversation($alex);
    $events = linkedSpouseOnboardingEmitStep($alex, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS);
    $record = collect($events)->firstWhere('type', 'capture_form')['record'];

    Sanctum::actingAs($alex->fresh());
    $post = fn (float $dividends) => $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['form' => [
        'name' => CaptureForms::INVESTMENT,
        'answers' => ['gia' => ['provider' => 'AJ Bell', 'current_value' => 15000, 'annual_dividend_income' => $dividends]],
        'record' => $record,
    ]])->assertOk()->streamedContent();

    FynStreamHarness::fake()->bind();
    $post(400);
    $account = InvestmentAccount::find($record['id']);
    expect((float) $account->annual_dividend_income)->toBe(400.0)
        ->and($account->provider)->toBe('AJ Bell')
        ->and((float) $alex->fresh()->annual_dividend_income)->toBe(1400.0);

    // The edit form now opens with the figure, and a lower one moves the total down.
    expect(app(RecordEditForms::class)->formFor($alex->fresh(), 'investment_account', $record['id'])['answers']['gia']['annual_dividend_income'])->toBe(400.0);
    FynStreamHarness::fake()->bind();
    $post(300);
    expect((float) $alex->fresh()->annual_dividend_income)->toBe(1300.0);
});

it('keeps what is drawn and the lump sum taken on an edited personal pension', function (): void {
    // csjones /m walk 2026-09-30: £1,200 typed into "You draw from it each
    // year" on the transferred pension's edit form was dropped.
    [, $alex] = linkedSpouseWithTransferredHoldings();
    $conversation = linkedSpouseOnboardingConversation($alex);
    $events = linkedSpouseOnboardingEmitStep($alex, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_PENSION_CONTRIBS);
    $record = collect($events)->firstWhere('type', 'capture_form')['record'];

    Sanctum::actingAs($alex->fresh());
    FynStreamHarness::fake()->bind();
    $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['form' => [
        'name' => CaptureForms::PENSION_PERSONAL,
        'answers' => ['personal' => ['provider' => 'Aviva', 'current_value' => 40000, 'annual_contribution' => 2400, 'annual_drawdown_income' => 1200, 'pcls_taken' => 5000]],
        'record' => $record,
    ]])->assertOk()->streamedContent();

    $pension = DCPension::find($record['id']);
    expect((float) $pension->annual_drawdown_income)->toBe(1200.0)
        ->and((float) $pension->pcls_taken)->toBe(5000.0)
        ->and(DCPension::where('user_id', $alex->id)->count())->toBe(1);
});
