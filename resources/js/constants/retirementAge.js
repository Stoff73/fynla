/**
 * The age assumed when a household has not said when it retires.
 *
 * **W-0196 — one home.** The backend held SEVEN private `DEFAULT_RETIREMENT_AGE`
 * constants on two different numbers, and the frontend held eleven more hardcoded
 * fallbacks split the same 67/68 way. A user could see 67 on the accumulation chart
 * and 68 on the capital adequacy tab, computed from the same profile, in the same
 * session, and nothing on either screen explained the difference.
 *
 * **Why 67 and not 68.** 67 was already anchored: `DBPension`'s default is
 * deliberately the same 67 as the pension projector's, so a pension cannot count as
 * income from one age while being projected forward from another (W-0036). 68 was the
 * outlier, not the pair.
 *
 * The mirror of `App\Services\Retirement\RetirementAgeResolver::DEFAULT_RETIREMENT_AGE`.
 * Two languages, one home each, cross-referenced — change one, change both.
 *
 * **This is NOT the State Pension age.** That is legislated by cohort and is a
 * different question with a different answer (W-0197, W-0516). The `state_pension_age`
 * fallbacks scattered through these components are deliberately left alone here; they
 * belong to those items, and folding them in would merge two unrelated numbers.
 */
export const DEFAULT_RETIREMENT_AGE = 67;

/**
 * The scheme retirement age assumed for a defined benefit pension that does not record
 * one. Mirrors `App\Models\DBPension::DEFAULT_NORMAL_RETIREMENT_AGE`, which reads the
 * resolver — so it is the same number by construction on both sides.
 *
 * W-0196: two components fell back to **65** here, disagreeing with the model by two
 * years for any scheme that had not recorded its own age.
 */
export const DEFAULT_DB_NORMAL_RETIREMENT_AGE = DEFAULT_RETIREMENT_AGE;
