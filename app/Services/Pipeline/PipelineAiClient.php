<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Provider-aware text generation for the marketing pipeline.
 *
 * The pipeline follows the application's existing AI_PROVIDER setting. The
 * Anthropic implementation remains available, while xAI uses its
 * OpenAI-compatible chat-completions endpoint and returns the same normalised
 * result shape to pipeline consumers.
 */
class PipelineAiClient
{
    public function __construct(
        private readonly AnthropicOpusClient $anthropic,
        private readonly CostCap $costCap,
    ) {}

    /**
     * @param  list<array{type:'text',text:string,cache_control?:array{type:'ephemeral'}}>  $systemBlocks
     * @param  list<array{role:string,content:string}>  $messages
     * @return array{text:string,usage:array<string,int>,gbp:float,model:string,provider:string}
     */
    public function complete(array $systemBlocks, array $messages): array
    {
        $provider = strtolower((string) config('services.ai_provider', 'anthropic'));

        return match ($provider) {
            'anthropic' => $this->completeWithAnthropic($systemBlocks, $messages),
            'xai' => $this->completeWithXai($systemBlocks, $messages),
            default => throw new RuntimeException("Unsupported pipeline AI provider: {$provider}"),
        };
    }

    private function completeWithAnthropic(array $systemBlocks, array $messages): array
    {
        $completion = $this->anthropic->complete($systemBlocks, $messages);

        return array_merge($completion, [
            'model' => (string) config('pipeline.anthropic.model', 'claude-opus-4-7'),
            'provider' => 'anthropic',
        ]);
    }

    private function completeWithXai(array $systemBlocks, array $messages): array
    {
        $this->costCap->ensureDailyBudgetAvailable();

        $apiKey = (string) config('services.xai.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('XAI_API_KEY is not set.');
        }

        $model = (string) config(
            'services.xai.advanced_chat_model',
            config('services.xai.chat_model', 'grok-4.3'),
        );
        $baseUrl = rtrim((string) config('services.xai.base_url', 'https://api.x.ai/v1'), '/');

        $systemText = collect($systemBlocks)
            ->map(static fn (array $block): string => (string) ($block['text'] ?? ''))
            ->filter(static fn (string $text): bool => $text !== '')
            ->implode("\n\n");

        $chatMessages = $systemText === ''
            ? $messages
            : array_merge([['role' => 'system', 'content' => $systemText]], $messages);

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('pipeline.anthropic.timeout_seconds', 120))
            ->retry(2, 5000, static fn ($e): bool => $e instanceof ConnectionException)
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'messages' => $chatMessages,
                'max_tokens' => (int) config('pipeline.anthropic.max_output_tokens', 4096),
            ]);

        if (! $response->successful()) {
            Log::channel('pipeline')->error('xAI pipeline call failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('xAI pipeline call failed: HTTP '.$response->status().' — '.$response->body());
        }

        $text = trim((string) $response->json('choices.0.message.content', ''));
        if ($text === '') {
            throw new RuntimeException('xAI returned no text content.');
        }

        $promptTokens = (int) $response->json('usage.prompt_tokens', 0);
        $cacheReadTokens = (int) $response->json('usage.prompt_tokens_details.cached_tokens', 0);
        $usage = [
            'input_tokens' => max(0, $promptTokens - $cacheReadTokens),
            'output_tokens' => (int) $response->json('usage.completion_tokens', 0),
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => $cacheReadTokens,
        ];

        return [
            'text' => $text,
            'usage' => $usage,
            'gbp' => $this->costCap->recordModelUsage($model, $usage),
            'model' => $model,
            'provider' => 'xai',
        ];
    }
}
