<?php

declare(strict_types=1);

namespace App\Services\AI\ContextualConversation;

use App\Enums\AiMessageStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Mobile\NextActionsService;
use App\Services\Onboarding\RecordEditForms;
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

        // A resource that is one record opens on that record's form (web and
        // /m draw it from the message metadata, as they do an edit form).
        $form = app(RecordEditForms::class)->formForResource(
            $user,
            $resource->resourceType,
            (array) ($validated['current_destination']['params'] ?? []),
        );

        return DB::transaction(function () use ($user, $validated, $resource, $origin, $recommendation, $form): array {
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
                'content' => match (true) {
                    $form !== null => $this->formOpening($form),
                    $recommendation !== null => $this->recommendationOpening($recommendation),
                    default => $this->openingFor($validated['action'], $resource),
                },
                'metadata' => array_filter([
                    'source' => 'server_contextual_opening',
                    'capture_form' => $form['schema'] ?? null,
                    'capture_form_values' => $form['answers'] ?? null,
                    'capture_form_record' => $form['record'] ?? null,
                ], static fn ($v) => $v !== null),
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

    /**
     * The line above a record's form. It also reads on its own, for a client
     * that does not draw forms.
     *
     * @param  array{label: string}  $form
     */
    private function formOpening(array $form): string
    {
        // The label keeps its own capitals ("State Pension" is a name), and the
        // verb agrees with it: "employer benefits" are, "State Pension" is.
        $label = (string) preg_replace('/^your\s+/i', '', (string) $form['label']);
        $verb = preg_match('/[^s]s$/', $label) === 1 ? 'are' : 'is';

        return "Here {$verb} your {$label}. Change what needs changing and save, or tell me what has changed.";
    }

    private function openingFor(string $action, ContextualResource $resource): string
    {
        if ($action === 'add') {
            return "Let's add to your {$resource->label}. Tell me the details you know, and I'll validate them before anything is saved.";
        }

        return "Let's update your {$resource->label}. Tell me what has changed, and I'll validate it before anything is saved.";
    }
}
