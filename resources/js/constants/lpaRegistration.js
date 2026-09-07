/**
 * What the Office of the Public Guardian charges to register one Lasting Power
 * of Attorney, and how long it says registration takes.
 *
 * **W-0109 — one home.** These two figures were written out in FOUR places with
 * no shared source: twice in `LpaComplianceService` and twice here in the
 * frontend. Nothing connected them, so the OPG changing either would have needed
 * four edits by someone who knew all four existed.
 *
 * **The timescale was already stale, which is the proof the arrangement does not
 * work:** every copy said "up to 8 weeks" long after the OPG's published figure
 * moved to 20. Four copies drifted together because none was anybody's job.
 *
 * The mirror of `App\Constants\EstateDefaults::LPA_REGISTRATION_FEE` and
 * `::LPA_REGISTRATION_WEEKS`. Two languages, one home each, cross-referenced —
 * change one, change both.
 */

/** OPG registration fee, per Lasting Power of Attorney, in pounds. */
export const LPA_REGISTRATION_FEE = 82;

/** Upper bound of the OPG's published registration timescale, in weeks. */
export const LPA_REGISTRATION_WEEKS = 20;
