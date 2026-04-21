<?php

declare(strict_types=1);

namespace App\Services\AI;

use InvalidArgumentException;

/**
 * Config-driven registry of Fyn personas.
 *
 * Source of truth: config/fyn_personas.php. Each persona entry declares
 * its prompt builder class, its allowed tool names, and its handoff
 * tool names. FynPersonaInvoker reads the registry to build the per-turn
 * tool list.
 *
 * Adding a new persona requires one entry in the config file and one
 * prompt builder class — no changes to the orchestrator or invoker.
 */
final class FynPersonaRegistry
{
    public const PERSONA_ADVICE = 'advice';

    public const PERSONA_DATA_CAPTURE = 'data_capture';

    /**
     * @var array<string, array{prompt_builder: class-string, allowed_tools: list<string>, handoff_tools: list<string>}>
     */
    private array $personas;

    public function __construct()
    {
        $config = config('fyn_personas', []);

        if (! is_array($config) || $config === []) {
            throw new InvalidArgumentException('fyn_personas config is empty or malformed.');
        }

        $this->personas = $config;
    }

    /**
     * @return list<string>
     */
    public function personaNames(): array
    {
        return array_keys($this->personas);
    }

    /**
     * @return array{prompt_builder: class-string, allowed_tools: list<string>, handoff_tools: list<string>}
     */
    public function get(string $persona): array
    {
        if (! isset($this->personas[$persona])) {
            throw new InvalidArgumentException("Unknown persona: {$persona}");
        }

        return $this->personas[$persona];
    }

    public function has(string $persona): bool
    {
        return isset($this->personas[$persona]);
    }

    /**
     * @return class-string
     */
    public function promptBuilderClass(string $persona): string
    {
        return $this->get($persona)['prompt_builder'];
    }

    /**
     * @return list<string>
     */
    public function allowedTools(string $persona): array
    {
        return $this->get($persona)['allowed_tools'];
    }

    /**
     * @return list<string>
     */
    public function handoffTools(string $persona): array
    {
        return $this->get($persona)['handoff_tools'];
    }

    /**
     * Full tool list the invoker passes to the LLM for a persona turn:
     * allowed_tools plus this persona's handoff_tools. Preview filtering
     * is applied separately via AiToolDefinitions::getTools($isPreview).
     *
     * @return list<string>
     */
    public function toolListFor(string $persona): array
    {
        $entry = $this->get($persona);

        return array_values(array_unique(array_merge($entry['allowed_tools'], $entry['handoff_tools'])));
    }
}
