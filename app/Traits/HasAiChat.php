<?php

declare(strict_types=1);

namespace App\Traits;

use Anthropic\Client;
use Anthropic\Client as AnthropicClient;
use Anthropic\Messages\InputJSONDelta;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawContentBlockStartEvent;
use Anthropic\Messages\RawContentBlockStopEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\ToolUseBlock;
use App\Constants\QuerySchemas;
// Anthropic SDK imports — only used when AI_PROVIDER=anthropic
use App\Models\AiAbortEvent;
use App\Models\AiAdviceLog;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\AdviceFyn;
use App\Services\AI\AdvicePromptBuilder;
use App\Services\AI\KycGateChecker;
use App\Services\AI\QueryClassifier;
use App\Services\AI\StructuredResponseValidator;
use App\Services\AI\XaiClient;
use App\Services\AI\XaiToolDefinitions;
use App\Services\Eval\EvalBypassGate;
use App\Services\PrerequisiteGateService;
use App\Support\XaiFunctionCallLeakStripper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Provides AI chat capabilities: streaming completion, prompt building,
 * tool call loop, and message persistence.
 *
 * Expects the using class to have:
 * - HasAiGuardrails trait
 * - AnthropicClient or XaiClient resolved from container based on AI_PROVIDER config
 * - $this->prerequisiteGate (PrerequisiteGateService)
 * - $this->taxConfig (TaxConfigService)
 * - Module agent properties (protectionAgent, savingsAgent, etc.)
 * - $this->toolDefinitions (AiToolDefinitions)
 */
trait HasAiChat
{
    /**
     * Default tool-call cap when no engine-level signal is available.
     * Used by code paths outside AdviceFyn (e.g. onboarding asset_capture
     * delegations) where module-tier intensity is the right floor.
     */
    private const MAX_TOOL_CALLS_PER_TURN = 5;

    /**
     * April30Updates F-15 — engine-level-aware caps. Holistic chats need
     * more tools (orchestrate + 3 module analyses + 1 calculation can
     * easily reach 5+) so capping at 5 truncated genuine reasoning chains.
     * Factual queries are capped lower because they shouldn't need many
     * tool calls — burning 5 on a billing query is a sign of confusion.
     */
    private const TOOL_CALL_CAPS_BY_LEVEL = [
        'holistic' => 8,
        'module' => 5,
        'factual' => 3,
    ];

    private const MAX_HISTORY_MESSAGES = 20;

    /**
     * Chat overrides used by OnboardingChatDirector during delegated
     * asset_capture turns. Per-call state only — always cleared via the
     * try/finally block in chatWithPromptOverride().
     */
    private ?string $systemPromptOverride = null;

    /** @var list<string>|null */
    private ?array $allowedToolsOverride = null;

    private bool $skipUserMessagePersistence = false;

    /**
     * Replacement tool list for grouped_extract turns. When set, takes
     * priority over getTools() + allowedToolsOverride — Claude sees ONLY
     * these tools. Used so onboarding extraction tools never pollute the
     * main Fyn chat token budget.
     *
     * @var list<array<string, mixed>>|null
     */
    private ?array $toolsListOverride = null;

    /**
     * Persona tag applied to the assistant AiMessage row persisted by this
     * chat() call. Set to 'advice' by AdviceFyn::handle and 'data_capture'
     * by OnboardingChatDirector::handleInlineCapture so history can be
     * grouped by persona. Null outside those paths (legacy flows leave
     * ai_messages.persona null for backwards compatibility). Must match the
     * `ai_messages.persona` enum (['advice', 'data_capture']).
     */
    private ?string $personaOverride = null;

