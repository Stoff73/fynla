<?php

declare(strict_types=1);

/**
 * W-0497. Forty-four user-facing strings met the reader cold with six acronyms:
 * RNRB, NRB, IHT, PET, CLT and GROB. These are `implementation_steps`, `key_benefits`
 * and `key_risks` — the guidance the user is expected to act on — and GROB in
 * particular ("gift with reservation of benefit") cannot be looked up from initials.
 *
 * **The unit is the method, because the method is the screen.**
 * `TrustPlanningStrategy.vue` renders `strategy_name` (:115), `description` (:116),
 * `implementation_steps` (:209), `key_benefits` (:264) and `key_risks` (:279) in one
 * card, and each strategy method returns exactly one of those cards. So CSJ's
 * 2026-08-24 amendment — spell it out once, then the acronym is fine on that surface —
 * resolves to: an acronym used anywhere in a method must be expanded somewhere in that
 * same method.
 *
 * This is why the item insisted the sweep be done as one piece. Expanding only the
 * RNRB instances would have left the same card reading "Immediate Discretionary Trust
 * (CLT)" beside a bare "within NRB of £325,000" — half-converted, and worse than
 * untouched because it looks finished.
 *
 * House style is the one already in `IHTCalculationService:318`: expanded on first use,
 * abbreviated afterwards, inside the text the reader has in front of them.
 */
dataset('estateCopyFiles', [
    'trust strategies' => 'app/Services/Estate/PersonalizedTrustStrategyService.php',
    'estate onboarding' => 'app/Services/Onboarding/EstateOnboardingFlow.php',
    'gifting strategies' => 'app/Services/Estate/PersonalizedGiftingStrategyService.php',
]);

it('expands every acronym on the screen that uses it', function (string $relativePath) {
    $expansions = [
        'RNRB' => 'Residence Nil Rate Band',
        'NRB' => 'Nil Rate Band',
        'IHT' => 'Inheritance Tax',
        'PET' => 'Potentially Exempt Transfer',
        'CLT' => 'Chargeable Lifetime Transfer',
        'GROB' => 'gift with reservation of benefit',
    ];

    $source = (string) file_get_contents(base_path($relativePath));

    // Tokenised, not regexed. A regex for quoted literals is wrong on PHP source: an
    // apostrophe inside a docblock — `User's IHT profile` — opens a bogus string and
    // the scan then swallows comments and code, which reported offences in
    // `__construct()` and `calculateNRBAvoidanceProjection()` that do not exist.
    // `token_get_all` knows what a string literal is; nothing else here does.
    $tokens = token_get_all($source);

    $copyByMethod = [];
    $current = null;
    $expectName = false;

    foreach ($tokens as $token) {
        if (! is_array($token)) {
            continue;
        }

        if ($token[0] === T_FUNCTION) {
            $expectName = true;

            continue;
        }

        if ($expectName) {
            // An anonymous `function ($x)` has no name token, so the flag must clear
            // on the first token that is not whitespace — otherwise the next T_STRING
            // in the body (`toArray`, say) is taken as the method name and literals
            // get filed under a method that does not exist.
            if ($token[0] === T_STRING) {
                $current = $token[1];
                $copyByMethod[$current] ??= [];
            }

            if ($token[0] !== T_WHITESPACE) {
                $expectName = false;
            }

            continue;
        }

        if ($current !== null && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
            $copyByMethod[$current][] = trim($token[1], "'\"");
        }
    }

    $offenders = [];

    foreach ($copyByMethod as $name => $literals) {
        // The whole method is one card, so an expansion in any of its literals is on
        // the reader's screen alongside the abbreviation.
        $copy = implode(' | ', $literals);

        foreach ($expansions as $acronym => $expansion) {
            if (! preg_match('/\b'.$acronym.'\b/', $copy)) {
                continue;
            }

            if (stripos($copy, $expansion) === false) {
                $offenders[] = "{$name}() uses {$acronym} without \"{$expansion}\"";
            }
        }
    }

    expect($offenders)->toBe([]);
})->with('estateCopyFiles');

it('leaves the grandfathered glyphs alone', function () {
    // Rule 15 is forward-only and these blocks carry ✓, ✗ and ⚠️. The item says in
    // terms: change the words, leave the glyphs. This fails if a sweep tidies them,
    // which is a decision that is CSJ's alone.
    $source = (string) file_get_contents(base_path('app/Services/Estate/PersonalizedTrustStrategyService.php'));

    expect(substr_count($source, '✓'))->toBeGreaterThan(0)
        ->and(substr_count($source, '✗'))->toBeGreaterThan(0);
});
