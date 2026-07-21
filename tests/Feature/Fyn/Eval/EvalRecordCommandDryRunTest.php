<?php

declare(strict_types=1);

use App\Console\Commands\EvalRecordCommand;
use App\Models\EvalProviderRun;
use App\Models\EvalRecordingSession;
use App\Models\User;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Regression pin for the dry-run persistence fix (0b66d42).
 *
 * eval:record --dry-run used to call EvalProviderRun::create() with
 * conversation_id=0, which can never satisfy the FK to ai_conversations
 * (restrictOnDelete, present since the table's creation migration) — the
 * dry-run path always threw a QueryException on any FK-enforcing DB. The
 * fix returns an UNSAVED model: handle() only needs a non-null return to
 * mark the session completed. This test pins that contract so a future
 * refactor can't silently restore the persisting create().
 */
it('dry-run recordOne returns an unsaved EvalProviderRun and persists nothing', function (): void {
    $user = User::factory()->create();

    $session = EvalRecordingSession::create([
        'scenario_id' => 'dry_run_pin',
        'scenario_path' => 'tests/Feature/Fyn/Eval/scenarios/dry_run_pin.json',
        'scenario_yaml' => '{}',
        'preview_user_id' => $user->id,
        'persona' => 'dry_run_pin',
        'start_state_snapshot' => [],
        'http_log' => [],
        'fynla_branch' => 'test',
        'fynla_sha' => 'test',
        'status' => 'running',
        'started_at' => now(),
    ]);

    $command = app(EvalRecordCommand::class);
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

    // recordOne is private — invoke via reflection (ProceduralVersionStampingTest pattern).
    $method = new ReflectionMethod($command, 'recordOne');
    $method->setAccessible(true);

    $run = $method->invoke(
        $command,
        $session,
        ['id' => 'dry_run_pin'],
        'xai',
        'grok-4.3',
        [],
        true, // dryRun
    );

    expect($run)->toBeInstanceOf(EvalProviderRun::class)
        ->and($run->exists)->toBeFalse()
        ->and(EvalProviderRun::count())->toBe(0);
});
