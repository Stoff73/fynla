<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Auth\FunnelAnswersMapper;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\FunnelIncomeBand;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

uses(RefreshDatabase::class);

/*
 * The Save Tax funnel's "No income" answer (#975), from registration to Fyn's
 * first turn. Found walking it on 2026-09-29:
 *  - a "Not employed" arrival skips the income step, and the recap lived only
 *    in the income (and retirement) steps, so Fyn opened cold on their first
 *    section with no greeting and no "here's what you've told me";
 *  - the funnel income cross-check ran only for a typed answer, so web and /m
 *    (which answer by form) never challenged a figure against the band.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/** A verified Save Tax registration, as AuthController builds it. */
function funnelArrival(array $funnel): User
{
    $funnel['campaign'] = 'savetax';
    $funnel['income_context'] = FunnelIncomeBand::context($funnel['income']);
    if (isset($funnel['spouseIncome'])) {
        $funnel['spouse_income_context'] = FunnelIncomeBand::context($funnel['spouseIncome']);
    }
    $user = User::factory()->create([
        'first_name' => 'Zara',
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_path' => null,
        'employment_status' => null,
        'marital_status' => null,
        'date_of_birth' => null,
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
        'funnel_answers' => $funnel,
    ]);
    app(FunnelAnswersMapper::class)->mapToProfile($user);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);

    return $user->fresh();
}

/** Start onboarding as web and /m do (forms client) and return the assistant rows. */
function startAsFormsClient(User $user): array
{
    Sanctum::actingAs($user);
    test()->withHeaders(['X-Fynla-Forms' => '1'])
        ->post('/api/ai-chat/onboarding/start', ['from' => 'savetax'])
        ->streamedContent();
    $conversation = AiConversation::where('user_id', $user->id)->latest('id')->firstOrFail();

    return $conversation->messages()->where('role', 'assistant')->orderBy('id')->pluck('content')->all();
}

function submitWorkForm(User $user, float $income): array
{
    $conversation = AiConversation::where('user_id', $user->id)->latest('id')->firstOrFail();
    FynStreamHarness::fake()->bind();
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);
    $form = ['name' => CaptureForms::WORK, 'answers' => [CaptureForms::LEAD => ['employer' => 'Acme', 'annual_income' => $income]]];

    return iterator_to_array($director->handleUserMessage($user->fresh(), $conversation, CaptureForms::summarise($form), null, true, $form), false);
}

describe('the funnel recap', function () {
    it('greets a not-employed, no-income arrival and reads their answers back on their first section', function () {
        $user = funnelArrival(['employment' => 'not-employed', 'income' => 'zero', 'spouse' => 'no', 'assets' => ['bank', 'pension']]);

        $rows = startAsFormsClient($user);

        expect($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS)
            ->and($rows)->toHaveCount(1)
            ->and($rows[0])->toStartWith("Hi Zara, I'm Fyn — thanks for those answers.")
            ->and($rows[0])->toContain("- Not currently employed\n- No income\n- You have bank accounts and a pension")
            ->and($rows[0])->toEndWith('Now your bank and savings accounts.')
            ->and($rows[0])->not->toContain(OnboardingStateMachine::BUBBLE_BREAK);
    });

    it('reads back the spouse line for a no-income arrival with a spouse', function (string $spouseIncome, string $line) {
        $user = funnelArrival(['employment' => 'not-employed', 'income' => 'zero', 'spouse' => 'yes', 'spouseIncome' => $spouseIncome, 'assets' => ['isa']]);

        $rows = startAsFormsClient($user);

        expect($rows[0])->toContain("- No income\n- {$line}\n");
    })->with([
        'spouse has no income' => ['zero', 'You have a spouse or civil partner'],
        'spouse earns' => ['50271_100000', 'You have a spouse or civil partner earning £50,271–£100,000'],
    ]);

    it('keeps the income-first opening for a working arrival, and says so when they picked No income', function () {
        $user = funnelArrival(['employment' => 'full-time', 'income' => 'zero', 'spouse' => 'no', 'assets' => ['bank']]);

        $rows = startAsFormsClient($user);

        expect($rows)->toHaveCount(1)
            ->and($rows[0])->toContain("- Working full-time\n- No income\n")
            ->and($rows[0])->toEndWith("minutes.\n\n**Let's start with your income.** Tell me your gross annual income (this includes bonuses and commissions).");
    });

    it('opens a retired arrival on the retirement date behind the recap', function () {
        $user = funnelArrival(['employment' => 'retired', 'income' => 'zero', 'spouse' => 'no', 'assets' => ['pension']]);

        $rows = startAsFormsClient($user);

        expect($rows[0])->toContain("- Retired\n- No income\n")
            ->and(end($rows))->toBe('When did you retire? A year is fine — something like "2020".');
    });

    it('is delivered once, however the first section is re-emitted', function () {
        $user = funnelArrival(['employment' => 'not-employed', 'income' => 'zero', 'spouse' => 'no', 'assets' => ['bank']]);
        startAsFormsClient($user);
        $conversation = AiConversation::where('user_id', $user->id)->latest('id')->firstOrFail();
        $step = OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS;

        // The resume Continue re-emits the step in the same conversation, and
        // "New" opens a fresh one mid-flow: neither greets again.
        $director = app(OnboardingChatDirector::class);
        $director->setClientSupportsForms(true);
        iterator_to_array($director->emitTurnForState($user->fresh(), $conversation, $step, OnboardingStateMachine::getState($step)), false);
        $fresh = AiConversation::factory()->create(['user_id' => $user->id]);
        iterator_to_array($director->emitTurnForState($user->fresh(), $fresh, $step, OnboardingStateMachine::getState($step)), false);

        $greetings = AiMessage::whereIn('conversation_id', [$conversation->id, $fresh->id])
            ->where('content', 'like', '%thanks for those answers%')->count();
        expect($greetings)->toBe(1);
    });

    it('does not recap before a funnel question Fyn still has to ask', function () {
        $user = funnelArrival(['employment' => 'not-employed', 'income' => 'zero', 'spouse' => 'no', 'assets' => []]);

        $rows = startAsFormsClient($user);

        expect($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_ASSETS)
            ->and(implode(' ', $rows))->not->toContain('thanks for those answers');
    });
});

