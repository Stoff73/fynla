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
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const ANTHROPIC_VERSION = '2023-06-01';

    private const TIMEOUT_SECONDS = 180;

    private const MAX_TOOL_CALLS_PER_TURN = 5;

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
        $tools = $this->toolDefinitions->getTools($user->is_preview_user);

        // Auto-generate title from first message
        if ($conversation->message_count === 0) {
            $title = $this->generateTitle($message);
            $conversation->update(['title' => $title]);
            yield ['type' => 'title', 'title' => $title];
        }

        // Initial API call
        $fullResponse = '';
        $toolCallCount = 0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;

        $messages = $messageHistory;

        while (true) {
            $response = $this->callAnthropicApi($model, $systemPrompt, $messages, $tools, $maxTokens);

            if (isset($response['error'])) {
                yield ['type' => 'error', 'message' => 'I apologise, but I encountered an issue processing your request. Please try again.'];

                return;
            }

            $totalInputTokens += $response['usage']['input_tokens'] ?? 0;
            $totalOutputTokens += $response['usage']['output_tokens'] ?? 0;

            // Process content blocks
            $hasToolUse = false;
            $assistantContent = [];

            foreach ($response['content'] ?? [] as $block) {
                if ($block['type'] === 'text') {
                    $fullResponse .= $block['text'];
                    yield ['type' => 'content', 'text' => $block['text']];
                    $assistantContent[] = $block;
                } elseif ($block['type'] === 'tool_use') {
                    $hasToolUse = true;
                    $toolCallCount++;
                    $assistantContent[] = $block;

                    yield [
                        'type' => 'tool_use',
                        'tool' => $block['name'],
                        'status' => 'running',
                    ];

                    // Execute the tool
                    $toolResult = $this->toolExecutor->execute(
                        $block['name'],
                        $block['input'] ?? [],
                        $user
                    );

                    // Handle navigation results
                    if (isset($toolResult['action']) && $toolResult['action'] === 'navigate') {
                        yield [
                            'type' => 'navigation',
                            'route_path' => $toolResult['route_path'],
                            'description' => $toolResult['description'] ?? '',
                        ];
                    }

                    // Handle entity creation results
                    if (isset($toolResult['created']) && $toolResult['created'] === true) {
                        yield [
                            'type' => 'entity_created',
                            'entity_type' => $toolResult['entity_type'],
                            'entity_id' => $toolResult['entity_id'],
                            'name' => $toolResult['name'] ?? '',
                        ];
                    }

                    // Add assistant message with tool use, then tool result
                    $messages[] = ['role' => 'assistant', 'content' => $assistantContent];
                    $messages[] = [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'tool_result',
                                'tool_use_id' => $block['id'],
                                'content' => json_encode($toolResult),
                            ],
                        ],
                    ];

                    // Reset assistant content for next iteration
                    $assistantContent = [];

                    yield [
                        'type' => 'tool_use',
                        'tool' => $block['name'],
                        'status' => 'complete',
                    ];
                }
            }

            // If model wants to continue with tool results, loop
            $stopReason = $response['stop_reason'] ?? 'end_turn';

            if ($hasToolUse && $stopReason === 'tool_use' && $toolCallCount < self::MAX_TOOL_CALLS_PER_TURN) {
                continue;
            }

            break;
        }

        // Save assistant message
        $assistantMessage = $this->saveMessage($conversation, 'assistant', $fullResponse, [
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
     * Call the Anthropic Messages API.
     */
    private function callAnthropicApi(
        string $model,
        string $systemPrompt,
        array $messages,
        array $tools,
        int $maxTokens
    ): array {
        $apiKey = config('services.anthropic.api_key');

        if (! $apiKey) {
            Log::error('[AiChatService] Anthropic API key not configured');

            return ['error' => 'API key not configured'];
        }

        $headers = [
            'x-api-key' => $apiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
            'content-type' => 'application/json',
        ];

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        try {
            $response = Http::withHeaders($headers)
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
