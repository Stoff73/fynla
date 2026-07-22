<?php

declare(strict_types=1);

namespace App\Services\AI\Learning;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CoALA Phase 6 — synthesises *candidate* durable user facts from a session for
 * human review. Never persists; never includes figures with a live owner
 * (pointer model — a retirement DATE is durable; a £ value is not). Output is
 * staged into proposed_semantic_facts, never auto-applied.
 */
final class ProposedFactSynthesiser
{
    private const ENDPOINT = 'https://api.x.ai/v1/chat/completions';

    /** @return list<array{fact_id: string, title: string, body: string, valid_from: ?string, valid_to: ?string}> */
    public function synthesise(int $conversationId, string $transcript): array
    {
        $apiKey = config('services.xai.api_key');
        if (empty($apiKey)) {
            return [];
        }

        $system = <<<'PROMPT'
You extract DURABLE, user-specific facts worth remembering long-term from a Fynla chat. Output strict JSON: {"facts": [{"fact_id": "<kebab-case>", "title": "<short>", "body": "<one sentence, third person>", "valid_from": null, "valid_to": null}]}.
Rules:
- Only durable traits/intentions: target retirement year, risk attitude, stated long-term goals, life-stage facts.
- NEVER include monetary amounts, balances, or any figure that has a live source — those are looked up live, never remembered.
- NEVER include regulatory/tax facts — those are author-maintained.
- Omit transient chit-chat. If nothing durable was stated, return {"facts": []}.
- JSON only, no prose, no markdown fences.
PROMPT;

        try {
            $response = Http::withHeaders(['Authorization' => 'Bearer '.$apiKey])
                ->timeout(60)
                ->post(self::ENDPOINT, [
                    'model' => config('services.xai.vision_model', 'grok-4.3'),
                    'max_completion_tokens' => 600,
                    'temperature' => 0,
                    'reasoning_effort' => 'none',
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $transcript],
                    ],
                ]);

            if (! $response->successful()) {
                return [];
            }

            $decoded = json_decode((string) ($response->json()['choices'][0]['message']['content'] ?? ''), true);
            $facts = is_array($decoded['facts'] ?? null) ? $decoded['facts'] : [];

            return array_values(array_filter(array_map(static fn ($f): ?array => is_array($f) && ! empty($f['fact_id']) ? [
                'fact_id' => (string) $f['fact_id'],
                'title' => (string) ($f['title'] ?? $f['fact_id']),
                'body' => (string) ($f['body'] ?? ''),
                'valid_from' => $f['valid_from'] ?? null,
                'valid_to' => $f['valid_to'] ?? null,
            ] : null, $facts)));
        } catch (\Throwable $e) {
            Log::warning('[ProposedFactSynthesiser] failed', ['conversation_id' => $conversationId, 'error' => $e->getMessage()]);

            return [];
        }
    }
}
