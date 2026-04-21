<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

use App\Models\User;
use App\Services\AI\HandoffContract;
use App\Services\TaxConfigService;
use App\ValueObjects\CaptureContext;

/**
 * Short-form system prompt builder for post-onboarding data-capture turns.
 *
 * This is the POST-ONBOARDING twin of OnboardingPromptBuilder. Both emit
 * a short, capture-focused prompt (~500 tokens vs AdvicePromptBuilder's
 * 1,600); both suppress the FCA process instructions that bias the model
 * toward single-tool-per-turn emission.
 *
 * Post-onboarding differences vs onboarding asset_capture:
 *  - takes a CaptureContext value object instead of a focus string
 *  - tool list is wider (any create_* tool plus update_* and profile tools)
 *  - may include pending_advice_question to bias Fyn toward capturing only
 *    what's needed to unblock the user's original advice question
 *  - must emit the capture_complete handoff tool when done (the invoker
 *    strips it from SSE and returns control to the orchestrator)
 *
 * NOT used by OnboardingChatDirector — the director continues to use
 * OnboardingPromptBuilder::buildAssetCapturePrompt() for asset_capture
 * turns (per AMENDMENTS §A).
 */
final class DataCapturePromptBuilder
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function build(User $user, CaptureContext $context): string
    {
        $nameParts = explode(' ', (string) $user->name);
        $firstName = $nameParts[0] !== '' ? $nameParts[0] : 'there';

        $taxYear = $this->taxConfig->getTaxYear() ?? '2025/26';

        $layers = [
            CoreIdentity::get($firstName),
            ComplianceRules::get($taxYear),
            $this->captureInstructions($context),
        ];

        return implode("\n\n", $layers);
    }

    private function captureInstructions(CaptureContext $context): string
    {
        $entityList = implode(', ', $context->entityTypes);
        $reason = $context->reason;
        $pendingQuestion = $context->pendingAdviceQuestion;
        $fieldsNeeded = $context->fieldsNeeded;

        $fieldHint = '';
        if ($fieldsNeeded !== []) {
            $fields = implode(', ', $fieldsNeeded);
            $fieldHint = "\nSpecifically, advice Fyn needs these fields: {$fields}. Prioritise capturing them.";
        }

        $contextLine = $pendingQuestion !== null
            ? "You were invoked because advice Fyn could not answer the user's question: \"{$pendingQuestion}\". Once you've captured the needed data, emit `".HandoffContract::CAPTURE_COMPLETE."` — the orchestrator will then re-prime advice Fyn to answer the original question with the fresh data."
            : "You were invoked directly for inline data capture. Once you've captured what the user asked for, emit `".HandoffContract::CAPTURE_COMPLETE."` and control returns to the main conversation.";

        $captureCompleteTool = HandoffContract::CAPTURE_COMPLETE;

        return <<<PROMPT
<data_capture_turn>
{$contextLine}

Capture reason: {$reason}
Expected entity types: {$entityList}{$fieldHint}

YOUR SINGLE JOB: call the appropriate create_* / update_* tool for EACH record the user describes. If they mention 3 items in one message, call 3 tools in your first response. If they provide nothing usable, acknowledge briefly and ask ONE short follow-up question naming the missing field(s).

Multi-entity rule: when the user mentions multiple records in a single message, you MUST emit one tool_use block per record in your very first response. Do not summarise the rest in text and come back for them on the next turn — emit them all at once.

Retraction: if the user contradicts a prior answer ("actually that's £50k not £40k"), call the matching `update_*` tool and acknowledge the change briefly. Never silently overwrite.

Terminate the capture: call `{$captureCompleteTool}` exactly once when the user's message has been fully captured. The tool takes two fields:
  - summary: one-sentence user-facing recap, e.g. "Added Scottish Widows SIPP £50k"
  - records_created: array of {type, id} pairs for every record you created or updated this sub-conversation

Do NOT greet, summarise at length, reference figures the user did not provide in THIS message, or ask for advice-related context. Keep your text output to short acknowledgments and targeted follow-up questions only. Do NOT emit `delegate_to_capture` — you are data-capture Fyn, not advice Fyn. Do NOT emit any advice, analysis, or recommendations — advice Fyn will handle that when control returns.

Off-script guardrail: your acknowledgment text MUST be EXACTLY ONE short sentence per capture, or empty. Never introduce topics outside the expected entity types above. If the user volunteers information about unrelated records, IGNORE it — the orchestrator can route them back to advice after `{$captureCompleteTool}`.
</data_capture_turn>
PROMPT;
    }
}
