<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Onboarding\OnboardingFactExtractor;

/**
 * S1.4 — Retrieves the structured "known facts" view of a user across
 * four memory layers, with strict gap-fill fall-through.
 *
 * Layer order (later layers only fill gaps from earlier):
 *
 *   1. Authoritative DB         — `users.*`, `family_members`, linked
 *                                 module tables. The system of record.
 *   2. Parked facts             — `ai_conversations.onboarding_parked_facts`
 *                                 — what the user volunteered earlier in
 *                                 the same onboarding flow but hasn't
 *                                 been written to `users.*` yet.
 *   3. Current conversation     — re-runs `OnboardingFactExtractor` over
 *                                 recent user messages on this
 *                                 conversation. Catches the just-typed
 *                                 turn before parking has flushed.
 *   4. Conversation index       — `ai_conversations.{summary, topics,
 *                                 entities_mentioned, intents_stated}`
 *                                 from prior conversations. Surfaces
 *                                 cross-conversation context (S1.3
 *                                 summariser fills these).
 *
 * Output shape: `[fact_key => value]`. Keys are flat (e.g. `marital_status`,
 * not `personal.marital_status`). Layer 4 contributes context-keys like
 * `prior_topics` / `prior_intents` rather than direct fact keys, so it
 * never collides with a Layer-1/2/3 fact.
 *
 * Out of scope (per Sprint 1 plan §S1.4): cross-user retrieval; caching
 * retrieved facts between turns within the same conversation.
 *
 * Used by `OnboardingPromptBuilder` (asset_capture turns), the inline
 * `buildGroupedExtractPrompt` in `OnboardingChatDirector`, and
 * `AdvicePromptBuilder` (recommendation + factual prompts) to inject a
 * `<known_facts>` block prefixed with "Do not ask the user for any
 * field above." — INV-2.2.3 + INV-2.11.1.
 */
class MemoryRetrieverService
{
    private const PARKED_BUCKETS = ['personal', 'spouse', 'dependants', 'employment', 'expenditure'];

    private const CURRENT_CONVERSATION_MESSAGE_LIMIT = 6;

    public function __construct(
        private readonly OnboardingFactExtractor $factExtractor,
    ) {}

    /**
     * Retrieve a flat fact map for the user, with strict gap-fill
     * fall-through across the four layers.
     *
     * @return array<string, mixed>
     */
    public function retrieve(User $user, ?AiConversation $conversation = null): array
    {
        $facts = $this->fromAuthoritativeDb($user);

        if ($conversation !== null) {
            $this->mergeNewKeys($facts, $this->fromParkedFacts($conversation));
            $this->mergeNewKeys($facts, $this->fromCurrentConversation($conversation));
        }

        $this->mergeNewKeys($facts, $this->fromConversationIndex($user, array_keys($facts), $conversation?->id));

        return $facts;
    }

    /**
     * Layer 1 — facts persisted in the relational store. Always wins.
     *
     * @return array<string, mixed>
     */
    public function fromAuthoritativeDb(User $user): array
    {
        $facts = [];

        $first = trim((string) ($user->first_name ?? ''));
        $surname = trim((string) ($user->surname ?? ''));
        $name = trim($first.' '.$surname);
        if ($name !== '') {
            $facts['name'] = $name;
        }

        foreach (['date_of_birth', 'marital_status', 'employment_status', 'employer', 'occupation'] as $field) {
            $value = $user->{$field};
            if ($value !== null && $value !== '') {
                $facts[$field] = $value instanceof \DateTimeInterface
                    ? $value->format('Y-m-d')
                    : $value;
            }
        }

        foreach (['annual_employment_income', 'monthly_expenditure', 'target_retirement_age'] as $numericField) {
            $value = $user->{$numericField};
            if ($value !== null && $value !== '' && (float) $value > 0) {
                $facts[$numericField] = (float) $value;
            }
        }

        $facts['onboarding_completed'] = (bool) $user->onboarding_completed;

        if ($user->spouse_id !== null) {
            $facts['has_spouse'] = true;
        }

        $dependantsCount = FamilyMember::where('user_id', $user->id)
            ->where('is_dependent', true)
            ->count();
        if ($dependantsCount > 0) {
            $facts['dependants_count'] = $dependantsCount;
        }

        foreach ([
            'properties_count' => 'properties_count',
            'investment_accounts_count' => 'investment_accounts_count',
            'savings_accounts_count' => 'savings_accounts_count',
            'dc_pensions_count' => 'dc_pensions_count',
            'db_pensions_count' => 'db_pensions_count',
        ] as $factKey => $userColumn) {
            $count = (int) ($user->{$userColumn} ?? 0);
            if ($count > 0) {
                $facts[$factKey] = $count;
            }
        }

        return $facts;
    }

