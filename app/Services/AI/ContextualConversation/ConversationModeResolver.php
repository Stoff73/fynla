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
            // The conversation was onboarding, but the director only has a
            // walk to run while the user has a step. A completed user (step
            // null, walk done) or a paused one (step nulled, MB-23) typing into
            // it gets advice Fyn. Live 2026-09-19 (csjones, conversation 245):
            // a message into a finished walk reached the director with no
            // step and the web panel hung on "Onboarding state lost".
            return $user->onboarding_fyn_step !== null;
        }

        return ($user->onboarding_completed === false || $user->active_campaign !== null)
            && $user->onboarding_fyn_step !== null;
    }
}
