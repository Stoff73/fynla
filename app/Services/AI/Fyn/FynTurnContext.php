<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

use App\Models\AiConversation;
use App\Models\User;
use InvalidArgumentException;

/**
 * Immutable description of a single Fyn turn. Carries everything
 * FynContextSelector and FynContextAssembler need — nothing more.
 */
final class FynTurnContext
{
    private function __construct(
        public readonly User $user,
        public readonly string $message,
        public readonly ?string $currentRoute,
        public readonly string $mode,            // 'advice' | 'onboarding'
        public readonly ?string $onboardingFocus,
        public readonly bool $isPreview,
        public readonly ?array $classification,
        public readonly ?AiConversation $conversation,
        public readonly ?array $kycResult = null,
    ) {}

    public static function make(
        User $user,
        string $message,
        ?string $currentRoute,
        string $mode,
        ?string $onboardingFocus,
        bool $isPreview,
        ?array $classification,
        ?AiConversation $conversation = null,
        ?array $kycResult = null,
    ): self {
        if (! in_array($mode, ['advice', 'onboarding'], true)) {
            throw new InvalidArgumentException("Invalid Fyn turn mode: {$mode}");
        }

        return new self(
            $user, $message, $currentRoute, $mode,
            $onboardingFocus, $isPreview, $classification, $conversation,
            $kycResult,
        );
    }

    public function isOnboarding(): bool
    {
        return $this->mode === 'onboarding';
    }
}
