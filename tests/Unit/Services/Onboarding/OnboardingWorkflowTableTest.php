<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\Procedure;
use App\Services\Onboarding\OnboardingWorkflowTable;
use Illuminate\Support\Carbon;

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
