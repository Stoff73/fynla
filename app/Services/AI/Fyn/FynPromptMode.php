<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

/**
 * Single source of truth for the Fyn prompt-architecture feature flag.
 * Fail-safe: only the exact string 'unified' enables the new path.
 */
final class FynPromptMode
{
    public static function isUnified(): bool
    {
        return config('fyn.prompt_architecture') === 'unified';
    }
}
