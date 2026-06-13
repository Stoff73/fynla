<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use App\Services\AI\Memory\Procedural\Procedure;
use App\Services\Onboarding\OnboardingStateMachine;
use App\Services\Onboarding\OnboardingWorkflowTable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

function workflowProc(string $body): Procedure
{
    return new Procedure(
        procedureId: 'onboarding.workflow.fyn-onboarding',
        kind: 'workflow',
        module: 'onboarding',
        version: 1,
        active: true,
        effectiveFrom: Carbon::parse('2026-06-02'),
        effectiveTo: null,
        body: $body,
    );
}

/** Clear the per-request memo so each case re-resolves the table. */
function resetTransitionMemo(): void
{
    $p = new ReflectionProperty(OnboardingStateMachine::class, 'transitionTableCache');
    $p->setAccessible(true);
    $p->setValue(null, null);
}

it('parses a valid fenced yaml table into the extracted-subset array', function (): void {
    $body = <<<'MD'
Some descriptive prose.

```yaml
path_choice:
  turn_type: bubbles
  prompt_text: 'Hello {first_name}.'
  bubbles:
    - { id: journey, label: 'Follow a journey' }
    - { id: focus, label: 'Pick a focus' }
  capture_field: onboarding_fyn_path
  next: { branch: nextFromPathChoice }
journey_selection:
  turn_type: bubbles
  prompt_text: 'Which journey?'
  capture_field: onboarding_fyn_selection
  next: base_personal
```
MD;

    $table = OnboardingWorkflowTable::fromProcedure(workflowProc($body));

    expect($table)->toBeArray()
        ->and(array_keys($table))->toBe(['path_choice', 'journey_selection'])
        ->and($table['path_choice']['turn_type'])->toBe('bubbles')
        ->and($table['path_choice']['bubbles'][0])->toBe(['id' => 'journey', 'label' => 'Follow a journey'])
        ->and($table['path_choice']['capture_field'])->toBe('onboarding_fyn_path')
        // callable-next marker round-trips as an array — the merge ignores it.
        ->and($table['path_choice']['next'])->toBe(['branch' => 'nextFromPathChoice'])
        // static-string next is preserved as a data string.
        ->and($table['journey_selection']['next'])->toBe('base_personal');
});

it('preserves capture_field: null as PHP null', function (): void {
    $body = "```yaml\nbase_dependants:\n  turn_type: bubbles\n  capture_field: null\n  next: { branch: nextFromDependants }\n```";
    $table = OnboardingWorkflowTable::fromProcedure(workflowProc($body));
    expect($table['base_dependants'])->toHaveKey('capture_field')
        ->and($table['base_dependants']['capture_field'])->toBeNull();
});

it('returns null when the fenced yaml block is missing', function (): void {
    expect(OnboardingWorkflowTable::fromProcedure(workflowProc('no fence here')))->toBeNull();
});

it('returns null on malformed yaml', function (): void {
    $body = "```yaml\n  : : : not valid\n   indentation broken\n```";
    expect(OnboardingWorkflowTable::fromProcedure(workflowProc($body)))->toBeNull();
});

it('returns null when the yaml is not a mapping of states', function (): void {
    $body = "```yaml\n- just\n- a\n- list\n```";
    expect(OnboardingWorkflowTable::fromProcedure(workflowProc($body)))->toBeNull();
});

it('returns null when a state value is not a mapping', function (): void {
    $body = "```yaml\npath_choice: 'a string, not a state map'\n```";
    expect(OnboardingWorkflowTable::fromProcedure(workflowProc($body)))->toBeNull();
});

it('returns null when a state has no turn_type', function (): void {
    $body = "```yaml\npath_choice:\n  prompt_text: 'no turn_type here'\n```";
    expect(OnboardingWorkflowTable::fromProcedure(workflowProc($body)))->toBeNull();
});

it('re-attaches PHP-only callable fields from code in the merged table', function (): void {
    resetTransitionMemo();
    $merged = OnboardingStateMachine::transitionTable();

    // callable next kept from code (a Class::method string, not the {branch:…} marker)
    expect($merged['path_choice']['next'])
        ->toBe(OnboardingStateMachine::class.'::nextFromPathChoice')
        ->and(is_array($merged['path_choice']['next']))->toBeFalse();

    // callable prompt_text kept from code
    expect($merged['base_personal']['prompt_text'])
        ->toBe(OnboardingStateMachine::class.'::buildPersonalPrompt');

    // skip_if array callable kept from code (never in the .md)
    expect($merged['base_personal']['skip_if'])
        ->toBe([OnboardingStateMachine::class, 'skipIfPersonalComplete']);

    // static-string next is the corpus/data value (identical either way)
    expect($merged['journey_selection']['next'])->toBe('base_personal');
});

it('falls back to the in-code table when the corpus procedure is absent', function (): void {
    $empty = sys_get_temp_dir().'/proc-4d-'.uniqid();
    @mkdir($empty, 0777, true);
    config(['fyn.memory.procedural_path' => $empty]);
    config(['fyn.memory.procedural_reload_interval' => 0]); // force re-stat
    app()->forgetInstance(ProceduralCorpusLoader::class); // fresh loader, empty corpus
    resetTransitionMemo();

    try {
        $table = OnboardingStateMachine::transitionTable();

        // Same state set + order as code, and the campaign branch still routes.
        expect($table)->toHaveKeys(['path_choice', 'campaign_intro', 'done'])
            ->and($table['journey_selection']['next'])->toBe('base_personal')
            ->and($table['path_choice']['next'])
            ->toBe(OnboardingStateMachine::class.'::nextFromPathChoice');
    } finally {
        File::deleteDirectory($empty);
        config(['fyn.memory.procedural_path' => base_path('fyn-memory/procedural')]);
        app()->forgetInstance(ProceduralCorpusLoader::class);
        resetTransitionMemo();
    }
});

it('getNextStateId chains identically under the corpus-backed table', function (): void {
    resetTransitionMemo();
    // Personal data incomplete so skipIfPersonalComplete does NOT fire and the
    // static-string edge lands on base_personal (proving the .md edge routes).
    $user = User::factory()->make([
        'marital_status' => null,
        'date_of_birth' => null,
    ]);

    // journey_selection → base_personal (static-string edge from the .md)
    expect(OnboardingStateMachine::getNextStateId('journey_selection', 'retirement', $user))
        ->toBe('base_personal');

    // path_choice 'journey' → journey_selection (callable next kept from code)
    expect(OnboardingStateMachine::getNextStateId('path_choice', 'Follow a journey', $user))
        ->toBe('journey_selection');
});
