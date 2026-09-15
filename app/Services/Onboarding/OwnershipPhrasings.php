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
    public const INDIVIDUAL = '(?:owned\s+)?individual(?:ly)?|individual\s+ownership|sole(?:ly)?(?!\s+trader)|mine\s+alone|(?:it[\x{2019}\x{0027}]?s\s+|that[\x{2019}\x{0027}]?s\s+|all\s+)?mine(?:\s+only)?|(?:just|only)\s+(?:me|mine|by\s+me)|me\s+only|myself|owned\s+by\s+me|(?:on\s+)?my\s+own|(?:in\s+)?my\s+name(?:\s+only)?|(?:it[\x{2019}\x{0027}]?s\s+|that[\x{2019}\x{0027}]?s\s+|all\s+|only\s+)?mine(?:\s+only|\s+alone)?|(?:just|only)\s+(?:for\s+)?me(?:\s+only)?|me\s+(?:only|alone|myself)|(?:by\s+)?myself|(?:in\s+|under\s+)?(?:my|one|a\s+single|the\s+one)\s+name(?:\s+only|\s+alone)?|single\s+name|sole\s+(?:name|owner(?:ship)?|account)|(?:my\s+)?personal(?:\s+account)?|(?:on\s+)?my\s+own|i\s+own\s+(?:it|this|that)|i[\x{2019}\x{0027}]?m\s+the\s+(?:only|sole)\s+(?:owner|name)|not\s+(?:joint(?:ly)?|shared|with\s+anyone)|no\s*(?:one|body)\s+else|nobody\s+else|(?:just|only)\s+(?:in\s+)?my\s+name';

    /** Alternation fragment for explicit JOINT ownership phrases. */
    public const JOINT = 'joint(?:ly)?(?:\s+owned)?|we\s+own|owned\s+with|with\s+my\s+(?:spouse|partner|wife|husband)|in\s+both\s+our\s+names|both\s+of\s+us|50\s*\/\s*50|shared|we\s+share|co-?own(?:ed|er|ers)?|(?:it[\x{2019}\x{0027}]?s\s+|that[\x{2019}\x{0027}]?s\s+|all\s+)?ours|our\s+(?:joint\s+)?(?:account|savings|isa|names?)|(?:in\s+)?both\s+(?:of\s+)?our\s+names|us\s+both|the\s+(?:two|both)\s+of\s+us|(?:me|i)\s+and\s+my\s+(?:wife|husband|partner|spouse|other\s+half)|my\s+(?:wife|husband|partner|spouse|other\s+half)\s+and\s+(?:me|i)|with\s+my\s+(?:other\s+half|civil\s+partner)|with\s+(?:the\s+)?(?:wife|husband|missus|hubby)|half\s+(?:each|and\s+half)|split\s+(?:equally|down\s+the\s+middle|50)|(?:owned\s+)?together|between\s+(?:us|the\s+two\s+of\s+us)';

    /** An equal split stated in words — the share is 50 without a number. */
    public const EQUAL_SPLIT = '50\s*\/\s*50|fifty[\s\/-]*fifty|half\s+(?:each|and\s+half)|split\s+(?:equally|evenly|down\s+the\s+middle)|equal(?:ly)?\s+split|equal\s+shares?|in\s+equal\s+shares';

    /** The user's own percentage share: "my share is 60%", "I own 60%", "60% is mine". */
    public const MY_SHARE_PERCENT = '(?:(?:i\s+own|my\s+share\s+(?:(?:is|at|of)\s+)?)(\d{1,3}(?:\.\d+)?)\s*%|(\d{1,3}(?:\.\d+)?)\s*%\s+(?:is\s+)?(?:owned\s+)?(?:by\s+)?(?:me|mine)\b)';
}