describe('the funnel income challenge on a form answer', function () {
    it('challenges a form income that contradicts the band, as a typed one is', function () {
        $user = funnelArrival(['employment' => 'full-time', 'income' => '100001_125140', 'spouse' => 'no', 'assets' => ['bank']]);
        startAsFormsClient($user);

        $events = submitWorkForm($user, 6500);

        $challenge = collect($events)->firstWhere('type', 'quick_replies');
        $user->refresh();
        expect($challenge['prompt_text'])->toBe("Earlier you told us your income was £100,001–£125,140, but you've entered £6,500. That changes your tax-saving calculation — is £6,500 right?")
            ->and(collect($challenge['bubbles'])->pluck('id')->all())->toBe(['continue', 'change'])
            ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_WORK)
            ->and($user->onboarding_fyn_context['pending_income_challenge']['band'])->toBe('100001_125140');
    });

    it('says "you have no income" when the funnel answer was No income', function () {
        $user = funnelArrival(['employment' => 'full-time', 'income' => 'zero', 'spouse' => 'no', 'assets' => ['bank']]);
        startAsFormsClient($user);

        $events = submitWorkForm($user, 40000);

        expect(collect($events)->firstWhere('type', 'quick_replies')['prompt_text'])
            ->toBe("Earlier you told us you have no income, but you've entered £40,000. That changes your tax-saving calculation — is £40,000 right?");
    });

    it('accepts £0 against No income without a challenge', function () {
        $user = funnelArrival(['employment' => 'full-time', 'income' => 'zero', 'spouse' => 'no', 'assets' => ['bank']]);
        startAsFormsClient($user);

        $events = submitWorkForm($user, 0);

        expect($user->fresh()->onboarding_fyn_context['pending_income_challenge'] ?? null)->toBeNull()
            ->and($user->fresh()->onboarding_fyn_step)->not->toBe(OnboardingStateMachine::STATE_BASE_WORK)
            ->and(collect($events)->pluck('prompt_text')->filter()->implode(' '))->not->toContain('Earlier you told us');
    });

    it('re-opens the work form, without the recap, when the user taps Change', function () {
        $user = funnelArrival(['employment' => 'full-time', 'income' => 'zero', 'spouse' => 'no', 'assets' => ['bank']]);
        startAsFormsClient($user);
        submitWorkForm($user, 40000);
        $conversation = AiConversation::where('user_id', $user->id)->latest('id')->firstOrFail();
        $director = app(OnboardingChatDirector::class);
        $director->setClientSupportsForms(true);

        $events = iterator_to_array($director->handleUserMessage($user->fresh(), $conversation, 'Change'), false);

        $form = collect($events)->firstWhere('type', 'capture_form');
        expect($form['form']['name'])->toBe(CaptureForms::WORK)
            ->and($form['prompt_text'])->toBe('Now your work and income.')
            ->and($user->fresh()->onboarding_fyn_context['pending_income_challenge'] ?? null)->toBeNull();
    });

    it('says "your spouse has no income" for the spouse', function () {
        $director = app(OnboardingChatDirector::class);
        $build = new ReflectionMethod($director, 'buildIncomeChallenge');

        $text = $build->invoke($director, ['field' => 'spouse', 'band' => 'zero', 'band_label' => 'no income', 'entered' => 12000.0], User::factory()->create());

        expect($text)->toBe("Earlier you told us your spouse has no income, but you've entered £12,000. That changes your tax-saving calculation — is £12,000 right for them?");
    });
});
