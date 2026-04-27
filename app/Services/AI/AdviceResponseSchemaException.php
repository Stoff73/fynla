<?php

declare(strict_types=1);

namespace App\Services\AI;

use RuntimeException;

/**
 * S1.6 — thrown when an `advice_response` SSE payload fails the strict
 * `AdviceResponseSchema::validate` contract. The composer is the only
 * code that emits this event, so a thrown instance is a code bug, not
 * a user-facing error — log-and-skip the event so the rest of the
 * stream is still delivered.
 */
final class AdviceResponseSchemaException extends RuntimeException {}
