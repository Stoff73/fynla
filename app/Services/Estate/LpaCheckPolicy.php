<?php

declare(strict_types=1);

namespace App\Services\Estate;

/**
 * The single home for "what Fynla is entitled to say about a user's Lasting
 * Power of Attorney after running its checks".
 *
 * W-0100, 2026-08-21. Until today `LpaComplianceService` returned the literal
 * string 'compliant' and the web checklist rendered it as "Compliant" in the
 * success colour. That was an overclaim on two independent grounds, and neither
 * depends on the checks being any good:
 *
 *  1. Fynla may report what it checked and what it did not. It may never tell a
 *     user that something they hold is compliant, approved, valid or sufficient.
 *     The claim describes the ACT PERFORMED, not the OBJECT. The workforce
 *     constitution (`constitution/05-perimeter.md` §7.3) already states this
 *     rule of the compliance agent — "its output is never an approval" — and
 *     names the failure mode precisely: "a confident-looking compliance sign-off
 *     that nobody questions… it stops a human from looking."
 *
 *  2. The object assessed is not the instrument. These checks run against stored
 *     form data. Validity turns on events the application never observes — the
 *     donor's actual capacity at signing, whether the certificate provider truly
 *     gave the certificate required by Mental Capacity Act 2005 Sch 1 para
 *     2(1)(e), the manner and order of execution, and whether the Public
 *     Guardian has registered the instrument (Sch 1 paras 4–5). A perfect
 *     checker would still not be entitled to the word.
 *
 * Rule 20 — this class is the ONE place the outcome vocabulary and the
 * disclosure live. `LpaComplianceService` composes its payload from here and
 * every client renders that payload; there is no second copy on the web
 * component, and any future `/m` or native surface gets the same words without a
 * second decision being written. A second copy of this copy anywhere is a
 * violation.
 *
 * Copy drafted against the six framing requirements in
 * `workforce/ops/reports/2026-08-21-W-0100-lpa-perimeter-review.md` §Q3 and
 * reviewed by ux-writing-expert, 2026-08-21.
 *
 * @see WillTypePolicy the same pattern for the will builder's refusal wording.
 */
final class LpaCheckPolicy
{
    /**
     * No check failed and none raised a point. Deliberately NOT a verdict on the
     * instrument — it reports the outcome of the checks that were run.
     */
    public const OUTCOME_NO_ISSUES = 'no_issues_found';

    /** At least one check raised a point, and none failed. */
    public const OUTCOME_POINTS = 'points_to_look_at';

    /** At least one check did not pass. */
    public const OUTCOME_NOT_PASSED = 'checks_not_passed';

    public const HEADING = 'What we checked';

    public const NOT_CHECKED_HEADING = 'What we did not check';

    public const NOT_CHECKED_INTRO = 'These checks look only at the details you have entered. They cannot tell you whether your Lasting Power of Attorney is valid.';

    /**
     * Named at the point the result is shown, never in a footer — the trunk's §4
     * requirement, applied here for the first time outside a currency figure.
     * Each entry is one thing the application cannot observe.
     *
     * @var list<string>
     */
    public const NOT_CHECKED = [
        'Whether you had mental capacity when you signed.',
        'Whether your certificate provider discussed the document with you and gave their certificate.',
        'How the document was signed and witnessed, and in what order.',
        'Whether the Office of the Public Guardian has registered it.',
        // W-0102. The reliability limit on every name comparison, stated ONCE rather
        // than hedged into each of the five party-role messages — four qualified
        // sentences would be worse copy and four places to drift. It covers W-0102,
        // all three W-0103 conflicts, the W-0151 cross-instrument check, and any
        // future name comparison. If the matching is ever made fuzzier than
        // `WillDocumentService::isSameParty()` (case and whitespace only), this line
        // must be rewritten to stay true.
        'Whether two people whose names you typed differently are the same person, or two people with the same name are different people. We compare only the names you entered.',
        // W-0151. Regulation 8(3) of the 2007 regulations disqualifies eight
        // categories of person from giving the certificate. Fynla checks the two it
        // can see in its own data — an attorney on this instrument, and an attorney
        // on another power of attorney the same donor made. The rest are disclosed
        // rather than checked. The family clause is deliberate and is not trimmed to
        // match the brevity of the entries above it: `checkCertificateProviderKnownYears()`
        // presents two years' acquaintance as the requirement, which steers a donor
        // towards their spouse or child, and a generic line would not undo that steer.
        // Compliance ruled this limb DISCLOSURE-ONLY: "family member" is undefined in
        // reg 8(4), reg 2 and MCA 2005 s.64, so a check here would have Fynla drawing
        // a boundary the instrument leaves undrawn.
        'Whether anything disqualifies your certificate provider from giving the certificate — including being a member of your family.',
    ];

    public const NOT_CHECKED_CLOSE = 'Fynla cannot see any of these.';

    /**
     * The solicitor signpost. `WillTypePolicy` carries the will-side equivalent
     * as approved verbatim copy; if a third instrument ever needs this line, the
     * sentence is lifted into one shared home rather than copied a third time —
     * and `WillTypePolicy`'s text is not edited to achieve it, because it was
     * approved verbatim by compliance-lead and design-lead under W-0019.
     *
     * No Financial Conduct Authority wording: a Lasting Power of Attorney is a
     * creature of the Mental Capacity Act, not the financial-services regime.
     */
    public const REFERRAL = 'Fynla does not provide legal advice. If anything here is unclear, or your circumstances are complicated, please speak to a qualified solicitor.';

    /**
     * Which outcome the counts produce. Failures take precedence over points.
     */
    public static function outcomeFor(int $failed, int $warnings): string
    {
        if ($failed > 0) {
            return self::OUTCOME_NOT_PASSED;
        }

        return $warnings > 0 ? self::OUTCOME_POINTS : self::OUTCOME_NO_ISSUES;
    }

    /**
     * The single line shown in place of the old badge. Rendered as plain text,
     * never as a coloured pill — green carries "approved" without a word being
     * written, which is the claim this item exists to remove.
     */
    public static function outcomeLabel(int $failed, int $warnings): string
    {
        return match (self::outcomeFor($failed, $warnings)) {
            self::OUTCOME_NOT_PASSED => $failed === 1
                ? 'One check did not pass'
                : 'Some checks did not pass',
            self::OUTCOME_POINTS => $warnings === 1
                ? 'One check raised a point to look at'
                : 'Some checks raised a point to look at',
            default => 'No issues found in these checks',
        };
    }

    /**
     * Everything a client renders. Web today; any future `/m` or native Lasting
     * Power of Attorney surface renders the same payload rather than writing its
     * own words.
     *
     * @return array{
     *     outcome: string,
     *     outcome_label: string,
     *     heading: string,
     *     not_checked_heading: string,
     *     not_checked_intro: string,
     *     not_checked: list<string>,
     *     not_checked_close: string,
     *     referral: string
     * }
     */
    public static function payload(int $failed, int $warnings): array
    {
        return [
            'outcome' => self::outcomeFor($failed, $warnings),
            'outcome_label' => self::outcomeLabel($failed, $warnings),
            'heading' => self::HEADING,
            'not_checked_heading' => self::NOT_CHECKED_HEADING,
            'not_checked_intro' => self::NOT_CHECKED_INTRO,
            'not_checked' => self::NOT_CHECKED,
            'not_checked_close' => self::NOT_CHECKED_CLOSE,
            'referral' => self::REFERRAL,
        ];
    }
}
