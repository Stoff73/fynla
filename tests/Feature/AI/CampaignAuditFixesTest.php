<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Models\AiConversation;
use App\Models\PendingRegistration;
use App\Models\PensionInputHistory;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use App\ValueObjects\CaptureContext;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Fyn\ScriptedAnthropicClient;

uses(RefreshDatabase::class);

/**
 * 2026-07-06 campaign audit fixes (July/July6Updates/saveTax.md +
 * pensionCampaign.md audit flags). One test per fixed flag:
 *
 *   P2 — recap-edit no longer short-circuits to synthesis: "Something's
 *        changed" stamps verify_section='recap' and the post-edit confirm
 *        re-enters the gap walk.
 *   P4/S2 — pause-resume: a paused campaign user (incomplete OR completed
 *        re-entrant) resumes at the parked step on the next /start instead of
 *        restarting at the entry state / 409ing.
 *   P5 — the restart action clears active_campaign (and fully resets a
 *        completed re-entrant).
 *   P6 — campaign_pension_history skips when contribution history is already
 *        on file.
 *   P7 — campaign2_flexible_access carries the pensions record context.
 *   P8 — the inline-capture whitelist includes the two pensioncheck tools.
 *   S1 — retired / not-working campaign users continue the section walk
 *        instead of exhausting it via base_expenditure.
 *   P10 — an unknown funnel_answers.campaign is stripped at registration,
 *        never 422-blocking the account.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
    Cache::put('ai_provider', 'anthropic');
    app()->instance(Client::class, new ScriptedAnthropicClient([]));
});

afterEach(function () {
    Mockery::close();
});

// ── helpers ─────────────────────────────────────────────────────────────────

function makeAuditFixUser(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'is_preview_user' => false,
        'onboarding_completed' => true,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_path' => null,
        'onboarding_fyn_selection' => null,
        'onboarding_fyn_context' => null,
        'active_campaign' => null,
    ], $overrides));

    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);

    return $user;
}

function makeAuditFixConversation(User $user): AiConversation
{
    return AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
        'metadata' => ['source' => 'fyn_onboarding', 'campaign' => 'pensioncheck'],
    ]);
}

// ── P2: recap-edit routes back into the gap walk ─────────────────────────────

it('stamps verify_section=recap when the existing recap answers "Something\'s changed"', function (): void {
    $user = makeAuditFixUser([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
        'onboarding_fyn_step' => 'campaign2_existing_recap',
    ]);

    $next = OnboardingStateMachine::nextFromExistingRecap("Something's changed", $user);

    expect($next)->toBe('campaign_verify_edit');
    expect($user->refresh()->onboarding_fyn_context['verify_section'] ?? null)->toBe('recap');
});

it('re-enters the gap walk (not synthesis) after a recap-edit is confirmed', function (): void {
    // A savetax graduate: income + DOB + expenditure known; no state pension,
    // no goals — the gap walk must resume at the pensions section, not jump
    // to synthesis→terminal.
    $user = makeAuditFixUser([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
        'onboarding_fyn_step' => 'campaign_verify_navigate',
        'onboarding_fyn_context' => ['verify_section' => 'recap'],
        'employment_status' => 'full_time',
        'annual_employment_income' => 62000,
        'date_of_birth' => '1980-02-19',
        'monthly_expenditure' => 2500,
        'marital_status' => 'single',
    ]);

    $next = OnboardingStateMachine::nextFromVerifyNavigate("Yes, that's right", $user);

    expect($next)->not->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS)
        ->and($next)->toBe('campaign_occupational_scheme');
});

it('recap "Yes" and recap-edit confirm resolve the same first gap section', function (): void {
    $user = makeAuditFixUser([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
        'onboarding_fyn_step' => 'campaign2_existing_recap',
        'employment_status' => 'full_time',
        'annual_employment_income' => 62000,
        'date_of_birth' => '1980-02-19',
        'monthly_expenditure' => 2500,
        'marital_status' => 'single',
    ]);

    expect(OnboardingStateMachine::nextFromExistingRecap("Yes, that's right", $user))
        ->toBe(OnboardingStateMachine::firstCampaignSection($user));
});

// ── P4/S2: pause-resume restores the parked step ─────────────────────────────

it('resumes an incomplete paused campaign user at the parked step, reusing the conversation', function (): void {
    $user = makeAuditFixUser([
        'onboarding_completed' => false,
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'savetax',
        'onboarding_fyn_step' => null,
        'onboarding_fyn_context' => ['paused_at_step' => 'campaign_isa_holdings'],
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['isa']],
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
        'metadata' => ['source' => 'fyn_onboarding'],
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/ai-chat/onboarding/start', []);

    $response->assertOk();
    $streamed = $response->streamedContent();
    $user->refresh();

    expect($user->onboarding_fyn_step)->toBe('campaign_isa_holdings')
        ->and($user->onboarding_fyn_context['paused_at_step'] ?? null)->toBeNull()
        ->and($streamed)->toContain('"conversation_id":'.$conversation->id);
    // No duplicate conversation was created for the resume.
    expect(AiConversation::forUser($user->id)->onboarding()->count())->toBe(1);
});

it('resumes a completed paused re-entrant at the parked step on a bare start (no 409)', function (): void {
    $user = makeAuditFixUser([
        'onboarding_completed' => true,
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
        'onboarding_fyn_step' => null,
        'active_campaign' => null, // pause cleared it
        'onboarding_fyn_context' => ['paused_at_step' => 'campaign2_state_pension'],
    ]);
    makeAuditFixConversation($user);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/ai-chat/onboarding/start', []);

    $response->assertOk();
    $response->streamedContent();
    $user->refresh();

    expect($user->onboarding_fyn_step)->toBe('campaign2_state_pension')
        ->and($user->active_campaign)->toBe('pensioncheck')
        ->and($user->onboarding_fyn_context['paused_at_step'] ?? null)->toBeNull();
});

it('still 409s a completed user with no from and no paused campaign', function (): void {
    $user = makeAuditFixUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/ai-chat/onboarding/start', [])
        ->assertStatus(409);
});

it('reaches the resume branch (not 409) for a mid-campaign re-entrant on a bare start', function (): void {
    // Found live: the /m dashboard re-probes /start with no from on reload;
    // a completed re-entrant mid-walk was 409ing into the generic greeting.
    $user = makeAuditFixUser([
        'active_campaign' => 'pensioncheck',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
        'onboarding_fyn_step' => 'campaign_occupational_scheme',
    ]);
    $conversation = makeAuditFixConversation($user);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/ai-chat/onboarding/start', []);

    $response->assertOk();
    $streamed = $response->streamedContent();

    expect($streamed)->toContain('"type":"resume"')
        ->and($streamed)->toContain('"conversation_id":'.$conversation->id)
        ->and($streamed)->toContain('"current_step":"campaign_occupational_scheme"');
});

// ── P5: restart clears active_campaign ──────────────────────────────────────

it('clears active_campaign and fully resets a completed re-entrant on the restart action', function (): void {
    $user = makeAuditFixUser([
        'active_campaign' => 'pensioncheck',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
        'onboarding_fyn_step' => 'campaign2_pension_pots',
    ]);
    $conversation = makeAuditFixConversation($user);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conversation->id}/action", ['action' => 'restart']);

    $response->assertOk();
    $response->streamedContent();
    $user->refresh();

    expect($user->active_campaign)->toBeNull()
        ->and($user->onboarding_fyn_step)->toBeNull()
        ->and($user->onboarding_completed)->toBeTrue();
});

// ── P6: pension history skips when already on file ───────────────────────────

it('skips campaign_pension_history when contribution history is already on file', function (): void {
    $user = makeAuditFixUser([
        'annual_employment_income' => 100000, // higher-rate: income gate alone would ASK
    ]);

    expect(OnboardingStateMachine::skipIfPensionHistoryNotApplicable($user))->toBeFalse();

    PensionInputHistory::create([
        'user_id' => $user->id,
        'tax_year' => '2024/25',
        'pension_input_amount' => 5000,
    ]);

    expect(OnboardingStateMachine::skipIfPensionHistoryNotApplicable($user->refresh()))->toBeTrue();
});

// ── P7: flexible access carries the pensions record context ──────────────────

it('arms the pensions record context on campaign2_flexible_access', function (): void {
    $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN2_FLEXIBLE_ACCESS);

    expect($state['record_context'] ?? null)->toBe('pensions');
});

// ── P8: inline-capture whitelist includes the pensioncheck tools ─────────────

it('whitelists capture_retirement_goals and capture_state_pension for inline capture', function (): void {
    $method = new ReflectionMethod(OnboardingChatDirector::class, 'captureToolSet');
    $tools = $method->invoke(
        app(OnboardingChatDirector::class),
        new CaptureContext('user asked to record retirement goals', ['retirement_profile'])
    );

    expect($tools)->toContain('capture_retirement_goals')
        ->and($tools)->toContain('capture_state_pension');
});

// ── S1: retired / not-working campaign users stay in the walk ────────────────

it('keeps a not-working campaign user in the section walk instead of exiting to expenditure', function (): void {
    $user = makeAuditFixUser([
        'onboarding_completed' => false,
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'savetax',
        'onboarding_fyn_step' => 'base_employment',
        'employment_status' => 'unemployed',
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['isa']],
        'marital_status' => 'single',
    ]);

    $next = OnboardingStateMachine::nextFromEmployment('Not working', $user);

    expect($next)->not->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE)
        ->and($next)->toBe('campaign_isa_holdings');
});

it('routes a retired campaign user with captured income into the income verify after the retirement date', function (): void {
    $user = makeAuditFixUser([
        'onboarding_completed' => false,
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'savetax',
        'onboarding_fyn_step' => 'base_retirement_date',
        'employment_status' => 'retired',
        'annual_employment_income' => 18000, // pension income captured at base_work
    ]);

    $next = OnboardingStateMachine::nextFromRetirementDate('2020', $user);

    expect($next)->toBe('campaign_verify_announce')
        ->and($user->refresh()->onboarding_fyn_context['verify_section'] ?? null)->toBe('income');
});

it('keeps the standard (non-campaign) retired flow unchanged', function (): void {
    $user = makeAuditFixUser([
        'onboarding_completed' => false,
        'onboarding_fyn_path' => 'journey',
        'employment_status' => 'retired',
    ]);

    expect(OnboardingStateMachine::nextFromRetirementDate('2020', $user))
        ->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);
});

// ── P10: unknown funnel campaign is stripped, not rejected ───────────────────

it('strips an unknown funnel_answers.campaign at registration instead of rejecting', function (): void {
    $payload = [
        'first_name' => 'Pat',
        'surname' => 'Tester',
        'email' => 'pat.audit-fixes@example.com',
        'password' => 'Sup3r$ecret!',
        'password_confirmation' => 'Sup3r$ecret!',
        'funnel_answers' => ['campaign' => 'not-a-campaign', 'income' => 'upto_50270'],
    ];

    $this->postJson('/api/auth/register', $payload)->assertStatus(201);

    $pending = PendingRegistration::where('email', 'pat.audit-fixes@example.com')->first();
    expect($pending)->not->toBeNull()
        ->and($pending->funnel_answers)->not->toHaveKey('campaign')
        ->and($pending->funnel_answers['income'] ?? null)->toBe('upto_50270');
});

it('keeps a known funnel_answers.campaign at registration', function (): void {
    $payload = [
        'first_name' => 'Pat',
        'surname' => 'Tester',
        'email' => 'pat.audit-keeps@example.com',
        'password' => 'Sup3r$ecret!',
        'password_confirmation' => 'Sup3r$ecret!',
        'funnel_answers' => ['campaign' => 'pensioncheck', 'income' => 'upto_50270'],
    ];

    $this->postJson('/api/auth/register', $payload)->assertStatus(201);

    $pending = PendingRegistration::where('email', 'pat.audit-keeps@example.com')->first();
    expect($pending->funnel_answers['campaign'] ?? null)->toBe('pensioncheck');
});
