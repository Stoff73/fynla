<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\Employment;
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
            ->and($form['values'][CaptureForms::LEAD])->toBe(['spouse_annual_income' => 72000.0, 'spouse_annual_earnings' => 72000.0])
            ->and($form)->not->toHaveKey('record');
        $saved = $conversation->messages()->where('role', 'assistant')->latest('id')->first();
        expect($saved->metadata['capture_form_values'][CaptureForms::LEAD]['spouse_annual_income'])->toBe(72000);
    });

    it('keeps a figure the user already gave for their spouse', function (): void {
        [, $alex] = linkedSpouseOnboardingCouple();
        $alex->forceFill(['household_calculation_mode' => 'dual_earner'])->save();
        TaxStrategyHouseholdInput::updateOrCreate(['user_id' => $alex->id], ['spouse_annual_income' => 70000]);

        $events = linkedSpouseOnboardingEmitStep($alex, linkedSpouseOnboardingConversation($alex), OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD);

        expect(collect($events)->firstWhere('type', 'capture_form'))->not->toHaveKey('values');
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