    /**
     * Send a message and yield SSE chunks.
     *
     * @return \Generator yields SSE event arrays
     */
    public function chat(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute = null
    ): \Generator {
        // Save user message (skipped when the caller has already persisted
        // the message itself — for example OnboardingChatDirector saves it
        // BEFORE delegating here, so the history reflects the user turn
        // even if the delegated call fails).
        if (! $this->skipUserMessagePersistence) {
            $userMessage = $this->saveMessage($conversation, 'user', $message);
        }

        // Check token budget
        if (! $this->hasTokenBudget($user)) {
            $usage = $this->getTokenUsageDetails($user);
            yield [
                'type' => 'token_limit',
                'message' => "You've reached your daily Fyn usage limit.",
                'reset_at' => $usage['reset_at'],
                'seconds_until_reset' => $usage['seconds_until_reset'],
            ];

            return;
        }

        // Classify query and check KYC
        $classifier = app(QueryClassifier::class);
        $classification = $classifier->classify($message, $currentRoute);

        $kycResult = null;
        if (! QuerySchemas::isBypassType($classification['primary'])
            && $classification['primary'] !== QuerySchemas::GENERAL) {
            $kycChecker = app(KycGateChecker::class);
            $kycResult = $kycChecker->check($user, $classification);
        }

        // Build context
        $systemPrompt = $this->systemPromptOverride
            ?? $this->buildSystemPrompt($user, $currentRoute, $classification, $kycResult, $conversation);
        $messageHistory = $this->buildMessageHistory($conversation);

        // Model selection
        $complexity = $this->classifyComplexity($message, $conversation->message_count);
        $model = $this->getAiModel($user, $complexity);

        // Soft-degrade notice (PR 6 — Rule #16: plain text only, no icon/emoji/glyph).
        // Prepend to the system prompt so the AI knows to keep responses shorter and
        // to relay the notice. Chat is NEVER hard-walled by the weekly budget.
        if ($this->isWeeklyBudgetExceeded($user)) {
            $systemPrompt = 'Fyn is running in a lighter mode this week — upgrade for full responses.'
                ."\n\n".$systemPrompt;
        }
        $maxTokens = $this->getAiMaxTokens($user);
        $isXai = $this->getAiProvider() === 'xai';
        $toolDefinitions = $isXai
            ? app(XaiToolDefinitions::class)
            : $this->toolDefinitions;

        // Tool list priority: toolsListOverride > allowedToolsOverride > getTools().
        // Directors can either replace the full tool list (grouped_extract) or
        // narrow the existing list to a subset (asset_capture).
        if ($this->toolsListOverride !== null) {
            $tools = $this->toolsListOverride;
        } else {
            // Bypass when the active token EXPLICITLY lists `bypass-preview-mode`
            // (eval flow) AND the X-Eval-Run-Id header is present
            // (April30Updates F-12 — defence-in-depth so a leaked token
            // alone is not enough to bypass preview filtering).
            $hasEvalBypass = EvalBypassGate::isActive($user);
            $tools = $toolDefinitions->getTools($user->is_preview_user && ! $hasEvalBypass);

            if ($this->allowedToolsOverride !== null) {
                $allowed = array_flip($this->allowedToolsOverride);
                $tools = array_values(array_filter($tools, function ($tool) use ($allowed): bool {
                    $name = $tool['name']
                        ?? ($tool['function']['name'] ?? null);

                    return $name !== null && isset($allowed[$name]);
                }));
            }
        }

        // Auto-generate title from first message
        if ($conversation->message_count === 0) {
            $title = $this->generateTitle($message);
            $conversation->update(['title' => $title]);
            yield ['type' => 'title', 'title' => $title];
        }

        // April30Updates F-15 — engine-level-aware tool call cap.
        // Holistic queries need more tools, factual queries fewer.
        $engineLevel = AdviceFyn::engineCallLevelFor(
            $classification['primary'] ?? null
        );
        $toolCallCap = self::TOOL_CALL_CAPS_BY_LEVEL[$engineLevel]
            ?? self::MAX_TOOL_CALLS_PER_TURN;

        // API call loop — handles tool calls and text responses
        $fullResponse = '';
        $toolCallCount = 0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $totalCachedTokens = 0;
        $toolCallsSummary = [];
        $messages = $messageHistory;

        // S0.11.2 — track the last tool dispatched + how many wrote so an
        // ai_abort_events row can capture them on a mid-stream disconnect.
        $lastToolCall = null;
        $partialWriteCount = 0;

        // For xAI: XaiToolDefinitions returns pre-wrapped tools, use directly.
        // For Anthropic: AiToolDefinitions returns Anthropic format, not used in xAI path.
        $xaiTools = $isXai ? $tools : [];

        while (true) {
            // S0.11.2 — bail out cleanly if the SSE client has dropped.
            // Per INV-2.9.2 we DO NOT roll back the partial writes that
            // already landed — every direct-write tool runs in its own
            // transaction and what's persisted stays.
            if ($this->wasConnectionAborted()) {
                $this->recordAbort($conversation, $lastToolCall, $partialWriteCount);

                return;
            }

            $contentBlocks = [];
            $toolUseBlocks = [];
            $stopReason = 'end_turn';

            try {
                if ($isXai) {
                    // ── xAI / OpenAI streaming ──────────────────────────────
                    $xaiClient = app(XaiClient::class)
                        ->forConversation($conversation->id);
                    $xaiMessages = array_merge(
                        [['role' => 'system', 'content' => $systemPrompt]],
                        $messages
                    );

                    $params = [
                        'model' => $model,
                        'messages' => $xaiMessages,
                        'max_completion_tokens' => $maxTokens,
                        'temperature' => 0,
                        'reasoning_effort' => 'none',
                        'stream' => true,
                        'stream_options' => ['include_usage' => true],
                    ];
                    if (! empty($xaiTools)) {
                        $params['tools'] = $xaiTools;
                        $params['tool_choice'] = 'auto';
                    }

                    $stream = $xaiClient->chat()->createStreamed($params);

                    $currentText = '';
                    // Tool calls indexed by position (OpenAI streams tool_calls with index)
                    $pendingToolCalls = [];

                    foreach ($stream as $response) {
                        $delta = $response->choices[0]->delta ?? null;
                        $finishReason = $response->choices[0]->finishReason ?? null;

                        if ($delta) {
                            // Text content
                            if (isset($delta->content) && $delta->content !== null && $delta->content !== '') {
                                $text = $delta->content;
                                // Strip dangerous HTML tags from AI output
                                $text = preg_replace('/<\s*(script|iframe|object|embed|form|input|link|meta|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $text);
                                $text = preg_replace('/<\s*(script|iframe|object|embed|form|input|link|meta|style)\b[^>]*\/?>/is', '', $text);
                                $currentText .= $text;
                                $fullResponse .= $text;
                                yield ['type' => 'content', 'text' => $text];
                            }

                            // Tool call deltas (OpenAI streams these with index)
                            if (isset($delta->toolCalls)) {
                                foreach ($delta->toolCalls as $toolCallDelta) {
                                    $idx = $toolCallDelta->index;
                                    if (! isset($pendingToolCalls[$idx])) {
                                        $pendingToolCalls[$idx] = [
                                            'id' => $toolCallDelta->id ?? '',
                                            'name' => '',
                                            'arguments' => '',
                                        ];
                                    }
                                    if (isset($toolCallDelta->id) && $toolCallDelta->id) {
                                        $pendingToolCalls[$idx]['id'] = $toolCallDelta->id;
                                    }
                                    if (isset($toolCallDelta->function->name) && $toolCallDelta->function->name) {
                                        $pendingToolCalls[$idx]['name'] = $toolCallDelta->function->name;
                                    }
                                    if (isset($toolCallDelta->function->arguments)) {
                                        $pendingToolCalls[$idx]['arguments'] .= $toolCallDelta->function->arguments;
                                    }
                                }
                            }
                        }

                        if ($finishReason === 'tool_calls') {
                            $stopReason = 'tool_use';
                        } elseif ($finishReason === 'stop') {
                            $stopReason = 'end_turn';
                        }
                    }

                    // Track token usage from stream (may not be available in all streaming responses)
                    if (isset($response->usage)) {
                        $totalInputTokens += $response->usage->promptTokens
                            ?? $response->usage->prompt_tokens ?? 0;
                        $totalOutputTokens += $response->usage->completionTokens
                            ?? $response->usage->completion_tokens ?? 0;

                        // Track cached tokens for cost monitoring (xAI prompt caching)
                        if (isset($response->usage->promptTokensDetails->cachedTokens)) {
                            $totalCachedTokens += $response->usage->promptTokensDetails->cachedTokens;
                        }
                    }

                    // Build content blocks from accumulated text
                    if ($currentText !== '') {
                        $contentBlocks[] = ['type' => 'text', 'text' => $currentText];
                    }

                    // Build tool use blocks from accumulated tool calls
                    foreach ($pendingToolCalls as $tc) {
                        $parsed = json_decode($tc['arguments'], true);
                        $toolBlock = [
                            'type' => 'tool_use',
                            'id' => $tc['id'],
                            'name' => $tc['name'],
                            'input' => is_array($parsed) ? $parsed : [],
                        ];
                        $contentBlocks[] = $toolBlock;
                        $toolUseBlocks[] = $toolBlock;
                    }

                } else {
                    // ── Anthropic streaming (legacy) ─────────────────────────
                    $currentTextBlock = '';
                    $currentToolUseBlock = null;
                    $accumulatedToolJson = '';

                    $anthropicClient = app(Client::class);
                    $stream = $anthropicClient->messages->createStream(
                        maxTokens: $maxTokens,
                        messages: $messages,
                        model: $model,
                        system: [
                            [
                                'type' => 'text',
                                'text' => $systemPrompt,
                                'cache_control' => ['type' => 'ephemeral'],
                            ],
                        ],
                        tools: ! empty($tools) ? $tools : null,
                        toolChoice: ! empty($tools) ? ['type' => 'auto'] : null,
                    );

                    foreach ($stream as $event) {
                        if ($event instanceof RawMessageStartEvent) {
                            $totalInputTokens += $event->message->usage->inputTokens ?? 0;

                            // April30Updates F-4 — capture Anthropic prompt-cache
                            // hit rate. The SDK exposes cache_read_input_tokens
                            // (existing-cache hits) and cache_creation_input_tokens
                            // (write-through on first turn) on the message usage
                            // object. Without this, the cache_hit_rate metric on
                            // ai_messages.metadata was always 0 for Anthropic,
                            // which means we couldn't tell if caching was working.
                            $usage = $event->message->usage ?? null;
                            if ($usage !== null) {
                                $cacheRead = $usage->cacheReadInputTokens
                                    ?? $usage->cache_read_input_tokens
                                    ?? 0;
                                $totalCachedTokens += (int) $cacheRead;
                            }
                        } elseif ($event instanceof RawContentBlockStartEvent) {
                            if ($event->contentBlock instanceof TextBlock) {
                                $currentTextBlock = '';
                            } elseif ($event->contentBlock instanceof ToolUseBlock) {
                                $currentToolUseBlock = [
                                    'type' => 'tool_use',
                                    'id' => $event->contentBlock->id,
                                    'name' => $event->contentBlock->name,
                                    'input' => [],
                                ];
                                $accumulatedToolJson = '';
                            }
                        } elseif ($event instanceof RawContentBlockDeltaEvent) {
                            if ($event->delta instanceof TextDelta) {
                                $text = $event->delta->text ?? '';
                                if ($text !== '') {
                                    $text = preg_replace('/<\s*(script|iframe|object|embed|form|input|link|meta|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $text);
                                    $text = preg_replace('/<\s*(script|iframe|object|embed|form|input|link|meta|style)\b[^>]*\/?>/is', '', $text);
                                    $currentTextBlock .= $text;
                                    $fullResponse .= $text;
                                    yield ['type' => 'content', 'text' => $text];
                                }
                            } elseif ($event->delta instanceof InputJSONDelta) {
                                $accumulatedToolJson .= $event->delta->partialJSON ?? '';
                            }
                        } elseif ($event instanceof RawContentBlockStopEvent) {
                            if ($currentToolUseBlock !== null) {
                                if ($accumulatedToolJson !== '') {
                                    $parsed = json_decode($accumulatedToolJson, true);
                                    $currentToolUseBlock['input'] = is_array($parsed) ? $parsed : [];
                                }
                                $contentBlocks[] = $currentToolUseBlock;
                                $toolUseBlocks[] = $currentToolUseBlock;
                                $currentToolUseBlock = null;
                                $accumulatedToolJson = '';
                            } elseif ($currentTextBlock !== '') {
                                $contentBlocks[] = ['type' => 'text', 'text' => $currentTextBlock];
                                $currentTextBlock = '';
                            }
                        } elseif ($event instanceof RawMessageDeltaEvent) {
                            $stopReason = $event->delta->stopReason ?? $stopReason;
                            $totalOutputTokens += $event->usage->outputTokens ?? 0;
                        }
                    }
                }
            } catch (\Exception $e) {
                $provider = $isXai ? 'xAI' : 'Anthropic';

                // April30Updates F-13 — retry once per turn on transient
                // errors. 429 (rate limit), 529 (overloaded), and network
                // timeout are common in production; a single 1.5s backoff
                // masks most of them. Non-retriable errors (auth, config,
                // context-length) fall through to the user-facing error.
                // Only retry on the FIRST iteration with no partial output —
                // mid-turn failures after tool calls have run are not safe
                // to replay (the model would re-do completed tool work).
                $retryable = $this->isRetriableLlmError($e);
                $turnRetried = $turnRetried ?? false;

                if ($retryable && ! $turnRetried && $toolCallCount === 0 && $fullResponse === '') {
                    Log::warning("[CoordinatingAgent] {$provider} transient error — retrying once", [
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                    usleep(1_500_000); // 1.5s — covers most rate-limit windows
                    $turnRetried = true;

                    continue;
                }

                Log::error("[CoordinatingAgent] {$provider} API streaming failed", [
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                $hint = $this->categoriseApiError($e->getMessage(), null, null);
                yield ['type' => 'error', 'message' => $hint];

                return;
            }

            // Handle tool calls (shared logic for both providers)
            $hasToolCalls = ! empty($toolUseBlocks);

            if ($hasToolCalls) {
                if ($isXai) {
                    // OpenAI format: assistant message with tool_calls array
                    $assistantToolCalls = array_map(fn ($tb) => [
                        'id' => $tb['id'],
                        'type' => 'function',
                        'function' => [
                            'name' => $tb['name'],
                            'arguments' => json_encode($tb['input']),
                        ],
                    ], $toolUseBlocks);

                    $assistantMsg = ['role' => 'assistant'];
                    if ($fullResponse !== '') {
                        $assistantMsg['content'] = $fullResponse;
                    }
                    $assistantMsg['tool_calls'] = $assistantToolCalls;
                    $messages[] = $assistantMsg;
                } else {
                    // Anthropic format: assistant message with content blocks
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $contentBlocks,
                    ];
                }

                $anthropicToolResultBlocks = [];

                foreach ($toolUseBlocks as $toolUseBlock) {
                    $toolCallCount++;
                    $functionName = $toolUseBlock['name'];
                    $functionArgs = $toolUseBlock['input'] ?? [];
                    $lastToolCall = $functionName;

                    // S0.11.2 — abort BEFORE dispatching the next tool so
                    // a disconnected client doesn't trigger an unnecessary
                    // DB write. Anything earlier in this generator pass
                    // stays — see INV-2.9.2.
                    if ($this->wasConnectionAborted()) {
                        $this->recordAbort($conversation, $lastToolCall, $partialWriteCount);

                        return;
                    }

                    yield [
                        'type' => 'tool_use',
                        'tool' => $functionName,
                        'status' => 'running',
                    ];

                    $toolResult = $this->executeTool($functionName, $functionArgs, $user, $conversation->id);

                    if (isset($toolResult['created']) && $toolResult['created'] === true) {
                        $partialWriteCount++;
                    }

                    // Handle navigation results
                    if (isset($toolResult['action']) && $toolResult['action'] === 'navigate') {
                        yield [
                            'type' => 'navigation',
                            'route_path' => $toolResult['route_path'],
                            'description' => $toolResult['description'] ?? '',
                        ];
                    }

                    // S0.5.t — auto-navigation on blocked results removed.
                    // BS-14 caught the regression: "Add a Cash ISA" caused
                    // Advice Fyn to call get_module_analysis(savings), which
                    // the prerequisite gate blocked on missing expenditure
                    // and suggested /profile. The frontend obeyed the
                    // redirect, force-navigating the user to /profile
                    // despite the inline-capture having already persisted
                    // the ISA. The blocked reason still reaches the LLM via
                    // the tool_result so it can surface the gap as text and
                    // recommend (in words) where to add the missing data;
                    // we no longer hijack the user's route.

                    // Handle form fill results
                    if (isset($toolResult['action']) && $toolResult['action'] === 'fill_form') {
                        yield [
                            'type' => 'fill_form',
                            'entity_type' => $toolResult['entity_type'],
                            'route' => $toolResult['route'],
                            'fields' => $toolResult['fields'],
                            'mode' => $toolResult['mode'] ?? 'create',
                            'entity_id' => $toolResult['entity_id'] ?? null,
                        ];
                    }

                    // Onboarding inline-capture handoff — consumed by
                    // OnboardingChatDirector::handleInlineCapture via the
                    // synthetic `handoff` SSE event. Never forwarded to the
                    // frontend; invisible to the user per INV-2.4.1.
                    if (isset($toolResult['action']) && $toolResult['action'] === 'handoff') {
                        yield [
                            'type' => 'handoff',
                            'handoff_type' => $toolResult['handoff_type'] ?? 'unknown',
                            'payload' => $toolResult['payload'] ?? [],
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

                    // Handle grouped onboarding field captures (used by the
                    // Fyn onboarding director's grouped_extract turns). The
                    // handler writes fields to the DB and returns a structured
                    // receipt; we yield an SSE event so the director can see
                    // the capture arrived and advance state.
                    if (isset($toolResult['onboarding_capture']) && $toolResult['onboarding_capture'] === true) {
                        yield [
                            'type' => 'onboarding_field_captured',
                            'field_group' => $toolResult['field_group'] ?? 'unknown',
                            'summary' => $toolResult['summary'] ?? '',
                            'details' => $toolResult['details'] ?? [],
                        ];
                    }

                    // FR-M13 — structured onboarding capture error (e.g. spouse
                    // email already bound to another household). The director
                    // consumes this to render a targeted terminal message and
                    // stops advancing state.
                    if (isset($toolResult['onboarding_capture_error']) && $toolResult['onboarding_capture_error'] === true) {
                        yield [
                            'type' => 'onboarding_capture_error',
                            'field_group' => $toolResult['field_group'] ?? 'unknown',
                            'error_type' => $toolResult['error_type'] ?? 'unknown',
                            'message' => $toolResult['message'] ?? '',
                        ];
                    }

                    $toolCallsSummary[] = [
                        'tool' => $functionName,
                        'input' => $this->summariseToolInput($functionArgs),
                        'result_summary' => $this->summariseToolResult($toolResult),
                    ];

                    $isToolError = isset($toolResult['error']) && $toolResult['error'] === true;

                    // April30Updates F-3 — compress the tool result before
                    // re-injecting into the LLM context. Some tools (notably
                    // get_module_analysis) return ~5-10KB of structured JSON;
                    // un-summarised, every subsequent loop iteration eats
                    // those tokens again. The compressed shape preserves all
                    // the hooks the model actually uses to reason (entity_id,
                    // entity_type, top-level metrics, error state, fingerprints)
                    // while trimming verbose nested payloads.
                    $toolResultForModel = $this->compressToolResultForModel($functionName, $toolResult);
                    $toolResultJson = json_encode($toolResultForModel);

                    if ($isXai) {
                        // OpenAI format: each tool result is a separate message
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolUseBlock['id'],
                            'content' => $toolResultJson,
                        ];
                    } else {
                        // Anthropic format: collect for single user message
                        $block = [
                            'type' => 'tool_result',
                            'tool_use_id' => $toolUseBlock['id'],
                            'content' => $toolResultJson,
                        ];
                        if ($isToolError) {
                            $block['is_error'] = true;
                        }
                        $anthropicToolResultBlocks[] = $block;
                    }

                    yield [
                        'type' => 'tool_use',
                        'tool' => $functionName,
                        'status' => 'complete',
                    ];
                }

                // Anthropic: add all tool results as a single user message
                if (! $isXai && ! empty($anthropicToolResultBlocks)) {
                    $messages[] = [
                        'role' => 'user',
                        'content' => $anthropicToolResultBlocks,
                    ];
                }
            }

            if ($hasToolCalls && $stopReason === 'tool_use' && $toolCallCount < $toolCallCap) {
                continue;
            }

            // If we hit the tool call limit but still have tool_use stop reason,
            // make one final pass with tools disabled to force a text response
            if ($hasToolCalls && $stopReason === 'tool_use' && $toolCallCount >= $toolCallCap && $fullResponse === '') {
                $xaiTools = [];
                $tools = [];

                continue;
            }

            break;
        }

        // Validate and sanitise AI response
        $validator = app(StructuredResponseValidator::class);
        $fullResponse = $validator->sanitise($fullResponse);
        $violations = $validator->validateAndLog($fullResponse, $classification, $user->id);

        // Build metadata with tool call summary and any violations
        $messageMetadata = [];
        if (! empty($toolCallsSummary)) {
            $messageMetadata['tool_calls'] = $toolCallsSummary;
        }
        if (! empty($violations)) {
            $messageMetadata['validation_violations'] = $violations;
        }

        // Save assistant message with system prompt for audit trail
        if ($totalCachedTokens > 0) {
            $messageMetadata['cached_tokens'] = $totalCachedTokens;
            $messageMetadata['cache_hit_rate'] = $totalInputTokens > 0
                ? round(($totalCachedTokens / $totalInputTokens) * 100, 1)
                : 0;
        }

        // April30Updates F-8 — persist a SHA-256 hash of the system prompt
        // rather than the full text. The full prompt embeds user PII
        // (income, family names, financial position) and is ~10KB per
        // assistant message — duplicating it across every row of a long
        // conversation creates needless DB bloat and a redundant copy
        // of data already in the canonical user/records tables. The
        // hash is enough to confirm whether the prompt structure changed
        // between turns when debugging.
        //
        // The column is still named `system_prompt` (renaming requires a
        // migration that's out of audit scope) — we just write a hash to
        // it going forward instead of the full text. Legacy rows retain
        // the full prompt and can be migrated separately. The hash is
        // prefixed with `sha256:` so a future reader can tell hashes
        // from legacy full-prompt rows at a glance.
        $assistantExtra = array_merge([
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
            'model_used' => $model,
            'system_prompt' => 'sha256:'.hash('sha256', $systemPrompt),
        ], ! empty($messageMetadata) ? ['metadata' => $messageMetadata] : []);

        if ($this->personaOverride !== null) {
            $assistantExtra['persona'] = $this->personaOverride;
        }

        // Defence in depth — strip any `<function_call>...</function_call>`
        // markup the LLM may have emitted as plain text instead of using the
        // structured tool_use API. We never want that leaking into a
        // persisted assistant message (it breaks the chat bubble + the
        // BS-20 visible-text contract).
        $sanitisedResponse = XaiFunctionCallLeakStripper::stripLeakedToolCallMarkup($fullResponse);

        $assistantMessage = $this->saveMessage($conversation, 'assistant', $sanitisedResponse, $assistantExtra);

        // Update conversation token usage
        $conversation->incrementTokenUsage($totalInputTokens, $totalOutputTokens);
        $conversation->update(['model_used' => $model]);

        // S0.11.1 — atomically record the daily total to ai_daily_usage
        // so the next hasTokenBudget call sees the latest figure with no
        // 5-minute cache lag.
        $this->recordTokenUsage($user, $totalInputTokens + $totalOutputTokens);

        // Log advice for review system (only for advice query types)
        if ($classification !== null
            && QuerySchemas::isAdviceType($classification['primary'])) {
            try {
                AiAdviceLog::create([
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                    'message_id' => $assistantMessage->id,
                    'query_type' => $classification['primary'],
                    'classification' => $classification,
                    'kyc_status' => $kycResult,
                    'recommendations' => array_map(fn ($r) => [
                        'title' => $r['title'] ?? null,
                        'module' => $r['module'] ?? null,
                        'estimated_saving' => $r['estimated_saving'] ?? null,
                    ], array_slice($toolCallsSummary, 0, 5)),
                    'tools_called' => array_map(fn ($tc) => $tc['tool'] ?? null, $toolCallsSummary),
                    'user_data_snapshot' => [
                        'income' => (float) $user->annual_employment_income + (float) $user->annual_self_employment_income,
                        'expenditure' => (float) ($user->monthly_expenditure ?? 0),
                        'employment_status' => $user->employment_status,
                        'marital_status' => $user->marital_status,
                    ],
                ]);
            } catch (\Exception $e) {
                Log::warning('[HasAiChat] Failed to log advice', ['error' => $e->getMessage()]);
            }
        }

        yield [
            'type' => 'done',
            'message_id' => $assistantMessage->id,
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
        ];
    }

    // ─── Prompt Building ─────────────────────────────────────────────

    /**
     * Build the complete system prompt for the AI assistant.
     * Delegates to AdvicePromptBuilder for 10-layer assembly.
     */
    protected function buildSystemPrompt(
        User $user,
        ?string $currentRoute = null,
        ?array $classification = null,
        ?array $kycResult = null,
        ?AiConversation $conversation = null,
    ): string {
        $builder = app(AdvicePromptBuilder::class);

        return $builder->build(
            user: $user,
            classification: $classification,
            kycResult: $kycResult,
            currentRoute: $currentRoute,
            isPreview: $user->is_preview_user,
            // Sized analysis: only run the modules the classification needs.
            // For HOLISTIC_HEALTH this still runs orchestrateAnalysis end-to-end;
            // for module-scoped advice queries it runs only the relevant
            // {Module}Agent::analyze() calls; for factual queries (INCOME /
            // GENERAL / NAVIGATION / BILLING / OUT_OF_REMIT) it skips module
            // analysis entirely. See CoordinatingAgent::analyzeRelevantModules.
            orchestrateAnalysis: fn (int $userId) => $this->analyzeRelevantModules($userId, $classification),
            conversation: $conversation,
        );
    }

    // ─── Message Persistence & History ────────────────────────────────

    // ─── Message Persistence ─────────────────────────────────────────

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
     * Build message history from conversation.
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
     * Generate a short conversation title from the first message.
     *
     * Strips HTML tags before truncating so script/img/anchor markup in the
     * user's first message cannot leak into the conversation list sidebar
     * or the audit trail (S0.11.6).
     */
    private function generateTitle(string $message): string
    {
        $cleaned = trim(strip_tags($message));
        $title = mb_substr($cleaned, 0, 100);

        if (mb_strlen($cleaned) > 100) {
            $title .= '...';
        }

        return $title;
    }

    /**
     * April30Updates F-3 — compress a tool result before sending it back
     * into the LLM message history.
     *
     * Without compression, the JSON payload of `get_module_analysis`,
     * `get_recommendations`, `list_records`, etc. (5-10 KB each) re-enters
     * the input stream on every subsequent tool-call iteration in the
     * same turn, multiplying input tokens. The model only needs the
     * decision-shaped fields (top-level metrics, recommendations, entity
     * IDs) to reason about the next step.
     *
     * Strategy:
     *  - Errors pass through verbatim (the model needs the full message
     *    to surface it honestly per FcaProcessInstructions WRITE-failure
     *    rule).
     *  - Direct-write results (entity_created shape) trim to the keys
     *    the model uses for chaining: success, entity_type, entity_id,
     *    persisted_fields (metrics only).
     *  - Read tools (get_*, list_*, calculate_*) trim arrays > 10 items
     *    to a head + tail + count summary, drop nested arrays > 3 levels
     *    deep, and truncate strings > 200 chars.
     *  - Anything else passes through unchanged.
     */
    private function compressToolResultForModel(string $toolName, array $result): array
    {
        // Errors must be visible verbatim so the model surfaces them.
        if (isset($result['error']) && $result['error'] === true) {
            return $result;
        }

        // Handoff and confirmation results are tiny — pass through.
        if (isset($result['action']) && in_array($result['action'], ['handoff', 'navigate', 'fill_form'], true)) {
            return $result;
        }

        // Direct-write results — keep the chaining surface, drop verbose
        // persisted_fields that the model cannot use anyway.
        if ((isset($result['created']) && $result['created'] === true)
            || (isset($result['success']) && $result['success'] === true && isset($result['entity_id']))) {
            return [
                'success' => true,
                'entity_type' => $result['entity_type'] ?? null,
                'entity_id' => $result['entity_id'] ?? null,
                'name' => $result['name'] ?? null,
            ];
        }

        // General compression — recursively trim oversized nested data.
        return $this->trimForModel($result, depth: 0);
    }

    /**
     * Recursively trim a value tree for LLM consumption. List arrays
     * over 10 items are summarised; strings over 200 chars are
     * truncated; depth over 3 collapses to a placeholder.
     */
    private function trimForModel(mixed $value, int $depth): mixed
    {
        if ($depth >= 3) {
            if (is_array($value)) {
                return '[nested '.count($value).' items]';
            }

            return $value;
        }

        if (is_string($value)) {
            return mb_strlen($value) > 200 ? mb_substr($value, 0, 200).'…' : $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value) && count($value) > 10) {
            $head = array_slice($value, 0, 5);
            $tail = array_slice($value, -2);

            return [
                ...array_map(fn ($v) => $this->trimForModel($v, $depth + 1), $head),
                '__truncated__' => count($value) - 7 .' items omitted',
                ...array_map(fn ($v) => $this->trimForModel($v, $depth + 1), $tail),
            ];
        }

        return array_map(fn ($v) => $this->trimForModel($v, $depth + 1), $value);
    }

    /**
     * Summarise tool input.
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
     * Summarise a tool result.
     *
     * Always preserves `entity_id` and `entity_type` when present, even if
     * other keys would push them past the 5-key cap (S0.11.6 / INV-2.5.3).
     * The audit chain and downstream observers correlate direct-write tool
     * calls back to the persisted row via these two keys.
     */
    private function summariseToolResult(array $result): string
    {
        if (empty($result)) {
            return 'empty result';
        }

        $parts = [];
        $rendered = [];

        $renderPart = static function (string $key, mixed $value) use (&$parts, &$rendered): void {
            if (isset($rendered[$key])) {
                return;
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
            } else {
                return;
            }

            $rendered[$key] = true;
        };

        foreach (['entity_id', 'entity_type'] as $priorityKey) {
            if (array_key_exists($priorityKey, $result)) {
                $renderPart($priorityKey, $result[$priorityKey]);
            }
        }

        $count = 0;
        foreach ($result as $key => $value) {
            if ($key === 'entity_id' || $key === 'entity_type') {
                continue;
            }

            if ($count >= 5) {
                break;
            }

            $renderPart((string) $key, $value);
            $count++;
        }

        return implode(', ', $parts) ?: 'processed';
    }

    /**
     * Configure per-call overrides for the next chat() invocation. Used by
     * OnboardingChatDirector to swap the system prompt and tool list for
     * asset_capture and grouped_extract delegations without touching the
     * main Fyn flow. The caller MUST pair this with clearChatOverrides()
     * in a finally block.
     *
     * @param  list<string>|null  $allowedTools
     * @param  list<array<string, mixed>>|null  $toolsListOverride
     */
    public function setChatOverrides(
        ?string $systemPrompt,
        ?array $allowedTools,
        bool $skipUserMessagePersistence = false,
        ?array $toolsListOverride = null,
        ?string $personaOverride = null,
    ): void {
        $this->systemPromptOverride = $systemPrompt;
        $this->allowedToolsOverride = $allowedTools;
        $this->skipUserMessagePersistence = $skipUserMessagePersistence;
        $this->toolsListOverride = $toolsListOverride;
        $this->personaOverride = $personaOverride;
    }

    public function clearChatOverrides(): void
    {
        $this->systemPromptOverride = null;
        $this->allowedToolsOverride = null;
        $this->skipUserMessagePersistence = false;
        $this->toolsListOverride = null;
        $this->personaOverride = null;
    }

    /**
     * Detect whether the SSE client has dropped the connection mid-stream.
     *
     * Wraps PHP's `connection_aborted()` so tests can stub abort behaviour.
     * Note: PHP only updates the abort flag after a write attempt OR when
     * `ignore_user_abort()` is false (the Apache/php-fpm default), so the
     * caller should be inside or just-past a `yield` for this to be
     * reliable.
     */
    protected function wasConnectionAborted(): bool
    {
        return connection_aborted() === 1;
    }

    /**
     * April30Updates F-13 — classify an LLM exception as transient.
     *
     * Retriable: 429 (rate limit), 529 (overloaded), network timeouts,
     * "service unavailable", connection-refused, "overloaded".
     * Not retriable: 401/403 (auth), 400 (bad request), context-length,
     * malformed-tool-schema, anything where retry would fail identically.
     *
     * Conservative on purpose — false positives (treating non-transient
     * as transient) just mean a small extra latency before the same
     * error surfaces. False negatives (treating transient as fatal) mean
     * the user sees an error they didn't need to.
     */
    protected function isRetriableLlmError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        // Auth / configuration / API-key errors — never retry, will fail again.
        if (str_contains($msg, 'api_key')
            || str_contains($msg, 'authentication')
            || str_contains($msg, 'invalid_api_key')
            || str_contains($msg, '401')
            || str_contains($msg, '403')) {
            return false;
        }

        // Bad request, unsupported model, malformed schema — not transient.
        if (str_contains($msg, 'invalid_request')
            || str_contains($msg, 'context_length')
            || str_contains($msg, 'max_tokens')
            || str_contains($msg, 'max_completion_tokens')
            || str_contains($msg, 'tool_use_id')) {
            return false;
        }

        // Transient — retry.
        return str_contains($msg, '429')
            || str_contains($msg, '529')
            || str_contains($msg, 'rate_limit')
            || str_contains($msg, 'overloaded')
            || str_contains($msg, 'capacity')
            || str_contains($msg, 'timeout')
            || str_contains($msg, 'timed out')
            || str_contains($msg, 'connection')
            || str_contains($msg, 'service_unavailable')
            || str_contains($msg, 'temporarily');
    }

    /**
     * Persist a forensic abort row. Partial writes from earlier tool
     * calls in the same generator pass are deliberately NOT rolled back
     * (INV-2.9.2) — every direct-write tool runs in its own transaction
     * and what already landed is real.
     */
    protected function recordAbort(
        AiConversation $conversation,
        ?string $lastToolCall,
        int $partialWriteCount
    ): void {
        try {
            AiAbortEvent::create([
                'conversation_id' => $conversation->id,
                'user_id' => $conversation->user_id,
                'last_tool_call' => $lastToolCall,
                'partial_write_count' => $partialWriteCount,
            ]);
        } catch (\Throwable $e) {
            // Best-effort write — never let an audit failure mask the
            // upstream abort itself.
            Log::warning('[HasAiChat] Failed to persist ai_abort_events row', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
