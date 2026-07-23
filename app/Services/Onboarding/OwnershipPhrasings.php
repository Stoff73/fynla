<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

/**
 * THE single source of ownership phrasing vocabulary (CLAUDE.md Rule 20 —
 * every Fyn vocabulary lives in ONE place, all consumers compose from it).
 *
 * Live 2026-07-23: "owned just by me" and "both just mine" fell through TWO
 * divergent per-file regex lists (CaptureAccuracyGate vs
 * AssetCaptureEntityExtractor) and the gate re-asked for ownership the user
 * had explicitly stated. Consumers embed these fragments inside their own
 * anchors/wrappers; fragments are non-capturing and assume the `/.../u`
 * delimiter (the `50\/50` escape).
 */
final class OwnershipPhrasings
{
    /** Alternation fragment for explicit INDIVIDUAL ownership phrases. */
    public const INDIVIDUAL = '(?:owned\s+)?individually|individual\s+ownership|solely|mine\s+alone|(?:just|only)\s+(?:me|mine|by\s+me)|owned\s+by\s+me|(?:on\s+)?my\s+own|in\s+my\s+name';

    /** Alternation fragment for explicit JOINT ownership phrases. */
    public const JOINT = 'joint(?:ly)?(?:\s+owned)?|we\s+own|owned\s+with|with\s+my\s+(?:spouse|partner|wife|husband)|in\s+both\s+our\s+names|both\s+of\s+us|50\s*\/\s*50';
}
