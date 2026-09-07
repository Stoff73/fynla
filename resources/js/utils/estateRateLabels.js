/**
 * Estate rate labels — one home for the captions that name a configured
 * Inheritance Tax rate (W-0461 criterion 3).
 *
 * The plan card and the printed plan draw the same charitable-threshold panel
 * from two mechanisms (`Plans/Estate/EstateCurrentSituation.vue` and
 * `Plans/Shared/planPrintMixin.js`). Both used to spell "36%" into the label
 * themselves, so a user moving between screen and print saw the same wrong rate
 * twice, which reads as corroboration. The label is composed here so the pair
 * converges on ONE source rather than being edited in lockstep.
 *
 * Rates arrive as decimals (0.36), matching the `taxConfig` store getters and
 * the `/api/tax/config` snapshot they hydrate from.
 */

import { formatPercentage } from '@/utils/currency';

/**
 * "Threshold for 36% Rate" — the charitable-giving panel's threshold caption.
 *
 * @param {number|null|undefined} reducedRate the IHT reduced rate, as a decimal
 * @returns {string}
 */
export function charitableThresholdRateLabel(reducedRate) {
  return `Threshold for ${formatPercentage(reducedRate, { isDecimal: true, decimals: 0 })} Rate`;
}
