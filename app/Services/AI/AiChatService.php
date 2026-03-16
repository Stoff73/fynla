<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    private const TIMEOUT_SECONDS = 300;

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

        // R20: Check token budget before making API call
        if (! $this->modelResolver->hasTokenBudget($user)) {
            yield ['type' => 'error', 'message' => "You've reached your daily message limit. Your allowance resets tomorrow."];

            return;
        }

        // Build context
        $systemPrompt = $this->contextBuilder->buildSystemPrompt($user, $currentRoute);
        $messageHistory = $this->buildMessageHistory($conversation);

        // R15: Classify complexity for model tiering
        $complexity = $this->modelResolver->classifyComplexity($message, $conversation->message_count);
        $model = $this->modelResolver->getModel($user, $complexity);
        $maxTokens = $this->modelResolver->getMaxTokens($user);
        $tools = $this->toolDefinitions->getTools($user->is_preview_user);

        // Auto-generate title from first message
        if ($conversation->message_count === 0) {
            $title = $this->generateTitle($message);
            $conversation->update(['title' => $title]);
            yield ['type' => 'title', 'title' => $title];
        }

        // API call loop — handles tool calls and text responses
        $fullResponse = '';
        $toolCallCount = 0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $toolCallsSummary = [];

        $messages = $messageHistory;

        while (true) {
            // Stream from Anthropic and process SSE events
            $currentTextBlock = '';
            $currentToolUseBlock = null;
            $accumulatedToolJson = '';
            $contentBlocks = [];
            $toolUseBlocks = [];
            $stopReason = 'end_turn';
            $streamError = null;

            foreach ($this->streamAnthropicApi($model, $systemPrompt, $messages, $tools, $maxTokens) as $event) {
                if ($event['type'] === 'error') {
                    $streamError = $event;
                    break;
                }

                $eventType = $event['event'] ?? null;
                $data = $event['data'] ?? [];

                switch ($eventType) {
                    case 'message_start':
                        $totalInputTokens += $data['message']['usage']['input_tokens'] ?? 0;
                        break;

                    case 'content_block_start':
                        $blockType = $data['content_block']['type'] ?? '';
                        if ($blockType === 'text') {
                            $currentTextBlock = '';
                        } elseif ($blockType === 'tool_use') {
                            $currentToolUseBlock = [
                                'type' => 'tool_use',
                                'id' => $data['content_block']['id'] ?? '',
                                'name' => $data['content_block']['name'] ?? '',
                                'input' => [],
                            ];
                            $accumulatedToolJson = '';
                        }
                        break;

                    case 'content_block_delta':
                        $deltaType = $data['delta']['type'] ?? '';
                        if ($deltaType === 'text_delta') {
                            $text = $data['delta']['text'] ?? '';
                            if ($text !== '') {
                                $currentTextBlock .= $text;
                                $fullResponse .= $text;
                                // Yield text content IMMEDIATELY for real-time streaming
                                yield ['type' => 'content', 'text' => $text];
                            }
                        } elseif ($deltaType === 'input_json_delta') {
                            $accumulatedToolJson .= $data['delta']['partial_json'] ?? '';
                        }
                        break;

                    case 'content_block_stop':
                        if ($currentToolUseBlock !== null) {
                            // Parse accumulated JSON for tool input
                            if ($accumulatedToolJson !== '') {
                                $parsed = json_decode($accumulatedToolJson, true);
                                $currentToolUseBlock['input'] = is_array($parsed) ? $parsed : [];
                            }
                            $contentBlocks[] = $currentToolUseBlock;
                            $toolUseBlocks[] = $currentToolUseBlock;
                            $currentToolUseBlock = null;
                            $accumulatedToolJson = '';
                        } elseif ($currentTextBlock !== '') {
                            $contentBlocks[] = [
                                'type' => 'text',
                                'text' => $currentTextBlock,
                            ];
                            $currentTextBlock = '';
                        }
                        break;

                    case 'message_delta':
                        $stopReason = $data['delta']['stop_reason'] ?? $stopReason;
                        $totalOutputTokens += $data['usage']['output_tokens'] ?? 0;
                        break;

                    case 'message_stop':
                        // Stream complete for this request
                        break;

                    case 'ping':
                        // Ignore keep-alive pings
                        break;
                }
            }

            // Handle streaming errors
            if ($streamError !== null) {
                $errorMessage = $streamError['error_message'] ?? 'Unknown streaming error';
                $httpStatus = $streamError['http_status'] ?? null;
                $errorType = $streamError['error_type'] ?? null;

                Log::error('[AiChatService] Anthropic API error during conversation', [
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'error' => $errorMessage,
                    'http_status' => $httpStatus,
                    'error_type' => $errorType,
                ]);

                $hint = $this->categoriseError($errorMessage, $httpStatus, $errorType);

                yield ['type' => 'error', 'message' => $hint];

                return;
            }

            // Handle tool calls
            $hasToolCalls = ! empty($toolUseBlocks);

            if ($hasToolCalls) {
                // Add the full assistant message (with all content blocks) to history
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $contentBlocks,
                ];

                // Collect tool results for a single user message
                $toolResultBlocks = [];

                foreach ($toolUseBlocks as $toolUseBlock) {
                    $toolCallCount++;
                    $functionName = $toolUseBlock['name'];
                    $functionArgs = $toolUseBlock['input'] ?? [];

                    yield [
                        'type' => 'tool_use',
                        'tool' => $functionName,
                        'status' => 'running',
                    ];

                    // Execute the tool
                    $toolResult = $this->toolExecutor->execute(
                        $functionName,
                        $functionArgs,
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

                    // Collect tool call summary for metadata (R14)
                    $toolCallsSummary[] = [
                        'tool' => $functionName,
                        'input' => $this->summariseToolInput($functionArgs),
                        'result_summary' => $this->summariseToolResult($toolResult),
                    ];

                    // Collect tool result block (Anthropic format)
                    $toolResultBlocks[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $toolUseBlock['id'],
                        'content' => json_encode($toolResult),
                    ];

                    yield [
                        'type' => 'tool_use',
                        'tool' => $functionName,
                        'status' => 'complete',
                    ];
                }

                // Add all tool results as a single user message (Anthropic requires alternating roles)
                $messages[] = [
                    'role' => 'user',
                    'content' => $toolResultBlocks,
                ];
            }

            // If model wants to continue with tool results, loop
            if ($hasToolCalls && $stopReason === 'tool_use' && $toolCallCount < self::MAX_TOOL_CALLS_PER_TURN) {
                continue;
            }

            break;
        }

        // Build metadata with tool call summary (R14)
        $messageMetadata = [];
        if (! empty($toolCallsSummary)) {
            $messageMetadata['tool_calls'] = $toolCallsSummary;
        }

        // Save assistant message
        $assistantMessage = $this->saveMessage($conversation, 'assistant', $fullResponse, array_merge([
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
            'model_used' => $model,
        ], ! empty($messageMetadata) ? ['metadata' => $messageMetadata] : []));

        // Update conversation token usage
        $conversation->incrementTokenUsage($totalInputTokens, $totalOutputTokens);
        $conversation->update(['model_used' => $model]);

        // R20: Invalidate daily usage cache after recording tokens
        $this->modelResolver->invalidateDailyUsageCache($user);

        yield [
            'type' => 'done',
            'message_id' => $assistantMessage->id,
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
        ];
    }

    /**
     * Stream from the Anthropic Messages API using Guzzle, yielding parsed SSE events.
     *
     * @return \Generator yields parsed SSE event arrays with 'event' and 'data' keys
     */
    private function streamAnthropicApi(
        string $model,
        string $systemPrompt,
        array $messages,
        array $tools,
        int $maxTokens
    ): \Generator {
        $apiKey = config('services.anthropic.api_key');

        if (! $apiKey) {
            Log::error('[AiChatService] Anthropic API key not configured');

            yield [
                'type' => 'error',
                'error_message' => 'API key not configured',
                'http_status' => null,
                'error_type' => 'authentication',
            ];

            return;
        }

        // R4: Prompt caching — system as array with cache_control
        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'stream' => true,
            'system' => [
                [
                    'type' => 'text',
                    'text' => $systemPrompt,
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ],
            'messages' => $messages,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = ['type' => 'auto'];
        }

        try {
            $client = new Client([
                'timeout' => self::TIMEOUT_SECONDS,
                'connect_timeout' => 30,
            ]);

            $response = $client->post(self::API_URL, [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'anthropic-beta' => 'prompt-caching-2024-07-31',
                    'Content-Type' => 'application/json',
                    'Accept' => 'text/event-stream',
                ],
                'json' => $payload,
                'stream' => true,
                'http_errors' => false,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $errorBody = (string) $response->getBody();
                $errorData = json_decode($errorBody, true);
                $errorMessage = $errorData['error']['message'] ?? $errorBody;
                $errorType = $errorData['error']['type'] ?? null;

                Log::error('[AiChatService] Anthropic API error', [
                    'status' => $statusCode,
                    'error' => $errorMessage,
                    'error_type' => $errorType,
                ]);

                yield [
                    'type' => 'error',
                    'error_message' => $errorMessage,
                    'http_status' => $statusCode,
                    'error_type' => $errorType,
                ];

                return;
            }

            // Read the SSE stream incrementally
            $body = $response->getBody();
            $buffer = '';

            while (! $body->eof()) {
                $chunk = $body->read(8192);
                if ($chunk === '') {
                    continue;
                }

                $buffer .= $chunk;

                // Process complete SSE events from buffer
                while (($doubleNewlinePos = strpos($buffer, "\n\n")) !== false) {
                    $rawEvent = substr($buffer, 0, $doubleNewlinePos);
                    $buffer = substr($buffer, $doubleNewlinePos + 2);

                    $parsed = $this->parseSseEvent($rawEvent);
                    if ($parsed !== null) {
                        // Check for error events in the stream
                        if ($parsed['event'] === 'error') {
                            $errorData = $parsed['data'];
                            yield [
                                'type' => 'error',
                                'error_message' => $errorData['error']['message'] ?? 'Stream error',
                                'http_status' => null,
                                'error_type' => $errorData['error']['type'] ?? 'stream_error',
                            ];

                            return;
                        }

                        yield $parsed;
                    }
                }
            }

            // Process any remaining data in buffer
            if (trim($buffer) !== '') {
                $parsed = $this->parseSseEvent($buffer);
                if ($parsed !== null) {
                    yield $parsed;
                }
            }

        } catch (\Exception $e) {
            Log::error('[AiChatService] Anthropic API streaming request failed', [
                'error' => $e->getMessage(),
            ]);

            yield [
                'type' => 'error',
                'error_message' => $e->getMessage(),
                'http_status' => null,
                'error_type' => 'connection_error',
            ];
        }
    }

    /**
     * Parse a raw SSE event string into structured data.
     */
    private function parseSseEvent(string $raw): ?array
    {
        $eventType = null;
        $dataLines = [];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, 'event: ')) {
                $eventType = substr($line, 7);
            } elseif (str_starts_with($line, 'data: ')) {
                $dataLines[] = substr($line, 6);
            }
        }

        if ($eventType === null) {
            return null;
        }

        $dataJson = implode("\n", $dataLines);
        $data = json_decode($dataJson, true) ?? [];

        return [
            'event' => $eventType,
            'data' => $data,
        ];
    }

    /**
     * Categorise an API error into a user-friendly message (R13).
     */
    private function categoriseError(?string $errorMessage, ?int $httpStatus, ?string $errorType): string
    {
        // HTTP status-based categorisation
        if ($httpStatus === 429) {
            return "You've sent several messages quickly. Please wait a moment before trying again.";
        }

        if ($httpStatus === 529) {
            return 'The service is temporarily busy. Please try again in a moment.';
        }

        // Authentication errors
        if ($httpStatus === 401 || $httpStatus === 403) {
            return 'Configuration issue — please contact support.';
        }

        // Error type and message-based categorisation
        $errorString = ($errorMessage ?? '').' '.($errorType ?? '');
        $errorLower = strtolower($errorString);

        if (str_contains($errorLower, 'api_key') || str_contains($errorLower, 'authentication') || str_contains($errorLower, 'invalid_api_key')) {
            return 'Configuration issue — please contact support.';
        }

        if (str_contains($errorLower, 'context_length') || str_contains($errorLower, 'token') || str_contains($errorLower, 'too many tokens') || str_contains($errorLower, 'max_tokens')) {
            return 'This conversation has become quite long. Starting a new conversation may help.';
        }

        if (str_contains($errorLower, 'overloaded') || str_contains($errorLower, 'capacity')) {
            return 'The service is temporarily busy. Please try again in a moment.';
        }

        if (str_contains($errorLower, 'rate_limit')) {
            return "You've sent several messages quickly. Please wait a moment before trying again.";
        }

        return 'I apologise, but I encountered an issue processing your request. Please try again.';
    }

    /**
     * Build message history from conversation, keeping within token budget.
     * Enriches assistant messages with tool call context from metadata (R14).
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
            $content = $msg->content;

            // R14: Append tool call context from metadata for assistant messages
            if ($msg->role === 'assistant' && ! empty($msg->metadata['tool_calls'])) {
                $toolContext = $this->buildToolCallContext($msg->metadata['tool_calls']);
                if ($toolContext !== '') {
                    $content .= "\n\n".$toolContext;
                }
            }

            $messages[] = [
                'role' => $msg->role,
                'content' => $content,
            ];
        }

        return $messages;
    }

    /**
     * Build a brief context note from tool call metadata (R14).
     */
    private function buildToolCallContext(array $toolCalls): string
    {
        if (empty($toolCalls)) {
            return '';
        }

        $parts = [];
        foreach ($toolCalls as $call) {
            $tool = $call['tool'] ?? 'unknown';
            $summary = $call['result_summary'] ?? '';
            $parts[] = "- {$tool}: {$summary}";
        }

        return "[Context: This response used the following data lookups]\n".implode("\n", $parts);
    }

    /**
     * Summarise tool input by extracting key parameters (R14).
     */
    private function summariseToolInput(array $input): array
    {
        if (empty($input)) {
            return [];
        }

        $summary = [];
        $count = 0;

        foreach ($input as $key => $value) {
            if ($count >= 5) {
                break;
            }

            if (is_string($value)) {
                $summary[$key] = mb_strlen($value) > 80 ? mb_substr($value, 0, 80).'...' : $value;
            } elseif (is_numeric($value) || is_bool($value)) {
                $summary[$key] = $value;
            } elseif (is_array($value)) {
                $summary[$key] = '[array: '.count($value).' items]';
            } else {
                $summary[$key] = (string) $value;
            }

            $count++;
        }

        return $summary;
    }

    /**
     * Summarise a tool result by extracting the first few key fields (R14).
     */
    private function summariseToolResult(array $result): string
    {
        if (empty($result)) {
            return 'empty result';
        }

        $parts = [];
        $count = 0;

        foreach ($result as $key => $value) {
            if ($count >= 5) {
                break;
            }

            if (is_string($value)) {
                $truncated = mb_strlen($value) > 60 ? mb_substr($value, 0, 60).'...' : $value;
                $parts[] = "{$key}: {$truncated}";
            } elseif (is_numeric($value)) {
                $parts[] = "{$key}: {$value}";
            } elseif (is_bool($value)) {
                $parts[] = "{$key}: ".($value ? 'true' : 'false');
            } elseif (is_array($value)) {
                $parts[] = "{$key}: [".count($value).' items]';
            }

            $count++;
        }

        return implode(', ', $parts) ?: 'processed';
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
