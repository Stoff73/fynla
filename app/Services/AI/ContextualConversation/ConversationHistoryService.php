<?php

declare(strict_types=1);

namespace App\Services\AI\ContextualConversation;

use App\Constants\GateRoutes;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ConversationHistoryService
{
    public function __construct(
        private readonly ContextualResourceResolver $resources,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forUser(User $user): Collection
    {
        return AiConversation::forUser($user->id)
            ->whereIn('status', ['active', 'paused'])
            ->with(['latestVisibleMessage' => fn ($query) => $query->select([
                'ai_messages.id',
                'ai_messages.conversation_id',
                'ai_messages.content',
            ])])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get([
                'id',
                'user_id',
                'title',
                'status',
                'message_count',
                'last_message_at',
                'metadata',
                'created_at',
                'updated_at',
            ])
            ->map(fn (AiConversation $conversation): array => $this->project($conversation, $user))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function project(AiConversation $conversation, User $user): array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $mode = match ($metadata['source'] ?? null) {
            'fyn_onboarding' => 'onboarding',
            'surface_action' => 'contextual',
            default => 'general',
        };
        $purpose = $this->purpose($mode, $metadata);
        [$relatedEntity, $fallback] = $mode === 'contextual'
            ? $this->contextualRelationship($metadata, $user)
            : [null, GateRoutes::destination(GateRoutes::DASHBOARD)];

        $lastMessage = $conversation->latestVisibleMessage?->content;
        $lastMessageSummary = is_string($lastMessage) && trim($lastMessage) !== ''
            ? Str::limit(Str::squish(strip_tags($lastMessage)), 160, '…')
            : null;

        return [
            'id' => $conversation->id,
            'title' => $conversation->title ?: $purpose,
            'message_count' => $conversation->message_count,
            'mode' => $mode,
            'purpose' => $purpose,
            'related_entity' => $relatedEntity,
            'status' => $conversation->status,
            'created_at' => $conversation->created_at?->toIso8601String(),
            'updated_at' => $conversation->updated_at?->toIso8601String(),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'last_message_summary' => $lastMessageSummary,
            'fallback_destination' => $fallback,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function purpose(string $mode, array $metadata): string
    {
        if ($mode === 'onboarding') {
            return 'Complete your Fynla setup';
        }

        if ($mode !== 'contextual') {
            return 'General Fyn conversation';
        }

        $action = ($metadata['action'] ?? null) === 'add' ? 'Add' : 'Edit';
        $resourceType = is_string($metadata['resource_type'] ?? null)
            ? $metadata['resource_type']
            : '';

        return $action.' '.$this->resources->displayNameFor($resourceType);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{0: array<string, mixed>, 1: array{screen: string, params: array<string, int|string>, fallback: string}}
     */
    private function contextualRelationship(array $metadata, User $user): array
    {
        $resourceType = is_string($metadata['resource_type'] ?? null)
            ? $metadata['resource_type']
            : '';
        $rawResourceId = $metadata['resource_id'] ?? null;
        $resourceId = is_int($rawResourceId)
            ? $rawResourceId
            : (is_string($rawResourceId) && ctype_digit($rawResourceId) ? (int) $rawResourceId : null);
        $overview = $this->resources->overviewScreenFor($resourceType);

        try {
            $resource = $this->resources->resolve($user, $resourceType, $resourceId);

            return [[
                'type' => $resource->resourceType,
                'id' => $resource->resourceId,
                'label' => $resource->label,
                'available' => true,
                'explanation' => null,
            ], GateRoutes::destination($resource->overviewScreen)];
        } catch (ModelNotFoundException) {
            return [[
                'type' => $resourceType,
                'id' => $resourceId,
                'label' => null,
                'available' => false,
                'explanation' => 'This related item is no longer available.',
            ], GateRoutes::destination($overview)];
        }
    }
}
