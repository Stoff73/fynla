import { describe, expect, it } from 'vitest';
import IHTPlanning from '@/components/Estate/IHTPlanning.vue';

/**
 * W-0132 — where the Inheritance Tax rate label comes from.
 *
 * It came from `charitableBequest ? '36%' : '40%'` — two hardcoded literals chosen by
 * `users.charitable_bequest`, a column the client is never sent. `charitableBequest`
 * was therefore `undefined` on every fresh load, so the label read **40% permanently**,
 * regardless of the will and regardless of what the user had answered. On Priya
 * Raman's screen it sat above a figure the server had computed at 36%.
 *
 * The computed is exercised directly rather than through a mount: the whole point is
 * which FIELD it reads, and a mount would prove nothing a mount of the old code would
 * not also have proved. `iht_rate_percent` is the server's answer; a label built from
 * anything else fails here.
 */
const label = (ihtData) => IHTPlanning.computed.ihtRateLabel.call({ ihtData });
const qualifies = (ihtData) => IHTPlanning.computed.qualifiesForReducedRate.call({ ihtData });
const recorded = (ihtData) => IHTPlanning.computed.charitableLegacyRecorded.call({ ihtData });

describe('IHTPlanning — the rate label reads the calculation (W-0132)', () => {
  it('states the reduced rate the server applied, on a payload with no toggle in it', () => {
    // Priya's payload, verbatim in shape: there is no `charitable_bequest` key
    // anywhere, because the client is never sent one.
    expect(label({ iht_rate_percent: 36, projected_iht_rate_percent: 36 })).toBe('36%');
  });

  it('states the standard rate where the legacy does not clear the threshold', () => {
    expect(label({ iht_rate_percent: 40, projected_iht_rate_percent: 40 })).toBe('40%');
  });

  it('does not hardcode either percentage', () => {
    // The rates are `TaxConfigService`'s to decide (Rule 2). 36 and 40 only happen to
    // be this tax year's values, and the old label was two string literals.
    expect(label({ iht_rate_percent: 30, projected_iht_rate_percent: 30 })).toBe('30%');
  });

  it('states the two columns separately when the projection reaches a different rate', () => {
    // W-0136: the projection re-runs the 10% test against the projected estate, so a
    // single label printed across both columns is wrong in one of them.
    expect(label({
      iht_rate_percent: 36,
      projected_iht_rate_percent: 40,
      estimated_age_at_death: 84,
    })).toBe('36% today, 40% at age 84');
  });

  it('says nothing at all before the calculation has arrived', () => {
    // Silence beats a default. The old code had no way to say "I do not know yet" —
    // an absent flag was falsy, and falsy meant 40%.
    expect(label({})).toBeNull();
    expect(label({ projected_iht_rate_percent: 40 })).toBeNull();
  });
});

describe('IHTPlanning — the charitable card reads the will (W-0132)', () => {
  it('reports a recorded legacy from what the server deducted', () => {
    expect(recorded({ charitable_deduction: 10000 })).toBe(true);
    expect(recorded({ charitable_deduction: 0 })).toBe(false);
    expect(recorded({})).toBe(false);
  });

  it('reports qualification from the rate type the server decided, not from a toggle', () => {
    expect(qualifies({ iht_rate_type: 'reduced' })).toBe(true);
    expect(qualifies({ iht_rate_type: 'standard' })).toBe(false);
    expect(qualifies({})).toBe(false);
  });

  it('never re-derives the rate from whether a legacy exists', () => {
    // A legacy that does not clear the 10% threshold is deducted under IHTA 1984 s23
    // AND leaves the rate at 40%. Treating "has a legacy" as "qualifies" is the
    // shortcut that has to stay dead — the server runs the threshold test.
    const belowThreshold = { charitable_deduction: 500, iht_rate_type: 'standard', iht_rate_percent: 40 };

    expect(recorded(belowThreshold)).toBe(true);
    expect(qualifies(belowThreshold)).toBe(false);
    expect(label(belowThreshold)).toBe('40%');
  });
});
