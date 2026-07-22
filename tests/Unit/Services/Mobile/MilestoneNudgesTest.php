<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserMilestone;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Gamification\MilestoneCollector;
use App\Services\Mobile\MilestoneDetectionService;
use App\Services\Mobile\PushNotificationService;
use App\Services\Onboarding\OnboardingChatDirector;
use App\ValueObjects\CaptureContext;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * WP-5c-iii — the nudge layer: request-scoped mint collection, flag-gated
 * push, deep-link routes on upcoming steps, the dashboard hero nudge, and
 * Fyn acknowledging a mint in the same capture turn.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('collects every fresh mint in the request-scoped collector', function () {
    $user = User::factory()->create();

    app(MilestoneDetectionService::class)->detectEmergencyFund($user, 3.0);

    $minted = app(MilestoneCollector::class)->all();
    expect(array_column($minted, 'type'))->toBe(['emergency_fund', 'emergency_fund'])
        ->and($minted[0]['label'])->toBe('Your emergency fund covers a month of your spending.');
});

it('sends a push per mint only when the flag is on and the user is real', function () {
    config(['gamification.push_enabled' => true]);
    $user = User::factory()->create(['is_preview_user' => false]);

    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldReceive('sendToUser')
        ->once()
        ->withArgs(fn (int $id, string $title): bool => $id === $user->id && $title === 'Milestone reached');
    app()->instance(PushNotificationService::class, $push);

    app(MilestoneDetectionService::class)->detectRetirementOnTrack($user, 25000.0, 20000.0);
});

it('sends no push when the flag is off or the user is a preview persona', function () {
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldNotReceive('sendToUser');
    app()->instance(PushNotificationService::class, $push);

    config(['gamification.push_enabled' => false]);
    $real = User::factory()->create(['is_preview_user' => false]);
    app(MilestoneDetectionService::class)->detectRetirementOnTrack($real, 25000.0, 20000.0);

    config(['gamification.push_enabled' => true]);
    $preview = User::factory()->create(['is_preview_user' => true]);
    app(MilestoneDetectionService::class)->detectRetirementOnTrack($preview, 25000.0, 20000.0);
});

it('stamps every upcoming step with its deep-link route', function () {
    $user = User::factory()->create();

    $upcoming = app(MilestoneDetectionService::class)->upcoming($user, 15000.0);
    $byTitle = collect($upcoming)->keyBy('title');

    expect($byTitle['Net worth £25,000']['route'])->toBe('m-net-worth')
        ->and($byTitle['Put your will in place']['route'])->toBe('m-estate')
        ->and($byTitle['Open your first ISA']['route'])->toBe('m-savings')
        ->and($byTitle['Action your first tax saving']['route'])->toBe('tax-strategy')
        ->and(collect($upcoming)->every(fn (array $i): bool => ($i['route'] ?? '') !== ''))->toBeTrue();
});

it('memoises the composed tax plan per request and declines when never composed', function () {
    $user = User::factory()->create();
    $service = app(ComposedTaxPlanService::class);

    expect($service->forUserIfComputed($user))->toBeNull()
        ->and(app(ComposedTaxPlanService::class))->toBe($service); // scoped

    $plan = $service->forUser($user);

    expect($service->forUserIfComputed($user))->toBe($plan);
});

it('carries the hero nudge in the dashboard payload', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/v1/mobile/dashboard')->assertOk()->json('data');

    expect(array_key_exists('next_milestone', $data))->toBeTrue()
        ->and($data['next_milestone'])->not->toBeNull()
        ->and($data['next_milestone'])->toHaveKeys(['group', 'title', 'steps', 'route']);
});

it('lets Fyn acknowledge a capture-crossed milestone in the same turn', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    // The capture "creates" this ISA (pre-created here; the mocked agent
    // yields the entity_created event the real create handler would).
    SavingsAccount::factory()->create(['user_id' => $user->id, 'is_isa' => true]);

    $agent = Mockery::mock(CoordinatingAgent::class);
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')->andReturnUsing(function (): Generator {
        yield ['type' => 'entity_created', 'entity_type' => 'savings_account', 'entity_id' => 7, 'name' => 'Cash ISA'];
    });
    app()->instance(CoordinatingAgent::class, $agent);

    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $events = iterator_to_array(
        app(OnboardingChatDirector::class)->handleInlineCapture(
            $user,
            $conversation,
            'I opened a Cash ISA with £5,000',
            new CaptureContext(reason: 'milestone ack test', entityTypes: ['savings_account']),
        ),
        false,
    );

    $ackEvents = array_values(array_filter(
        $events,
        fn (array $e): bool => ($e['type'] ?? '') === 'content'
            && str_starts_with((string) ($e['text'] ?? ''), "That's a milestone:"),
    ));

    expect($ackEvents)->toHaveCount(1)
        ->and($ackEvents[0]['text'])->toBe("That's a milestone: you've opened your first ISA.")
        ->and(UserMilestone::where('user_id', $user->id)->where('milestone_type', 'isa_first')->exists())->toBeTrue()
        ->and(AiMessage::where('conversation_id', $conversation->id)
            ->where('content', "That's a milestone: you've opened your first ISA.")
            ->exists())->toBeTrue();
});
