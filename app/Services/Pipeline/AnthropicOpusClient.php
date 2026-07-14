<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin HTTPS wrapper around Anthropic's Messages API for the pipeline's
 * script generation. Direct HTTP (no SDK) so the client is straightforward
 * to fake in tests via Http::fake().
 *
 * Enforces the pipeline's cost cap before every call.
 *
 * @phpstan-type SystemBlock array{type:'text',text:string,cache_control?:array{type:'ephemeral'}}
 * @phpstan-type UserMessage array{role:'user',content:string}
 */
class AnthropicOpusClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    public function __construct(private readonly CostCap $costCap) {}

    /**
     * Send a Messages API request. Returns the assistant's raw text content
     * plus the parsed usage payload (for cost recording).
     *
     * @param  list<SystemBlock>  $systemBlocks
     * @param  list<UserMessage>  $messages
     * @return array{text:string, usage:array<string,int>, gbp:float}
     */
    public function complete(array $systemBlocks, array $messages): array
    {
        $this->costCap->ensureDailyBudgetAvailable();

        $payload = [
            'model' => config('pipeline.anthropic.model', 'claude-opus-4-7'),
            'max_tokens' => (int) config('pipeline.anthropic.max_output_tokens', 4096),
            'system' => $systemBlocks,
            'messages' => $messages,
        ];

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey(),
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ])
            ->timeout((int) config('pipeline.anthropic.timeout_seconds', 120))
            ->retry(2, 5000, function ($e, $request) {
                return $e instanceof \Illuminate\Http\Client\ConnectionException;
            })
            ->post(self::API_URL, $payload);

        if (! $response->successful()) {
            Log::channel('pipeline')->error('Anthropic Opus call failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Anthropic Opus call failed: HTTP '.$response->status().' — '.$response->body());
        }

        $data = $response->json();

        $text = collect($data['content'] ?? [])
            ->filter(fn ($block) => ($block['type'] ?? '') === 'text')
            ->map(fn ($block) => (string) ($block['text'] ?? ''))
            ->implode('');

        if ($text === '') {
            throw new RuntimeException('Anthropic Opus returned no text content.');
        }

        $usage = $data['usage'] ?? [];
        $gbp = $this->costCap->recordUsage($usage);

        return [
            'text' => $text,
            'usage' => $usage,
            'gbp' => $gbp,
        ];
    }

    private function apiKey(): string
    {
        $key = config('pipeline.anthropic.api_key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY is not set.');
        }

        return $key;
    }
}
