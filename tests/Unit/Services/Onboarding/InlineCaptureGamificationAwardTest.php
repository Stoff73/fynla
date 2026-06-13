<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\PointAward;
use App\Models\User;
use App\Models\UserGamification;
use App\Services\Onboarding\OnboardingChatDirector;
use App\ValueObjects\CaptureContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers PR #484 — savetax/inline-capture onboarding answers award points.
 *
 * The bubble-onboarding award seam (recordProgress) is NOT reached by the
 * inline-capture path (savetax campaign users have onboarding_fyn_step = null →
 * advice Fyn + delegate_to_capture → handleInlineCapture). PR #484 added a
 * per-answer onboarding award inside handleInlineCapture, deduped on a content
 * signature so a retry of the same answer does not re-award while each new
 * answer levels the user up "as they go".
 *
 * The award fires off the events the delegated CoordinatingAgent generator
 * yields (fill_form / entity_created), so the only mock needed is that
 * generator seam — no LLM HTTP mock. entityTypes ['income'] maps to no module
 * focus in inferFocusesFromEntityTypes(), so the post-award gap-fill no-ops;
 * the unified-focus carry uses the capture-mode 'savings' fallback (June13 §6c),
 * leaving CoordinatingAgent the sole collaborator exercised.
 */

/**
 * Build an OnboardingChatDirector whose CoordinatingAgent yields the given
 * events from chatWithPromptOverride(). All other deps resolve real from the
 * container — none are reached on this path.
 *
 * @param  list<array<string, mixed>>  $events
 */
function directorYielding(array $events): OnboardingChatDirector
{
    $agent = Mockery::mock(CoordinatingAgent::class);
    // handleInlineCapture now guarantees a non-null unifiedFocus (the deflection
    // fix, June13 §6c) — even for entity types with no module focus like
    // ['income'], the 'savings' fallback keeps the turn in capture mode. FynLoop
    // therefore calls setUnifiedOnboardingFocus; allow it without weakening the
    // strict chatWithPromptOverride expectation.
    $agent->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
    $agent->shouldReceive('chatWithPromptOverride')
        ->andReturnUsing(function () use ($events): Generator {
            yield from $events;
        });

    app()->instance(CoordinatingAgent::class, $agent);

    return app(OnboardingChatDirector::class);
}

function runInlineCapture(OnboardingChatDirector $director, User $user, array $events): array
{
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $context = new CaptureContext(reason: 'inline-capture test', entityTypes: ['income']);

    // Drain the generator so the post-loop award block runs.
    return iterator_to_array(
        $director->handleInlineCapture($user, $conversation, 'I earn £50,000', $context),
        false,
    );
}

afterEach(function () {
    Mockery::close();
});

describe('inline-capture onboarding award', function () {
    it('awards one onboarding answer when the LLM emits a fill_form', function () {
        $user = User::factory()->create(['is_preview_user' => false]);
        $events = [['type' => 'fill_form', 'fields' => ['annual_income' => 50000]]];

        runInlineCapture(directorYielding($events), $user, $events);

        $awards = PointAward::where('user_id', $user->id)->get();
        expect($awards)->toHaveCount(1);
        expect($awards->first()->source_type)->toBe('onboarding');
        expect($awards->first()->dedup_key)->toStartWith('onboarding:inline:');
        expect($awards->first()->points)->toBe(10);
        expect($awards->first()->meta)->toMatchArray(['inline' => true]);
        expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(10);
    });

    it('awards when a record was created even with no fill_form', function () {
        $user = User::factory()->create(['is_preview_user' => false]);
        $events = [['type' => 'entity_created', 'entity_type' => 'savings_account', 'entity_id' => 7, 'name' => 'Cash ISA']];

        runInlineCapture(directorYielding($events), $user, $events);

        expect(PointAward::where('user_id', $user->id)->count())->toBe(1);
    });

    it('does not re-award when the same answer is captured twice (retry-safe)', function () {
        $user = User::factory()->create(['is_preview_user' => false]);
        $events = [['type' => 'fill_form', 'fields' => ['annual_income' => 50000]]];

        runInlineCapture(directorYielding($events), $user, $events);
        runInlineCapture(directorYielding($events), $user, $events);

        // Identical content signature → same dedup_key → single award.
        expect(PointAward::where('user_id', $user->id)->count())->toBe(1);
        expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(10);
    });

    it('awards again for a different answer (progressive level-up as you go)', function () {
        $user = User::factory()->create(['is_preview_user' => false]);

        $first = [['type' => 'fill_form', 'fields' => ['annual_income' => 50000]]];
        $second = [['type' => 'fill_form', 'fields' => ['date_of_birth' => '1985-04-01']]];

        runInlineCapture(directorYielding($first), $user, $first);
        runInlineCapture(directorYielding($second), $user, $second);

        // Distinct content signatures → two dedup_keys → two awards.
        expect(PointAward::where('user_id', $user->id)->count())->toBe(2);
        expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(20);
    });

    it('does not award when nothing was captured', function () {
        $user = User::factory()->create(['is_preview_user' => false]);
        $events = [['type' => 'content', 'content' => 'Sure, ask me anything.']];

        runInlineCapture(directorYielding($events), $user, $events);

        expect(PointAward::where('user_id', $user->id)->exists())->toBeFalse();
    });

    it('never awards to preview users', function () {
        $user = User::factory()->create(['is_preview_user' => true]);
        $events = [['type' => 'fill_form', 'fields' => ['annual_income' => 50000]]];

        runInlineCapture(directorYielding($events), $user, $events);

        expect(PointAward::where('user_id', $user->id)->exists())->toBeFalse();
    });
});
