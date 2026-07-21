<?php

declare(strict_types=1);

use App\Services\AI\HandoffPayloadValidator;

describe('HandoffPayloadValidator::validateDelegateToCapture', function (): void {
    it('accepts a valid payload', function (): void {
        $error = HandoffPayloadValidator::validateDelegateToCapture([
            'reason' => 'user mentioned a new SIPP',
            'entity_types' => ['dc_pension'],
        ]);

        expect($error)->toBeNull();
    });

    it('rejects missing reason', function (): void {
        expect(HandoffPayloadValidator::validateDelegateToCapture([
            'entity_types' => ['dc_pension'],
        ]))->toBe('missing_or_invalid_reason');
    });

    it('rejects non-string reason', function (): void {
        expect(HandoffPayloadValidator::validateDelegateToCapture([
            'reason' => 42,
            'entity_types' => ['dc_pension'],
        ]))->toBe('missing_or_invalid_reason');
    });

    it('rejects missing entity_types', function (): void {
        expect(HandoffPayloadValidator::validateDelegateToCapture([
            'reason' => 'ok',
        ]))->toBe('missing_or_invalid_entity_types');
    });

    it('rejects non-array entity_types', function (): void {
        expect(HandoffPayloadValidator::validateDelegateToCapture([
            'reason' => 'ok',
            'entity_types' => 'dc_pension',
        ]))->toBe('missing_or_invalid_entity_types');
    });

    it('rejects entity_types containing non-strings', function (): void {
        expect(HandoffPayloadValidator::validateDelegateToCapture([
            'reason' => 'ok',
            'entity_types' => ['dc_pension', 99],
        ]))->toBe('entity_types_must_be_strings');
    });
});

describe('HandoffPayloadValidator::validateCaptureComplete', function (): void {
    it('accepts a valid payload', function (): void {
        $error = HandoffPayloadValidator::validateCaptureComplete([
            'summary' => 'Added Scottish Widows SIPP £50k',
            'records_created' => [['type' => 'dc_pension', 'id' => 42]],
        ]);

        expect($error)->toBeNull();
    });

    it('rejects missing summary', function (): void {
        expect(HandoffPayloadValidator::validateCaptureComplete([
            'records_created' => [],
        ]))->toBe('missing_or_invalid_summary');
    });

    it('rejects non-string summary', function (): void {
        expect(HandoffPayloadValidator::validateCaptureComplete([
            'summary' => ['not', 'a', 'string'],
            'records_created' => [],
        ]))->toBe('missing_or_invalid_summary');
    });

    it('rejects missing records_created', function (): void {
        expect(HandoffPayloadValidator::validateCaptureComplete([
            'summary' => 'ok',
        ]))->toBe('missing_or_invalid_records_created');
    });

    it('rejects non-array records_created', function (): void {
        expect(HandoffPayloadValidator::validateCaptureComplete([
            'summary' => 'ok',
            'records_created' => 'not-an-array',
        ]))->toBe('missing_or_invalid_records_created');
    });
});
