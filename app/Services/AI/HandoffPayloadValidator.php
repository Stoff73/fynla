<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Validates the payload shape of the two internal handoff tools emitted
 * by the LLM during onboarding inline-capture turns (delegate_to_capture,
 * capture_complete). Returns an error-key string on malformed payloads,
 * null on valid input. Pure, stateless, no side effects.
 */
final class HandoffPayloadValidator
{
    /** @param  array<string, mixed>  $payload */
    public static function validateDelegateToCapture(array $payload): ?string
    {
        if (! isset($payload['reason']) || ! is_string($payload['reason'])) {
            return 'missing_or_invalid_reason';
        }
        if (! isset($payload['entity_types']) || ! is_array($payload['entity_types'])) {
            return 'missing_or_invalid_entity_types';
        }
        foreach ($payload['entity_types'] as $type) {
            if (! is_string($type)) {
                return 'entity_types_must_be_strings';
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $payload */
    public static function validateCaptureComplete(array $payload): ?string
    {
        if (! isset($payload['summary']) || ! is_string($payload['summary'])) {
            return 'missing_or_invalid_summary';
        }
        if (! isset($payload['records_created']) || ! is_array($payload['records_created'])) {
            return 'missing_or_invalid_records_created';
        }

        return null;
    }
}
