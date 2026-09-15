<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

/**
 * The ONE vocabulary for how people name a property in chat (Rule 20).
 * The entity extractor types a chunk with it, and the accuracy gate uses it
 * to cut a multi-property message into one evidence segment per property
 * and to find the segment a create_property call belongs to. Regex
 * fragments without delimiters; every consumer wraps them in \b...\b.
 */
final class PropertyPhrasings
{
    public const BUY_TO_LET = 'buy[\s-]?to[\s-]?lets?|btls?|rental\s+(?:property|properties|flat|house|home|unit)|investment\s+propert(?:y|ies)|let\s+propert(?:y|ies)';

    public const SECOND_HOME = 'second\s+homes?|secondary\s+residences?|holiday\s+homes?|holiday\s+lets?';

    public const MAIN_RESIDENCE = 'main\s+residences?|primary\s+residences?|principal\s+residences?|propert(?:y|ies)|houses?|flats?|apartments?|bungalows?|maisonettes?|homes?|cottages?|terrace|semi|detached|my\s+place';

    /** Every noun that opens a property description. */
    public const NOUNS = self::BUY_TO_LET.'|'.self::SECOND_HOME.'|'.self::MAIN_RESIDENCE;

    /**
     * A new property declaration inside a longer sentence — what follows
     * "and" or a comma when the user moves on to their next property.
     */
    public const DECLARATION = '(?:an?|my|our|the|another|one|a\s+second|a\s+further)\s+(?:[a-z]+\s+){0,2}(?:'.self::NOUNS.')';

    /** @return 'buy_to_let'|'secondary_residence'|'main_residence'|null */
    public static function typeOf(string $lowerText): ?string
    {
        return match (true) {
            preg_match('/\b(?:'.self::BUY_TO_LET.')\b/u', $lowerText) === 1 => 'buy_to_let',
            preg_match('/\b(?:'.self::SECOND_HOME.')\b/u', $lowerText) === 1 => 'secondary_residence',
            preg_match('/\b(?:'.self::MAIN_RESIDENCE.')\b/u', $lowerText) === 1 => 'main_residence',
            default => null,
        };
    }
}
