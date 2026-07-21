<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

/**
 * The four context buckets that replace the legacy 12-layer assembly.
 *
 * IDENTITY  — always: profile narrative + current-page context.
 * POSITION  — financial snapshot + existing records + ranked recommendations.
 * READINESS — data completeness + KYC gate + review-due.
 * CAPTURE   — onboarding focus header + capture-turn instruction block.
 */
enum ContextBucket
{
    case IDENTITY;
    case POSITION;
    case READINESS;
    case CAPTURE;
}
