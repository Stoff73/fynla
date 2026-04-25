<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Agents\CoordinatingAgent;
use App\Constants\QuerySchemas;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Post-onboarding Fyn with a read-only tool list.
 *
 * Two-Fyn architecture (Sprint 0): AdviceFyn handles every post-onboarding
 * chat turn. No write tools — every mutating tool the catalogue exposes is
 * stripped before the LLM sees it. Write intents during onboarding are
 * served by OnboardingChatDirector::handleInlineCapture instead.
 *
 * create_what_if_scenario is intentionally NOT in the excluded list — it is
 * analytics-only (no DB row is persisted by the handler) and remains
 * available to advice-mode chat.
 */
final class AdviceFyn
{
    private const WRITE_TOOLS = [
        'create_savings_account', 'create_investment_account', 'create_holding',
        'create_pension', 'create_property', 'create_mortgage',
        'create_protection_policy', 'create_asset', 'create_liability',
        'create_estate_gift', 'create_chattel', 'create_business_interest',
        'create_trust', 'create_family_member', 'create_will', 'update_will',
        'create_power_of_attorney', 'update_power_of_attorney',
        'update_record', 'delete_record', 'update_profile', 'set_expenditure',
        'capture_personal_details', 'capture_spouse_details',
        'capture_dependants', 'capture_work_details',
    ];

    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
        private readonly AiToolDefinitions $toolDefinitions,
        private readonly XaiToolDefinitions $xaiToolDefinitions,
        private readonly QueryClassifier $queryClassifier,
    ) {}

    public function handle(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute = null,
    ): \Generator {
        // S0.14 — short-circuit non-financial topics with the canonical
        // refusal. The classifier only flags out_of_remit when no advice
        // keyword fired, so financial questions that incidentally mention
        // a non-financial term still route through the normal advice path.
        $classification = $this->queryClassifier->classify($message, $currentRoute);

        if (($classification['primary'] ?? null) === QuerySchemas::OUT_OF_REMIT) {
            $context = $classification['detected_topic'] ?? 'General queries';
            $text = "I'm able to help you with your finances. {$context} is out of scope.";

            // Persist the user's message so the conversation transcript stays
            // honest — chatWithPromptOverride would normally do this, but
            // we're short-circuiting that path. Persist the assistant refusal
            // alongside it so the next turn's history loader sees both.
            $conversation->messages()->create([
                'role' => 'user',
                'content' => $message,
                'persona' => 'advice',
            ]);

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $text,
                'persona' => 'advice',
            ]);

            yield ['type' => 'content', 'text' => $text];
            yield ['type' => 'done'];

            return;
        }

        $allowedTools = $this->buildToolList($user);

        yield from $this->coordinatingAgent->chatWithPromptOverride(
            user: $user,
            conversation: $conversation,
            message: $message,
            currentRoute: $currentRoute,
            systemPromptOverride: null,
            allowedTools: $allowedTools,
            persistUserMessage: true,
            toolsListOverride: null,
            personaOverride: 'advice',
        );
    }

    /** @return list<string> */
    public function buildToolList(User $user): array
    {
        $provider = Cache::get('ai_provider', config('services.ai_provider', 'anthropic'));
        $definitions = $provider === 'xai' ? $this->xaiToolDefinitions : $this->toolDefinitions;
        $allTools = $definitions->getTools((bool) $user->is_preview_user);

        $names = array_filter(array_map(
            fn (array $t) => $t['name'] ?? ($t['function']['name'] ?? null),
            $allTools,
        ));

        return array_values(array_diff($names, self::WRITE_TOOLS));
    }
}
