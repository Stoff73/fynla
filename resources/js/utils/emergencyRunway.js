/**
 * How every surface says "we cannot work out your runway" (W-0495).
 *
 * A runway is total cash divided by monthly spending. With no spending recorded
 * there is no runway to state — and the application used to say **0 months**,
 * which is a different claim entirely and the alarming one: a household holding
 * £40,000 in cash was told it had nothing.
 *
 * The wording lives here, and not beside each renderer, because the same panel
 * is drawn by the desktop savings overview and by `/m`, which is a separate
 * bundle. Two copies of one sentence is the drift Rule 20 forbids, and this is
 * the sentence a user acts on.
 */

/** Shown in place of a figure when no expenditure has been recorded. */
export const RUNWAY_UNAVAILABLE_LABEL = 'Add your monthly spending';

/** The line under it, saying what to do about it. */
export const RUNWAY_UNAVAILABLE_HINT = 'We need what you spend each month before we can work out how long your cash would last.';

/**
 * The runway as a sentence, or the prompt where it cannot be worked out.
 *
 * Names the basis rather than implying the money is to hand — the agreed
 * answer to W-0276, kept identical on every surface.
 *
 * @param {number|null|undefined} months
 * @returns {string}
 */
export function runwayLabel(months) {
  if (months === null || months === undefined || Number.isNaN(Number(months))) {
    return RUNWAY_UNAVAILABLE_LABEL;
  }

  const m = Number(months);
  const rounded = m >= 10 ? Math.round(m) : Math.round(m * 10) / 10;

  return `${rounded} ${rounded === 1 ? 'month' : 'months'} from cash savings`;
}
