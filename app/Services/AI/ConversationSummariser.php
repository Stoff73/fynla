<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * S1.3 — Conversation index summariser.
 *
 * Compresses a conversation's user + assistant messages into a structured
 * index row on the `ai_conversations` table:
 *
 *   - summary           text     — single-paragraph plain-English recap
 *   - topics            string[] — module-level tags (e.g. "retirement",
 *                                  "isa_allowance", "estate_planning")
 *   - entities_mentioned object[] — typed mentions ({type, label})
 *   - intents_stated    string[] — explicit user intents (e.g. "wants to
 *                                  retire at 60", "comparing two pensions")
 *
 * Called by `ConversationSummariserJob` either on `STATE_DONE` (end of
 * onboarding) or via the every-30-minute scheduler scan for stale
 * conversations whose `last_message_at` has moved past `summarised_at`.
 *
 * Provider: xAI grok-4.3 (successor to the retired grok-4-1-fast
 * family). The model name is read from `services.xai.vision_model`
 * (the existing alias used for cheap non-chat-path calls; the alias is
 * historical and not vision-specific). See memory
 * `feedback_fyn_model_choice_is_deliberate.md`.
 *
 * Failure mode: a single failed run logs a warning and leaves the row
 * un-summarised. The scheduler will retry on the next sweep. The job
 * does not retry within Laravel's queue retry mechanism — the data is
 * regenerable, so transient failures are absorbed silently.
 */
class ConversationSummariser
{
    private const ENDPOINT = 'https://api.x.ai/v1/chat/completions';

    private const MAX_MESSAGES = 50;

    private const MAX_TOKENS = 800;

    private const TIMEOUT_SECONDS = 60;

    public function summarise(int $conversationId): void
    {
        $conversation = AiConversation::find($conversationId);

        if ($conversation === null) {
            return;
        }

        $messages = AiMessage::where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->limit(self::MAX_MESSAGES)
            ->get(['role', 'content']);

        if ($messages->isEmpty()) {
            return;
        }

        $transcript = $messages
            ->map(fn (AiMessage $m) => mb_strtoupper($m->role).': '.($m->content ?? ''))
            ->implode("\n\n");

        $payload = $this->callProvider($transcript);

        if ($payload === null) {
            return;
        }

        $conversation->forceFill([
            'summary' => $payload['summary'] ?? null,
            'topics' => $payload['topics'] ?? [],
            'entities_mentioned' => $payload['entities_mentioned'] ?? [],
            'intents_stated' => $payload['intents_stated'] ?? [],
            'summarised_at' => now(),
        ])->save();
    }

    /**
     * @return array{summary?: string, topics?: array<int, string>, entities_mentioned?: array<int, array{type: string, label: string}>, intents_stated?: array<int, string>}|null
     */
    private function callProvider(string $transcript): ?array
    {
        $apiKey = config('services.xai.api_key');

        if (empty($apiKey)) {
            Log::warning('[ConversationSummariser] XAI_API_KEY is not configured — skipping summarisation');

            return null;
        }

        $model = config('services.xai.vision_model', 'grok-4.3');

        $systemPrompt = <<<'PROMPT'
You compress a Fynla financial-planning chat transcript into a structured index entry. Output strict JSON matching this schema:

{
  "summary": "<one paragraph, max 3 sentences, plain English, factual only>",
  "topics": ["<module-level tag>", ...],
  "entities_mentioned": [{"type": "<entity_type>", "label": "<human label>"}, ...],
  "intents_stated": ["<user-stated goal or question>", ...]
}

Rules:
- topics use module-level tags from this set when they apply: protection, savings, investment, retirement, estate_planning, goals_life_events, tax_optimisation, family, property, mortgage, billing, general. Multiple are allowed.
- entities_mentioned use entity_type values like life_insurance_policy, dc_pension, db_pension, isa, gia, savings_account, property, mortgage, credit_card, family_member, goal, life_event, will, trust, business_interest, chattel.
- intents_stated quote the user's own words where possible ("I want to retire at 60", "comparing two ISAs", "asking about IHT"). Skip greetings and small talk.
- Never invent figures or facts. If something was not stated, omit it.
- Never include the assistant's recommendations — only the user's situation, intents, and the entities discussed.
- Output JSON only. No prose, no markdown fences.
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::ENDPOINT, [
                    'model' => $model,
                    'max_tokens' => self::MAX_TOKENS,
                    'reasoning_effort' => 'none',
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $transcript],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('[ConversationSummariser] xAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $json = $response->json();
            $content = $json['choices'][0]['message']['content'] ?? '';

            if ($content === '') {
                Log::warning('[ConversationSummariser] empty content from provider');

                return null;
            }

            $decoded = json_decode($content, true);

            if (! is_array($decoded)) {
                Log::warning('[ConversationSummariser] non-JSON response', ['content' => mb_substr($content, 0, 200)]);

                return null;
            }

            return $decoded;
        } catch (RuntimeException|\Throwable $e) {
            Log::warning('[ConversationSummariser] exception during provider call', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
