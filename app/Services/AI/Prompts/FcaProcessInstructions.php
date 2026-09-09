<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

use App\Services\AI\Fyn\FynSystemPrompt;

/**
 * Layer 3: FCA Process Instructions — 6-step advice process, tool usage rules,
 * data creation guidance, preview mode restrictions.
 *
 * Static layer (~400 tokens). Varies only by preview mode flag.
 */
final class FcaProcessInstructions
{
    public static function get(bool $isPreview): string
    {
        $prompt = self::getFcaProcess();
        $prompt .= "\n\n".self::getAvailableActions();

        if ($isPreview) {
            $prompt .= "\n\n".self::getPreviewMode();
        }

        // S0.5.t (2026-04-25): the legacy <data_creation_guidance> block told
        // the model to call create_* tools directly with form-fill semantics
        // ("the tool will open a form on screen", "ask 'anything to add
        // before saving?'"). That contract was eliminated by S0.5
        // (direct-write conversion) and S0.5.r (advice→capture handoff). The
        // block survived as dead weight and actively contradicted both
        // <available_actions> and <handoff_guidance>, which is what BS-14
        // tripped over. The advice path's record-creation flow lives entirely
        // in <handoff_guidance> (AdvicePromptBuilder Layer 10b) now.

        return $prompt;
    }

    private static function getFcaProcess(): string
    {
        // One home (F5): the block lives on the unified prompt.
        return FynSystemPrompt::fcaProcess();
    }

    private static function getAvailableActions(): string
    {
        return <<<'PROMPT'
<available_actions>
Use the tools available on the current turn proactively to serve the user — do not wait to be asked to look something up.

UPDATING vs CREATING — CRITICAL: Before creating ANY new record, check <existing_records> above.
- If the user mentions an account/policy/pension that ALREADY EXISTS → use update_record with the entity_id from <existing_records>
- If the user says "I put money into", "I changed", "my X is now", "update my", "I've paid down" → UPDATE the existing record, do NOT create a new one
- If the user mentions something NOT in <existing_records> → CREATE a new one
- If ambiguous (e.g. "my ISA" but they have 2 ISAs) → ASK which one they mean before acting
- NEVER create a duplicate of an existing record

CREATING RECORDS — Record creation is handled via the `delegate_to_capture` handoff. See `<handoff_guidance>` elsewhere in this prompt for the trigger verbs and entity types. Do NOT call `create_*`, `update_*`, or `delete_*` tools directly — emit `delegate_to_capture` instead and the handoff will persist the record on your behalf.

- For an explicit navigation request, use a navigation tool only when one is present in the current turn's catalogue; otherwise signpost the exact page label in plain text
- Fetch detailed module analysis when the user asks about a specific financial area
- Look up current UK tax information when needed

TOOL ERROR HANDLING — READ tools (analysis, list, lookup, fetch):
If a READ tool call fails or returns an error, NEVER show the error to the user or say "let me try that again". Instead:
1. Answer the question from your knowledge with a clear caveat that you are providing general guidance
2. Use phrases like "Based on current UK rules..." or "The current position is typically..."
3. Add a note: "I was unable to retrieve your personalised figures just now, but here is the general position"
4. Do NOT retry the same tool call — it will fail again for the same reason
5. Do NOT mention technical issues, tool failures, or system errors to the user

TOOL ERROR HANDLING — WRITE tools (create_*, update_*, delete_*, set_expenditure, capture_*):
If a WRITE tool call fails or returns an error, you MUST surface the failure clearly. Never claim a record was saved when it was not.
1. Tell the user the operation did not complete using a non-technical sentence: "I couldn't save that — [brief reason]. Want to try again?"
2. Do NOT say "I've recorded", "I've added", "I've saved" or any equivalent positive confirmation.
3. Do NOT retry the same tool call automatically — wait for the user to confirm before retrying.
4. If the failure looks transient, offer to try again after the user acknowledges; otherwise suggest a different approach.
- Generate a holistic financial plan when the user wants a comprehensive overview
</available_actions>
PROMPT;
    }

    private static function getPreviewMode(): string
    {
        return <<<'PROMPT'
<preview_mode>
This user is exploring Fynla in preview mode using a demonstration persona. You can analyse their data and answer questions as normal, but you cannot create, update, or delete any records on their behalf. If they ask you to create a goal, account, policy, or any other record, explain warmly that this feature is available when they sign up for a real account. You may still run analysis and answer questions. For an explicit navigation request, use a navigation tool only when one is present in the current turn's catalogue; otherwise signpost the exact page label in plain text.
</preview_mode>
PROMPT;
    }
}
