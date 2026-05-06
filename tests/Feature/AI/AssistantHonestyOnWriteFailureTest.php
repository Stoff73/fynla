<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Constants\QuerySchemas;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AdviceFyn;
use App\Services\AI\Prompts\FcaProcessInstructions;
use App\Services\AI\QueryClassifier;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S0.5.s — assistant honesty on write-tool failure.
 *
 * BS-14 (S0.16b Batch 1, 2026-04-25) caught the lying-on-failure pattern:
 * the LLM dispatched create_goal (wrong tool) for a savings-account input,
 * the validator rejected it, and the assistant fabricated success
 * ("I've recorded your Nationwide Cash ISA…"). Root cause: the
 * FcaProcessInstructions "TOOL ERROR HANDLING" block told the model to
 * NEVER show errors to the user — designed for read-failure graceful
 * degradation, but applied to writes it produced silent fabrication.
 *
 * S0.5.r split the block into READ (graceful degradation kept) and WRITE
 * (must surface failure, never fabricate, never auto-retry). This test
 * pins both halves so the regression cannot return.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('FcaProcessInstructions instructs the model to surface write-tool failures', function (): void {
    $prompt = FcaProcessInstructions::get(false);

    expect($prompt)
        ->toContain('TOOL ERROR HANDLING — WRITE tools')
        ->toContain('surface the failure')
        ->toContain("I couldn't save that");
});

it('FcaProcessInstructions WRITE block forbids fabricated success and silent auto-retry', function (): void {
    $prompt = FcaProcessInstructions::get(false);

    // Isolate the WRITE-failure subblock — anything before that header is
    // about read failures and intentionally tells the model NOT to surface
    // errors. Asserting on the full prompt would conflict with that.
    [, $writeBlock] = explode('TOOL ERROR HANDLING — WRITE tools', $prompt, 2);

    expect($writeBlock)
        ->toContain("Do NOT say \"I've recorded\"")
        ->toContain('Never claim a record was saved when it was not')
        ->toContain('Do NOT retry the same tool call automatically');
});

it('FcaProcessInstructions still allows graceful degradation on read-tool failures', function (): void {
    $prompt = FcaProcessInstructions::get(false);

    expect($prompt)
        ->toContain('TOOL ERROR HANDLING — READ tools')
        ->toContain('Based on current UK rules')
        ->toContain('I was unable to retrieve your personalised figures');
});

it('AdviceFyn passes assistant honesty text through unchanged when a write tool fails', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Advice',
    ]);

    $classifier = Mockery::mock(QueryClassifier::class);
    $classifier->shouldReceive('classify')
        ->andReturn(['primary' => QuerySchemas::DATA_ENTRY, 'related' => []]);
    app()->instance(QueryClassifier::class, $classifier);

    // Stub the LLM to simulate a turn where create_savings_account was
    // attempted, the handler returned a validation error, and the model
    // followed the new WRITE-failure rule by surfacing the failure rather
    // than fabricating success. The chain MUST deliver the content event
    // unchanged — no rewriting, no stripping.
    $agent = Mockery::mock(CoordinatingAgent::class);
    $agent->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () {
            yield ['type' => 'tool_use', 'tool' => 'create_savings_account'];
            yield ['type' => 'content',
                'text' => "I couldn't save that — the target date must be in the future. Want to try again?"];
            yield ['type' => 'done'];
        });
    app()->instance(CoordinatingAgent::class, $agent);

    /** @var AdviceFyn $advice */
    $advice = app(AdviceFyn::class);

    $events = [];
    foreach ($advice->handle($user, $conversation, 'Add a savings goal for last March') as $event) {
        $events[] = $event;
    }

    $contents = collect($events)
        ->where('type', 'content')
        ->pluck('text')
        ->implode(' ');

    expect($contents)
        ->toMatch("/couldn't|didn't|wasn't|unable to|failed to/")
        ->and($contents)->not->toContain("I've recorded")
        ->and($contents)->not->toContain("I've saved")
        ->and($contents)->not->toContain("I've added")
        ->and($contents)->not->toContain("I've created");
});
