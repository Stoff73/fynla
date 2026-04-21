<?php

declare(strict_types=1);

use App\Services\AI\AiToolDefinitions;
use App\Services\AI\FynPersonaRegistry;
use App\Services\AI\HandoffContract;
use App\Services\AI\XaiToolDefinitions;

it('exposes both personas', function () {
    $registry = app(FynPersonaRegistry::class);

    expect($registry->personaNames())->toEqualCanonicalizing(['advice', 'data_capture'])
        ->and($registry->has('advice'))->toBeTrue()
        ->and($registry->has('data_capture'))->toBeTrue()
        ->and($registry->has('does_not_exist'))->toBeFalse();
});

it('throws on unknown persona', function () {
    $registry = app(FynPersonaRegistry::class);

    expect(fn () => $registry->get('advisor'))->toThrow(InvalidArgumentException::class);
});

it('resolves prompt builder classes that actually exist', function () {
    $registry = app(FynPersonaRegistry::class);

    foreach ($registry->personaNames() as $persona) {
        $class = $registry->promptBuilderClass($persona);
        expect(class_exists($class))->toBeTrue("Prompt builder class {$class} for persona {$persona} does not exist");
    }
});

it('lists only handoff tools defined in HandoffContract', function () {
    $registry = app(FynPersonaRegistry::class);
    $internalNames = HandoffContract::internalToolNames();

    foreach ($registry->personaNames() as $persona) {
        foreach ($registry->handoffTools($persona) as $toolName) {
            expect(in_array($toolName, $internalNames, true))->toBeTrue(
                "Persona {$persona} references handoff tool '{$toolName}' which is not in HandoffContract::internalToolNames()"
            );
        }
    }
});

it('integrity: every allowed tool exists in both provider tool sets', function () {
    $registry = app(FynPersonaRegistry::class);
    $anthropic = app(AiToolDefinitions::class);
    $xai = app(XaiToolDefinitions::class);

    $extractName = static function (array $tool): ?string {
        return $tool['name'] ?? ($tool['function']['name'] ?? null);
    };

    $anthropicUniverse = array_values(array_unique(array_filter(array_merge(
        array_column($anthropic->getTools(false), 'name'),
        array_column($anthropic->handoffTools(), 'name'),
    ))));

    $xaiUniverse = array_values(array_unique(array_filter(array_merge(
        array_map($extractName, $xai->getTools(false)),
        array_map($extractName, $xai->handoffTools()),
    ))));

    foreach ($registry->personaNames() as $persona) {
        foreach ($registry->allowedTools($persona) as $toolName) {
            expect(in_array($toolName, $anthropicUniverse, true))->toBeTrue(
                "Persona {$persona} references '{$toolName}' which is missing from AiToolDefinitions (Anthropic provider)"
            );
            expect(in_array($toolName, $xaiUniverse, true))->toBeTrue(
                "Persona {$persona} references '{$toolName}' which is missing from XaiToolDefinitions (xAI provider)"
            );
        }
    }
});

it('advice persona has delegate_to_capture handoff', function () {
    $registry = app(FynPersonaRegistry::class);

    expect($registry->handoffTools('advice'))->toContain(HandoffContract::DELEGATE_TO_CAPTURE);
});

it('data_capture persona has capture_complete handoff', function () {
    $registry = app(FynPersonaRegistry::class);

    expect($registry->handoffTools('data_capture'))->toContain(HandoffContract::CAPTURE_COMPLETE);
});

it('data_capture persona does NOT expose delegate_to_capture', function () {
    $registry = app(FynPersonaRegistry::class);

    expect($registry->handoffTools('data_capture'))->not->toContain(HandoffContract::DELEGATE_TO_CAPTURE);
});

it('advice persona does NOT expose capture_complete', function () {
    $registry = app(FynPersonaRegistry::class);

    expect($registry->handoffTools('advice'))->not->toContain(HandoffContract::CAPTURE_COMPLETE);
});

it('toolListFor merges allowed and handoff tools without duplicates', function () {
    $registry = app(FynPersonaRegistry::class);

    $adviceTools = $registry->toolListFor('advice');
    expect($adviceTools)->toContain('navigate_to_page')
        ->and($adviceTools)->toContain(HandoffContract::DELEGATE_TO_CAPTURE)
        ->and(count($adviceTools))->toBe(count(array_unique($adviceTools)));

    $captureTools = $registry->toolListFor('data_capture');
    expect($captureTools)->toContain('create_savings_account')
        ->and($captureTools)->toContain('create_will')
        ->and($captureTools)->toContain(HandoffContract::CAPTURE_COMPLETE);
});
