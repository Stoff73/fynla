/**
 * Expenditure composition — one home for how a plan discloses what its
 * "Annual Expenditure" figure is made of (W-0140).
 *
 * The figure keeps its meaning: recorded entries PLUS financial commitments.
 * Disposable Income subtracts it, and must subtract commitments to be true. What
 * changed is that the plans no longer print the composed total under a bare label
 * naming only one of its components — and a user who has recorded no expenditure
 * is told so, instead of being shown a number that is entirely commitments.
 *
 * The server composes the numbers (UserProfileService::expenditurePresentation via
 * DisposableIncomeAccessor). This module owns only the labels and the presentation
 * decision, so the four plan panels and the adviser print pack say the same thing.
 */

export const EXPENDITURE_COMPOSITION_LABELS = {
  recorded: 'Recorded Expenditure',
  commitments: 'Financial Commitments',
  basis: 'Expenditure Basis',
};

/** Shown in place of an amount when the user has recorded no expenditure. */
export const NONE_RECORDED_LABEL = 'None recorded';

/**
 * Rows disclosing the composition of the annual expenditure figure.
 *
 * @param {object|null|undefined} composition personal_information.expenditure_composition
 * @returns {Array<{key: string, label: string, amount: number, text: string|null}>}
 *          `text` overrides the amount when there is no amount to state.
 */
export function expenditureCompositionRows(composition) {
  if (!composition) return [];

  const hasRecorded = composition.has_recorded_expenditure === true;

  return [
    {
      key: 'recorded',
      label: EXPENDITURE_COMPOSITION_LABELS.recorded,
      amount: Number(composition.recorded_annual) || 0,
      text: hasRecorded ? null : NONE_RECORDED_LABEL,
    },
    {
      key: 'commitments',
      label: EXPENDITURE_COMPOSITION_LABELS.commitments,
      amount: Number(composition.commitments_annual) || 0,
      text: null,
    },
  ];
}

/**
 * The basis statement, shown only where the total would otherwise be read as
 * spending the user entered.
 *
 * @param {object|null|undefined} composition personal_information.expenditure_composition
 * @returns {string|null}
 */
export function expenditureCompositionNote(composition) {
  if (!composition || composition.has_recorded_expenditure === true) return null;

  return composition.basis || null;
}
