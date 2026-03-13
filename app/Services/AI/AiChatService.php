<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    private const API_URL = 'https://api.cerebras.ai/v1/chat/completions';

    private const TIMEOUT_SECONDS = 180;

    private const MAX_HISTORY_MESSAGES = 20;

    public function __construct(
        private readonly AiModelResolver $modelResolver,
        private readonly AiContextBuilder $contextBuilder,
        private readonly AiToolDefinitions $toolDefinitions,
        private readonly AiToolExecutor $toolExecutor,
    ) {}

    /**
     * Send a message and yield SSE chunks.
     *
     * @return \Generator yields SSE event arrays
     */
    public function sendMessage(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute = null
    ): \Generator {
        // Save user message
        $userMessage = $this->saveMessage($conversation, 'user', $message);

        // Build context
        $systemPrompt = $this->contextBuilder->buildSystemPrompt($user, $currentRoute);
        $messageHistory = $this->buildMessageHistory($conversation);
        $model = $this->modelResolver->getModel($user);
        $maxTokens = $this->modelResolver->getMaxTokens($user);

        // Auto-generate title from first message
        if ($conversation->message_count === 0) {
            $title = $this->generateTitle($message);
            $conversation->update(['title' => $title]);
            yield ['type' => 'title', 'title' => $title];
        }

        $totalInputTokens = 0;
        $totalOutputTokens = 0;

        $response = $this->callChatApi($model, $systemPrompt, $messageHistory, $maxTokens);

        if (isset($response['error'])) {
            Log::error('[AiChatService] Chat API error during conversation', [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'error' => $response['error'],
            ]);

            $hint = str_contains($response['error'], 'API key')
                ? 'Configuration issue — please contact support.'
                : 'I apologise, but I encountered an issue processing your request. Please try again.';

            yield ['type' => 'error', 'message' => $hint];

            return;
        }

        $totalInputTokens += $response['usage']['prompt_tokens'] ?? 0;
        $totalOutputTokens += $response['usage']['completion_tokens'] ?? 0;

        // Extract the response
        $choice = $response['choices'][0] ?? [];
        $responseMessage = $choice['message'] ?? [];
        $textContent = $responseMessage['content'] ?? '';

        if ($textContent) {
            yield ['type' => 'content', 'text' => $textContent];
        }

        // Save assistant message
        $assistantMessage = $this->saveMessage($conversation, 'assistant', $textContent, [
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
            'model_used' => $model,
        ]);

        // Update conversation token usage
        $conversation->incrementTokenUsage($totalInputTokens, $totalOutputTokens);
        $conversation->update(['model_used' => $model]);

        yield [
            'type' => 'done',
            'message_id' => $assistantMessage->id,
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
        ];
    }

    /**
     * Call the Cerebras Chat Completions API (OpenAI-compatible).
     *
     * Tools are not sent because llama3.1-8b outputs tool calls as text
     * instead of using the structured tool_calls format with tool_choice auto.
     * Tool support can be re-enabled when using a larger model.
     */
    private function callChatApi(
        string $model,
        string $systemPrompt,
        array $messages,
        int $maxTokens
    ): array {
        $apiKey = config('services.cerebras.api_key');

        if (! $apiKey) {
            Log::error('[AiChatService] Cerebras API key not configured');

            return ['error' => 'API key not configured'];
        }

        // Prepend system message to conversation history
        $allMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        $payload = [
            'model' => $model,
            'max_completion_tokens' => $maxTokens,
            'messages' => $allMessages,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::API_URL, $payload);

            if (! $response->successful()) {
                $errorBody = $response->json();
                $errorMessage = $errorBody['error']['message'] ?? $response->body();
                Log::error('[AiChatService] API error', [
                    'status' => $response->status(),
                    'error' => $errorMessage,
                ]);

                return ['error' => $errorMessage];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('[AiChatService] API request failed', [
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Build message history from conversation, keeping within token budget.
     */
    private function buildMessageHistory(AiConversation $conversation): array
    {
        $dbMessages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at', 'desc')
            ->limit(self::MAX_HISTORY_MESSAGES)
            ->get()
            ->reverse()
            ->values();

        $messages = [];

        foreach ($dbMessages as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        return $messages;
    }

    /**
     * Save a message to the database.
     */
    private function saveMessage(
        AiConversation $conversation,
        string $role,
        string $content,
        array $extra = []
    ): AiMessage {
        return $conversation->messages()->create(array_merge([
            'role' => $role,
            'content' => $content,
        ], $extra));
    }

    /**
     * Generate a short conversation title from the first message.
     */
    private function generateTitle(string $message): string
    {
        $title = mb_substr(trim($message), 0, 80);

        if (mb_strlen($message) > 80) {
            $title .= '...';
        }

        return $title;
    }
}
