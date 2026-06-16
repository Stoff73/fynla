<?php

declare(strict_types=1);

namespace App\Services\AI\Actions;

/**
 * CoALA typed action space (Phase 5 item 3 — plan §"Action space", FR-M1).
 *
 * Every action Fyn can take is one of these five types. Today the LLM emits
 * raw provider-shaped tool calls; the dispatcher wraps each emitted call in a
 * typed Action (a `ground` write/navigate surface or a `retrieve` read). The
 * `reason` / `learn` / `no_action` variants exist for the planner that lands
 * in item 5 — they are part of the type surface now so the dispatcher and the
 * later FynLoop share one closed vocabulary.
 */
enum ActionType: string
{
    case Reason = 'reason';
    case Retrieve = 'retrieve';
    case Learn = 'learn';
    case Ground = 'ground';
    case NoAction = 'no_action';
}
