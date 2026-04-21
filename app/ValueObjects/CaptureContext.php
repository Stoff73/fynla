<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable context carried from advice Fyn (or the frontend's inline-capture
 * trigger) into a data-capture turn. Tells data-capture Fyn WHY it was invoked
 * and WHAT entity types to ask about.
 *
 * Serialises via toArray() into the `capture_context` slot of
 * `ai_conversations.persona_state` and hydrates back via fromArray() on the
 * next turn.
 *
 * @phpstan-type CaptureContextArray array{
 *     reason: string,
 *     entity_types: list<string>,
 *     fields_needed?: list<string>,
 *     pending_advice_question?: string|null,
 *     originating_focus?: string|null
 * }
 */
final class CaptureContext
{
    /**
     * @param  list<string>  $entityTypes  Record types to capture (e.g. ['dc_pension', 'db_pension']).
     *                                     Drawn from the data_capture persona's allowed_tools list in
     *                                     config/fyn_personas.php.
     * @param  list<string>  $fieldsNeeded  Optional. Specific fields the capture turn should prompt for.
     * @param  string|null  $pendingAdviceQuestion  Set when the advice persona delegated to capture
     *                                              because it needed data to answer a question.
     *                                              The orchestrator re-invokes advice with this
     *                                              question once capture_complete fires.
     * @param  string|null  $originatingFocus  Onboarding journey focus that triggered the capture, if any.
     */
    public function __construct(
        public readonly string $reason,
        public readonly array $entityTypes,
        public readonly array $fieldsNeeded = [],
        public readonly ?string $pendingAdviceQuestion = null,
        public readonly ?string $originatingFocus = null,
    ) {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('CaptureContext reason must not be empty.');
        }

        if ($entityTypes === []) {
            throw new InvalidArgumentException('CaptureContext entityTypes must not be empty.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        if (! array_key_exists('reason', $payload)) {
            throw new InvalidArgumentException('CaptureContext payload missing required key: reason.');
        }

        if (! array_key_exists('entity_types', $payload)) {
            throw new InvalidArgumentException('CaptureContext payload missing required key: entity_types.');
        }

        return new self(
            reason: (string) $payload['reason'],
            entityTypes: array_values((array) $payload['entity_types']),
            fieldsNeeded: array_values((array) ($payload['fields_needed'] ?? [])),
            pendingAdviceQuestion: isset($payload['pending_advice_question'])
                ? (string) $payload['pending_advice_question']
                : null,
            originatingFocus: isset($payload['originating_focus'])
                ? (string) $payload['originating_focus']
                : null,
        );
    }

    /**
     * @return CaptureContextArray
     */
    public function toArray(): array
    {
        return [
            'reason' => $this->reason,
            'entity_types' => $this->entityTypes,
            'fields_needed' => $this->fieldsNeeded,
            'pending_advice_question' => $this->pendingAdviceQuestion,
            'originating_focus' => $this->originatingFocus,
        ];
    }
}
