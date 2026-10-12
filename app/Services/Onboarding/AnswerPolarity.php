<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

/**
 * Whether a typed answer says yes or no, in the user's own words. One
 * vocabulary for the onboarding: the bubble matcher (a "yes"/"no" bubble step
 * reads "sure, go ahead" and "nope, that's it", walk fix plan T16) and the
 * director's completion declarations both read it.
 */
final class AnswerPolarity
{
    /**
     * "No" / "none" / "that's everything" at the start, or the everyday
     * "I don't have any …" / "I have no …" / "not got any …" / "nothing else"
     * (csjones user 403, 2026-09-16: "I don't have any other investments"
     * at the investment form fell to "Sorry, I didn't catch that").
     * …and the bare-noun form, "I don't have investments" (Laura,
     * 2026-09-18, conversation 897: acknowledged, then re-asked). The noun
     * list keeps "I don't have the exact figure, about 20k" as an answer.
     * "Nope" (Brett, 2026-10-11, #3776) opens a no as well.
     */
    public static function isNegative(string $message): bool
    {
        $nouns = 'investments?|accounts?|savings|pensions?|isas?|propert(?:y|ies)|polic(?:y|ies)|cover|insurance|goals?|debts?|loans?|mortgages?|dependants?|children|kids|gifts?|trusts?|shares|funds?|holdings?';

        return preg_match('/^\s*(?:no|nope|nah|none|nothing|neither|that(?:[\x{2019}\x{0027}]s|\s+is)\s+(?:all|it|everything)|all\s+done|done|no\s+more)\b/iu', $message) === 1
            || preg_match('/^\s*(?:i\s+)?(?:don[\x{2019}\x{0027}]?t|do\s+not|haven[\x{2019}\x{0027}]?t|have\s+not)\s+(?:have|got)\s+(?:any|one|another|an?)\b|^\s*i\s+have\s+(?:no|none)\b|^\s*(?:i[\x{2019}\x{0027}]?ve\s+)?not\s+got\s+(?:any|one|an?)\b|\bnothing\s+else\b|\bno\s+other\b/iu', $message) === 1
            || preg_match('/^\s*(?:i\s+)?(?:don[\x{2019}\x{0027}]?t|do\s+not|haven[\x{2019}\x{0027}]?t|have\s+not)\s+(?:have|got)\s+(?:any\s+|other\s+)?(?:'.$nouns.')\b/iu', $message) === 1;
    }

    /** "Yes", "sure, go ahead", "okay", "of course" at the start, and not a no. */
    public static function isAffirmative(string $message): bool
    {
        return ! self::isNegative($message)
            && preg_match('/^\s*(?:yes|yep|yeah|yup|sure|ok|okay|go\s+ahead|please|fine|of\s+course|absolutely|definitely|certainly|sounds\s+good|why\s+not|happy\s+to|let[\x{2019}\x{0027}]?s\s+(?:go|do\s+it)|correct|continue|carry\s+on)\b/iu', $message) === 1;
    }
}