    /**
     * Layer 2 — onboarding_parked_facts on the conversation. Flattened
     * across the five buckets (personal/spouse/dependants/employment/
     * expenditure). Skips keys that obviously belong to the spouse
     * (prefixed `spouse_`) or summarised dependants (`dependants_*`).
     *
     * @return array<string, mixed>
     */
    public function fromParkedFacts(AiConversation $conversation): array
    {
        $parked = (array) ($conversation->onboarding_parked_facts ?? []);

        $facts = [];

        foreach (self::PARKED_BUCKETS as $bucket) {
            $bucketData = $parked[$bucket] ?? null;

            if (! is_array($bucketData)) {
                continue;
            }

            switch ($bucket) {
                case 'personal':
                    foreach ($bucketData as $key => $value) {
                        $facts[$key] = $value;
                    }
                    break;

                case 'spouse':
                    foreach ($bucketData as $key => $value) {
                        $facts['spouse_'.$key] = $value;
                    }
                    if ($bucketData !== []) {
                        $facts['has_spouse'] = true;
                    }
                    break;

                case 'dependants':
                    if (is_array($bucketData) && array_is_list($bucketData)) {
                        $facts['dependants_count'] = count($bucketData);
                        $facts['dependants_summary'] = array_map(
                            fn ($d) => is_array($d) ? array_intersect_key($d, ['relationship' => true, 'age' => true, 'first_name' => true]) : $d,
                            $bucketData
                        );
                    }
                    break;

                case 'employment':
                    foreach ($bucketData as $key => $value) {
                        $facts[$key] = $value;
                    }
                    break;

                case 'expenditure':
                    foreach ($bucketData as $key => $value) {
                        $facts[$key] = $value;
                    }
                    break;
            }
        }

        return $facts;
    }

    /**
     * Layer 3 — re-run `OnboardingFactExtractor` over the most recent
     * user messages on THIS conversation. Catches the in-flight user
     * turn before parking has flushed (parking happens at the end of
     * `OnboardingChatDirector::handleUserMessage`).
     *
     * @return array<string, mixed>
     */
    public function fromCurrentConversation(AiConversation $conversation): array
    {
        $messages = AiMessage::where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->orderBy('id', 'desc')
            ->limit(self::CURRENT_CONVERSATION_MESSAGE_LIMIT)
            ->get(['content']);

        if ($messages->isEmpty()) {
            return [];
        }

        $merged = [];

        foreach ($messages as $message) {
            $extracted = $this->factExtractor->extract((string) $message->content);
            $merged = array_replace_recursive($merged, $extracted);
        }

        // Reuse the same flattening as fromParkedFacts by hydrating a
        // throwaway buckets array. Keeps the two layers in sync.
        $tmpConversation = new AiConversation;
        $tmpConversation->onboarding_parked_facts = $merged;

        return $this->fromParkedFacts($tmpConversation);
    }

    /**
     * Layer 4 — surface context from prior conversations' index columns
     * (S1.3). Filtered to fields not already known from layers 1-3 and
     * to the user's own conversations only.
     *
     * Contributes context-keys (`prior_topics`, `prior_intents`) rather
     * than direct fact keys — Layer 4 is hint, not fact, so it cannot
     * accidentally overwrite a Layer-1 truth.
     *
     * @param  list<string>  $alreadyKnown  Keys discovered by layers 1-3
     * @return array<string, mixed>
     */
    public function fromConversationIndex(User $user, array $alreadyKnown, ?int $excludeConversationId = null): array
    {
        $query = AiConversation::query()
            ->where('user_id', $user->id)
            ->whereNotNull('summarised_at');

        if ($excludeConversationId !== null) {
            $query->where('id', '!=', $excludeConversationId);
        }

        $rows = $query
            ->orderByDesc('summarised_at')
            ->limit(5)
            ->get(['topics', 'intents_stated', 'entities_mentioned']);

        if ($rows->isEmpty()) {
            return [];
        }

        $topics = [];
        $intents = [];

        foreach ($rows as $row) {
            foreach ((array) $row->topics as $t) {
                if (is_string($t) && $t !== '') {
                    $topics[$t] = true;
                }
            }
            foreach ((array) $row->intents_stated as $i) {
                if (is_string($i) && $i !== '') {
                    $intents[] = $i;
                }
            }
        }

        $facts = [];
        if ($topics !== [] && ! in_array('prior_topics', $alreadyKnown, true)) {
            $facts['prior_topics'] = array_keys($topics);
        }
        if ($intents !== [] && ! in_array('prior_intents', $alreadyKnown, true)) {
            // De-dupe and cap at 5 — these are context, not a feed.
            $facts['prior_intents'] = array_slice(array_values(array_unique($intents)), 0, 5);
        }

        return $facts;
    }

    /**
     * Render a `<known_facts>` block ready for prompt injection. Returns
     * an empty string when no facts are known so callers can safely
     * concatenate without producing a stray empty block.
     */
    public function renderKnownFactsBlock(User $user, ?AiConversation $conversation = null): string
    {
        $facts = $this->retrieve($user, $conversation);

        if ($facts === []) {
            return '';
        }

        $lines = ['<known_facts>'];

        foreach ($facts as $key => $value) {
            $lines[] = '- '.$key.': '.json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $lines[] = '</known_facts>';
        $lines[] = '';
        $lines[] = 'Do not ask the user for any field above.';

        return implode("\n", $lines);
    }

    /**
     * Merge $newer into $base, ONLY adding keys $base does not already
     * have. Preserves Layer-N priority over Layer-(N+1).
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $newer
     */
    private function mergeNewKeys(array &$base, array $newer): void
    {
        foreach ($newer as $key => $value) {
            if (! array_key_exists($key, $base)) {
                $base[$key] = $value;
            }
        }
    }
}
