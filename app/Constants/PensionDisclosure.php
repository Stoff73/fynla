<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * The ONE home for what the application says about Defined Benefit pensions when
 * it shows a figure that excludes them (Rule 20, W-0241).
 *
 * **CSJ ruling, 2026-08-22 — settled, do not re-open.** A Defined Benefit scheme is
 * **excluded** from every capital figure the application reports, because it has no
 * capital value: a Cash Equivalent Transfer Value is a quotation most users have
 * never obtained, and capitalising the accrued pension at a multiple would be
 * inventing a figure. CSJ considered and **rejected** both of those. The scheme is
 * excluded and the exclusion is **disclosed**.
 *
 * **The defect this exists to prevent is not the exclusion — it is a bare zero.**
 * A user holding an NHS scheme paying £35,000 a year sees `£0` against "Pensions".
 * Without a sentence beside it, that reads as a bug, a lost record, or a valuation
 * of nothing. With it, it reads as a statement. **Any surface that prints a
 * pensions capital figure a Defined Benefit holder can see must print this beside
 * it**, and must read the text from here rather than keeping its own copy — three
 * frontends already held three copies of this sentence, which is precisely how they
 * drift.
 *
 * Known consumers, all reading this constant:
 * - `NetWorthService` — the `/net-worth` pages on web, `/m` and native
 * - `MobileDashboardAggregator` — the dashboard net worth on the same three surfaces
 * - the risk-profile capacity-for-loss factor, which prints "£0 pensions" as a
 *   literal term in its formula
 *
 * **Adding a consumer means reading these constants, never re-typing the sentence.**
 * If the wording is ever wrong it must be wrong in exactly one place.
 *
 * Rule 12: descriptive text only. No score, no rating, no completeness percentage.
 * Rule 9: no acronyms — "Defined Benefit" and "Defined Contribution" in full.
 */
final class PensionDisclosure
{
    /**
     * The canonical sentence, for anywhere with room for a full line of text.
     *
     * Byte-identical to the wording already shipped on all three `/net-worth`
     * pages, so consolidating the copies onto this constant changed nothing a user
     * sees. **Keep it that way**: this is not the place to improve the wording as a
     * side effect of some other change.
     */
    public const DEFINED_BENEFIT_EXCLUDED = 'Defined Benefit pensions are excluded from net worth — they provide a guaranteed income rather than accessible capital.';

    /**
     * For a caption, subtitle or table cell that cannot hold the full sentence.
     *
     * **Not a truncation of the constant above — a shorter sentence that is
     * complete on its own.** A clipped disclosure is not a disclosure: a
     * `line-clamp-2` caption or a one-line cell that cuts the sentence mid-clause
     * discloses nothing, and W-0241's acceptance is that no surface presents the
     * total as complete. Pick this one when the space is tight rather than letting
     * the long one be cut.
     *
     * **Picking the shorter constant is NOT by itself the fix, and assuming it is
     * will waste your time.** The risk-profile capacity-for-loss factor swapped to
     * this constant and its disclosure was still clipped, because the length was
     * never the problem — **the disclosure had been appended to another sentence**,
     * and the combined string overflowed a `line-clamp-2` container. It was fixed by
     * giving the disclosure its own field on its own unclamped line, not by
     * shortening it.
     *
     * **So verify by MEASURING the rendered element, never by reading the CSS.**
     * At each viewport width the surface really uses:
     *
     * ```js
     * const el = document.querySelector('<the element carrying the disclosure>');
     * el.scrollHeight > el.clientHeight   // vertically clipped
     * el.getBoundingClientRect().width > el.parentElement.getBoundingClientRect().width  // overflows its column
     * ```
     *
     * Reading the stylesheet tells you which rules exist; it does not tell you what
     * the user can see. Both checks were run against every live consumer of these
     * constants on 2026-08-22 — see `F-0024` §11.
     */
    public const DEFINED_BENEFIT_EXCLUDED_SHORT = 'Excludes Defined Benefit pensions, which pay an income rather than a capital sum.';

    /**
     * What the pensions capital figure actually counts, for use as a heading or
     * subtitle above a list of schemes.
     *
     * Replaces "Accessible pension capital", which was the sentence that made a £0
     * line read as an error. A household whose only scheme is a final salary
     * pension sees a £0 capital figure **and** its £35,000 a year listed beneath;
     * this has to make both read as coherent rather than contradictory.
     */
    public const PENSION_CAPITAL_SUBTITLE = 'Defined Contribution pension value. Defined Benefit schemes are listed with the income they pay, not a capital value.';
}
