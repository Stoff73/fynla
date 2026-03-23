<?php

declare(strict_types=1);

namespace App\Services\AI;

use OpenAI;
use OpenAI\Client;

/**
 * Singleton wrapper for the OpenAI PHP SDK configured for xAI Grok API.
 *
 * Uses the OpenAI SDK with a custom base URI pointing to xAI's API.
 * All xAI models are OpenAI-compatible, so the SDK works directly.
 */
class XaiClient
{
    private Client $client;

    public function __construct()
    {
        $apiKey = config('services.xai.api_key');
        $baseUrl = config('services.xai.base_url', 'https://api.x.ai/v1');

        if (empty($apiKey)) {
            throw new \RuntimeException('XAI_API_KEY is not configured. Set it in your .env file.');
        }

        $this->client = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withBaseUri($baseUrl)
            ->make();
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
