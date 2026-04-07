<?php

declare(strict_types=1);

namespace App\Services\AI;

use GuzzleHttp\Client as GuzzleClient;
use OpenAI;
use OpenAI\Client;

/**
 * Singleton wrapper for the OpenAI PHP SDK configured for xAI Grok API.
 *
 * Uses the OpenAI SDK with a custom base URI pointing to xAI's API.
 * All xAI models are OpenAI-compatible, so the SDK works directly.
 *
 * Guzzle is configured with a 120-second timeout to accommodate
 * reasoning models (grok-4-1-fast-reasoning) which may "think"
 * for 30-60+ seconds before streaming any response chunks.
 */
class XaiClient
{
    private Client $client;

    private ?string $conversationId = null;

    public function __construct()
    {
        $apiKey = config('services.xai.api_key');
        $baseUrl = config('services.xai.base_url', 'https://api.x.ai/v1');

        if (empty($apiKey)) {
            throw new \RuntimeException('XAI_API_KEY is not configured. Set it in your .env file.');
        }

        $this->client = $this->buildClient($apiKey, $baseUrl);
    }

    /**
     * Set the conversation ID for prompt cache routing.
     *
     * xAI uses the x-grok-conv-id header to route requests to the same server,
     * dramatically increasing cache hit rates (75% discount on cached input tokens).
     * Must be called before chat() for each conversation.
     */
    public function forConversation(int|string $conversationId): self
    {
        $this->conversationId = (string) $conversationId;

        $apiKey = config('services.xai.api_key');
        $baseUrl = config('services.xai.base_url', 'https://api.x.ai/v1');

        $this->client = $this->buildClient($apiKey, $baseUrl, $this->conversationId);

        return $this;
    }

    /**
     * Build an OpenAI client instance, optionally with a conversation cache header.
     */
    private function buildClient(string $apiKey, string $baseUrl, ?string $conversationId = null): Client
    {
        $httpClient = new GuzzleClient([
            'timeout' => 120,
            'connect_timeout' => 10,
        ]);

        $factory = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withBaseUri($baseUrl)
            ->withHttpClient($httpClient);

        if ($conversationId !== null) {
            $factory = $factory->withHttpHeader('x-grok-conv-id', $conversationId);
        }

        return $factory->make();
    }

    /**
     * Get the underlying OpenAI client instance.
     */
    public function client(): Client
    {
        return $this->client;
    }

    /**
     * Access the chat completions API.
     */
    public function chat(): \OpenAI\Resources\Chat
    {
        return $this->client->chat();
    }

    /**
     * Get the configured chat model name.
     */
    public static function chatModel(): string
    {
        return config('services.xai.chat_model', 'grok-4-1-fast-reasoning');
    }

    /**
     * Get the configured advanced/complex model name.
     */
    public static function advancedModel(): string
    {
        return config('services.xai.advanced_chat_model', 'grok-4-1-fast-reasoning');
    }

    /**
     * Get the configured vision model name.
     */
    public static function visionModel(): string
    {
        return config('services.xai.vision_model', 'grok-4-1-fast-non-reasoning');
    }
}
