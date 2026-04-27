<?php

declare(strict_types=1);

namespace App\Services\AI;

use RuntimeException;

/**
 * Thrown when a module's analysis output drifts from the per-agent contract
 * defined in ToolResultContract. S1.6.b: tool results are blocked from
 * reaching the LLM with missing keys, silent drops, or a corrupted shape.
 *
 * Carries `$context` and `$missingKeys` so CoordinatingAgent can log the
 * exact drift and the LLM-facing error includes enough detail for the
 * model to ask the user a clarifying question rather than fabricate.
 */
final class ToolResultContractException extends RuntimeException
{
    /**
     * @param  list<string>  $missingKeys
     * @param  list<string>  $presentKeys
     */
    public function __construct(
        string $message,
        public readonly string $context = '',
        public readonly array $missingKeys = [],
        public readonly array $presentKeys = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $missingKeys
     * @param  list<string>  $presentKeys
     */
    public static function missingKeys(string $context, array $missingKeys, array $presentKeys): self
    {
        $missing = implode(', ', $missingKeys);
        $present = implode(', ', $presentKeys);

        return new self(
            "Tool result for '{$context}' is missing required keys: [{$missing}]. Present keys: [{$present}].",
            $context,
            $missingKeys,
            $presentKeys,
        );
    }

    public static function unknownModule(string $module): self
    {
        return new self(
            "Tool result contract has no schema for module '{$module}'.",
            $module,
        );
    }
}
