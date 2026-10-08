<?php

declare(strict_types=1);

namespace App\Services\AI;

use GuzzleHttp\Client as GuzzleClient;
use OpenAI;
use OpenAI\Client;
use OpenAI\Resources\Chat;

/**
 * Singleton wrapper for the OpenAI PHP SDK, the one client for every provider
 * that speaks the OpenAI Chat Completions format: xAI Grok and OpenAI GPT
 * ({@see AiProvider::speaksOpenAiFormat()}). The connection (API key and base
 * URL) is the provider's `config/services.php` block; it defaults to the
 * active provider's, and a chat loop passes its own provider snapshot.
 *
 * Guzzle is configured with a 120-second timeout. The chat models stream
 * within a few seconds; the generous timeout exists in case a slower model is
 * configured for evals or one-off testing.
 */
class XaiClient
{
    private Client $client;

    /** The OpenAI-format provider whose key and base URL the client uses. */
    private string $connection;

    private ?string $conversationId = null;

    public function __construct()
    {
        $this->connection = AiProvider::openAiFormatConnection(AiProvider::active());
        $this->client = $this->buildClient();
    }

    /**
     * Set the conversation for prompt cache routing, and the provider to talk
     * to (the chat loop's provider snapshot; null keeps the current one).
     *
     * xAI uses the x-grok-conv-id header to route requests to the same server,
     * dramatically increasing cache hit rates (75% discount on cached input tokens).
     * Must be called before chat() for each conversation.
     */
    public function forConversation(int|string $conversationId, ?string $provider = null): self
    {
        $this->conversationId = (string) $conversationId;

        if ($provider !== null) {
            $this->connection = AiProvider::openAiFormatConnection($provider);
        }

        $this->client = $this->buildClient();

        return $this;
    }

    /**
     * Build an OpenAI client for the current connection, with the xAI
     * conversation cache header when there is a conversation.
     */
    private function buildClient(): Client
    {
        $apiKey = (string) AiProvider::setting($this->connection, 'api_key');

        if ($apiKey === '') {
            throw new \RuntimeException(strtoupper($this->connection).'_API_KEY is not configured. Set it in your .env file.');
        }

        $httpClient = new GuzzleClient([
            'timeout' => 120,
            'connect_timeout' => 10,
        ]);

        $factory = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withBaseUri((string) AiProvider::setting($this->connection, 'base_url'))
            ->withHttpClient($httpClient);

        if ($this->conversationId !== null && $this->connection === AiProvider::XAI) {
            $factory = $factory->withHttpHeader('x-grok-conv-id', $this->conversationId);
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
     * Access the chat completions API, on the given provider's connection
     * when one is named (null keeps the current one).
     */
    public function chat(?string $provider = null): Chat
    {
        if ($provider !== null && AiProvider::openAiFormatConnection($provider) !== $this->connection) {
            $this->connection = AiProvider::openAiFormatConnection($provider);
            $this->client = $this->buildClient();
        }

        return $this->client->chat();
    }
}
