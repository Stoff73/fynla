<?php

declare(strict_types=1);

namespace App\Services\AI\ContextualConversation;

use App\Enums\AiMessageStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ContextualConversationService
{
    public function __construct(
        private readonly ContextualResourceResolver $resources,
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

        return DB::transaction(function () use ($user, $validated, $resource): array {
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
                    'origin' => $validated['origin'],
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
                'content' => $this->openingFor($validated['action'], $resource),
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

    private function openingFor(string $action, ContextualResource $resource): string
    {
        if ($action === 'add') {
            return "Let's add to your {$resource->label}. Tell me the details you know, and I'll validate them before anything is saved.";
        }

        return "Let's update your {$resource->label}. Tell me what has changed, and I'll validate it before anything is saved.";
    }
}
