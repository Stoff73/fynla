/**
 * The one home for a holding's asset type — its stored values and their labels.
 *
 * **W-0443.** `formatAssetType` existed as ELEVEN private maps across four directories,
 * plus `HoldingForm`'s inline `<option>` list, `InlineHoldingsEditor`'s `ASSET_TYPES`
 * const, and `/m`'s `assetTypeLabel()`. Rule 20 does not say the copies must agree; it
 * says there must not be copies.
 *
 * **They did not agree.** `HoldingsTable.formatAssetType` title-cased an unknown value,
 * so `uk_equity` read **"Uk Equity"**, while `PensionDetailInline` mapped it explicitly
 * to **"UK Equity"**. The same stored value read differently depending on the screen,
 * and one spelling broke Rule 9 as well.
 *
 * ## The relationship to the server, stated deliberately (acceptance 3)
 *
 * The authority is the `holdings.asset_type` column enum, and these are its exact ten
 * values. The same list appears server-side in
 * `App\Http\Requests\Investment\StoreHoldingRequest::getAssetTypes()` and in
 * `DCPensionHoldingsController`'s `in:` rules — this file is the client mirror of that
 * vocabulary, the way `ownership.js` mirrors `CalculatesOwnershipShare`. **Add a value
 * to the column and the rules, then here; never here alone.**
 *
 * ## `/m` shares this rather than mirroring it (acceptance 4)
 *
 * `resources/mobile/` has its own bundle and its own rendering of holdings, but it
 * already imports from `resources/js/utils` (`ownership.js`, `holdingUnits.js`), so a
 * third copy of this vocabulary would be a choice rather than a constraint. It imports
 * this module.
 */

/**
 * Stored value to label. Order is the column enum's own order.
 *
 * Rule 9: "UK" and "US" are the country names, not words to title-case. The
 * title-casing fallback that produced "Uk Equity" is deliberately not reproduced here —
 * see {@link formatAssetType}.
 */
export const ASSET_TYPE_LABELS = Object.freeze({
  equity: 'Equity',
  bond: 'Bond',
  fund: 'Fund',
  etf: 'Exchange-Traded Fund',
  alternative: 'Alternative',
  uk_equity: 'UK Equity',
  us_equity: 'US Equity',
  international_equity: 'International Equity',
  cash: 'Cash',
  property: 'Property',
});

/** The stored values, in the column enum's order — for `<option>` lists and selects. */
export const ASSET_TYPES = Object.freeze(
  Object.entries(ASSET_TYPE_LABELS).map(([value, label]) => ({ value, label }))
);

/**
 * A stored asset type's label.
 *
 * An unrecognised value returns an em dash rather than a title-cased guess. A guess is
 * how "Uk Equity" reached a screen, and title-casing an unknown value presents a stored
 * string the vocabulary does not contain as though it were a real label.
 *
 * @param {string|null|undefined} assetType
 * @returns {string}
 */
export function formatAssetType(assetType) {
  if (!assetType) return '—';

  return ASSET_TYPE_LABELS[assetType] ?? '—';
}
