<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\NetWorth\NetWorthService;
use Illuminate\Support\Facades\Log;

class AiSimulatedService
{
    public function __construct(
        private readonly AiIntentMatcher $intentMatcher,
        private readonly AiSimulatedResponseBuilder $responseBuilder,
        private readonly AiToolExecutor $toolExecutor,
        private readonly NetWorthService $netWorthService,
    ) {}

    /**
     * Send a simulated message and yield SSE chunks.
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
        $this->saveMessage($conversation, 'user', $message);

        // Auto-generate title from first message
        if ($conversation->message_count === 0) {
            $title = $this->generateTitle($message);
            $conversation->update(['title' => $title]);
            yield ['type' => 'title', 'title' => $title];
        }

        // Match intent
        $match = $this->intentMatcher->match($message);
        $intent = $match['intent'];
        $params = $match['params'];

        // Determine if we need to fetch agent data
        $agentData = [];
        $needsData = in_array($intent, ['module_analysis', 'recommendations', 'financial_plan', 'net_worth', 'what_if']);

        if ($needsData) {
            // Yield tool running event
            $toolName = $this->getToolNameForIntent($intent, $params);
            yield [
                'type' => 'tool_use',
                'tool' => $toolName,
                'status' => 'running',
            ];

            // Fetch real data from agents
            $agentData = $this->fetchAgentData($intent, $params, $user);

            // Yield tool complete event
            yield [
                'type' => 'tool_use',
                'tool' => $toolName,
                'status' => 'complete',
            ];
        }

        // Build response
        $response = $this->responseBuilder->build($intent, $params, $agentData, $user);
        $text = $response['text'];
        $navigation = $response['navigation'];

        // Stream text in chunks with simulated delays
        $chunks = $this->responseBuilder->chunkForStreaming($text);
        foreach ($chunks as $chunk) {
            yield ['type' => 'content', 'text' => $chunk];

            // Random delay between 15-40ms for natural streaming feel
            usleep(random_int(15000, 40000));
        }

        // Yield navigation event if applicable
        if ($navigation) {
            yield [
                'type' => 'navigation',
                'route_path' => $navigation['route_path'],
                'description' => $navigation['description'] ?? '',
            ];
        }

        // Save assistant message
        $assistantMessage = $this->saveMessage($conversation, 'assistant', $text, [
            'model_used' => 'simulated',
            'input_tokens' => 0,
            'output_tokens' => 0,
        ]);

        // Update conversation (zero tokens for simulated)
        $conversation->incrementTokenUsage(0, 0);
        $conversation->update(['model_used' => 'simulated']);

        yield [
            'type' => 'done',
            'message_id' => $assistantMessage->id,
            'input_tokens' => 0,
            'output_tokens' => 0,
        ];
    }

    /**
     * Fetch real data from the agent system based on intent.
     */
    private function fetchAgentData(string $intent, array $params, User $user): array
    {
        try {
            return match ($intent) {
                'module_analysis' => $this->toolExecutor->execute(
                    'get_module_analysis',
                    ['module' => $params['module'] ?? 'protection'],
                    $user
                ),
                'recommendations' => $this->toolExecutor->execute(
                    'get_recommendations',
                    [],
                    $user
                ),
                'financial_plan' => $this->toolExecutor->execute(
                    'generate_financial_plan',
                    [],
                    $user
                ),
                'net_worth' => $this->fetchNetWorthData($user),
                'what_if' => $this->toolExecutor->execute(
                    'run_what_if_scenario',
                    [
                        'module' => $params['module'] ?? 'savings',
                        'parameters' => [],
                    ],
                    $user
                ),
                default => [],
            };
        } catch (\Exception $e) {
            Log::warning('[AiSimulatedService] Agent data fetch failed', [
                'intent' => $intent,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Unable to fetch data'];
        }
    }

    /**
     * Fetch net worth data combining NetWorthService with holistic analysis.
     */
    private function fetchNetWorthData(User $user): array
    {
        $netWorth = $this->netWorthService->calculateNetWorth($user);
        $holistic = $this->toolExecutor->execute('get_module_analysis', ['module' => 'holistic'], $user);

        return array_merge($netWorth, [
            'metrics' => array_merge(
                ['net_worth' => $netWorth['net_worth'] ?? 0],
                ['total_assets' => $netWorth['total_assets'] ?? 0],
                ['total_liabilities' => $netWorth['total_liabilities'] ?? 0],
                $holistic['metrics'] ?? [],
            ),
            'recommendations' => $holistic['recommendations'] ?? [],
            'available_surplus' => $holistic['metrics']['cashflow_surplus'] ?? 0,
        ]);
    }

    /**
     * Get a descriptive tool name for the tool_use SSE events.
     */
    private function getToolNameForIntent(string $intent, array $params): string
    {
        return match ($intent) {
            'module_analysis' => 'get_module_analysis',
            'recommendations' => 'get_recommendations',
            'financial_plan' => 'generate_financial_plan',
            'net_worth' => 'get_module_analysis',
            'what_if' => 'run_what_if_scenario',
            default => 'analyze',
        };
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
