<?php

declare(strict_types=1);

namespace App\Services\AI\ContextualConversation;

use App\Enums\AiMessageStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Mobile\NextActionsService;
use Illuminate\Support\Facades\DB;

final class ContextualConversationService
{
    public function __construct(
        private readonly ContextualResourceResolver $resources,
        private readonly NextActionsService $nextActions,
    ) {}

    /**
     * @param  array{
     *     action: string,
     *     resource_type: string,
     *     resource_id?: int|null,
     *     current_destination: array{screen: string, params: array<string, int|string>, fallback: string},
     *     origin: array{kind: string, recommendation_id: int|null}
     * }  $validated
     * @return array{conversation: AiConversation, opening_message: AiMessage}
     */
    public function create(User $user, array $validated): array
    {
        $resource = $this->resources->resolve(
            $user,
            $validated['resource_type'],
            $validated['resource_id'] ?? null,
        );

        // A recommendation-driven capture (CSJ 2026-09-09): the dashboard row
        // the user tapped names what Fyn is collecting, and its id is what
        // gets ticked off when the user says they are done (AdviceFyn's
        // follow-up). Resolved server-side from the same ranked list the
        // dashboard showed, never from client-authored copy.
        $recommendation = $this->recommendationFor($user, $validated['origin']);
        $origin = $validated['origin'];
        if ($recommendation !== null) {
            $origin['recommendation'] = $recommendation;
        }

        return DB::transaction(function () use ($user, $validated, $resource, $origin, $recommendation): array {
            $timestamp = now();
            $conversation = AiConversation::create([
                'user_id' => $user->id,
                'title' => ucfirst($validated['action']).' '.$resource->label,
                'status' => 'active',
                'model_used' => '',
                'message_count' => 1,
                'last_message_at' => $timestamp,
                'metadata' => [
                    'source' => 'surface_action',
                    'mode' => 'surface_action',
                    'action' => $validated['action'],
                    'resource_type' => $resource->resourceType,
                    'resource_id' => $resource->resourceId,
                    'current_destination' => $validated['current_destination'],
                    'origin' => $origin,
                    'context_provenance' => [
                        'authority' => 'server',
                        'rehydrated_at' => $timestamp->toIso8601String(),
                        'resource_type' => $resource->resourceType,
                        'resource_id' => $resource->resourceId,
                    ],
                ],
            ]);

            $opening = $conversation->messages()->create([
                'role' => 'assistant',
                'status' => AiMessageStatus::Answered,
                'content' => $recommendation !== null
                    ? $this->recommendationOpening($recommendation)
                    : $this->openingFor($validated['action'], $resource),
                'metadata' => [
                    'source' => 'server_contextual_opening',
                ],
            ]);

            return [
                'conversation' => $conversation,
                'opening_message' => $opening,
            ];
        });
    }

    /**
     * The open dashboard recommendation behind a recommendation origin, from
     * the one ranked list (NextActionsService::buildAll). Null when the origin
     * is a surface action, or the recommendation is no longer open — the
     * conversation then opens as a plain add/edit rather than failing.
     *
     * @param  array{kind: string, recommendation_id: string|null}  $origin
     * @return array{id: string, module: string, title: string, detail: string|null}|null
     */
    private function recommendationFor(User $user, array $origin): ?array
    {
        if (($origin['kind'] ?? null) !== 'recommendation' || ! is_string($origin['recommendation_id'] ?? null)) {
            return null;
        }

        foreach ($this->nextActions->buildAll($user->id) as $item) {
            if (($item['type'] ?? null) === 'recommendation' && ($item['id'] ?? null) === $origin['recommendation_id']) {
                return [
                    'id' => (string) $item['id'],
                    'module' => (string) ($item['module'] ?? 'general'),
                    'title' => (string) $item['title'],
                    'detail' => isset($item['detail']) ? (string) $item['detail'] : null,
                ];
            }
        }

        return null;
    }

    /** @param array{title: string, detail: string|null} $recommendation */
    private function recommendationOpening(array $recommendation): string
    {
        $detail = trim((string) ($recommendation['detail'] ?? ''));
        $detail = $detail !== '' ? rtrim($detail, '.').'. ' : '';

        return "I can help you enter the information for {$recommendation['title']}. {$detail}"
            ."Tell me the details you know, and I'll validate them before anything is saved.";
    }

    private function openingFor(string $action, ContextualResource $resource): string
    {
        if ($action === 'add') {
            return "Let's add to your {$resource->label}. Tell me the details you know, and I'll validate them before anything is saved.";
        }

        return "Let's update your {$resource->label}. Tell me what has changed, and I'll validate it before anything is saved.";
    }
}
