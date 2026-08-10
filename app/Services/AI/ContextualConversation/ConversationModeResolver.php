<?php

declare(strict_types=1);

namespace App\Services\AI\ContextualConversation;

use App\Models\AiConversation;
use App\Models\User;

final class ConversationModeResolver
{
    public function routesToOnboarding(AiConversation $conversation, User $user): bool
    {
        if (! (bool) config('onboarding.fyn_flow_enabled', true)) {
            return false;
        }

        $source = $conversation->metadata['source'] ?? null;
        if ($source === 'surface_action') {
            return false;
        }

        if ($source === 'fyn_onboarding') {
            return true;
        }

        return ($user->onboarding_completed === false || $user->active_campaign !== null)
            && $user->onboarding_fyn_step !== null;
    }
}
