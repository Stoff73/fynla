import { describe, expect, it } from 'vitest';
import TaxStrategy from '../../../resources/mobile/views/TaxStrategy.vue';

describe('mobile Tax Strategy', () => {
  it('does not describe unused allowances as well-utilised when no actions are available', () => {
    const message = TaxStrategy.computed.emptyRecommendationsMessage.call({
      headroomCount: 4,
    });

    expect(message).toContain('unused allowances');
    expect(message).not.toContain('well-utilised');
  });

  it('does not count an unconfirmed allowance as known headroom', () => {
    const headroom = TaxStrategy.computed.headroom.call({
      userAllowances: [
        { key: 'isa_allowance', available: true, known: false, utilisation_pct: 0, remaining: 0 },
        { key: 'pension_annual_allowance', available: true, known: true, utilisation_pct: 20, remaining: 48000 },
      ],
    });

    expect(headroom).toHaveLength(1);
    expect(headroom[0].key).toBe('pension_annual_allowance');
  });

  it('counts any known positive remainder without adding unlike allowances together', () => {
    const headroom = TaxStrategy.computed.headroom.call({
      userAllowances: [
        { key: 'isa_allowance', available: true, known: true, utilisation_pct: 95, remaining: 1000 },
        { key: 'pension_annual_allowance', available: true, known: true, utilisation_pct: 20, remaining: 48000 },
        { key: 'personal_allowance', available: true, known: true, utilisation_pct: 100, remaining: 0 },
      ],
    });

    expect(headroom.map((item) => item.key)).toEqual(['isa_allowance', 'pension_annual_allowance']);
  });

  it('labels allowance use as unconfirmed instead of showing false availability', () => {
    const label = TaxStrategy.methods.remainingLabel.call({
      fmt: (value) => `£${value}`,
    }, {
      available: true,
      known: false,
      utilisation_pct: 0,
      remaining: 0,
    });

    expect(label).toBe('Current-year use not confirmed');
  });

  it('qualifies spouse transfer tax treatment instead of calling transfers universally exempt', () => {
    const copy = TaxStrategy.computed.householdIntro.call({ calculationMode: 'dual_earner' });

    expect(copy).toContain('usually be made without an immediate Capital Gains Tax charge');
    expect(copy).toContain('conditions apply');
    expect(copy).not.toContain('are exempt');
  });

  it('shows the income-tax jurisdiction the server says the calculator uses', () => {
    // One home for the sentence: TaxStrategyOutputDTO::TAX_BASIS_NOTE.
    const served = 'Income Tax bands use England, Wales and Northern Ireland rates. Scottish Income Tax bands are not modelled in this journey.';

    expect(TaxStrategy.computed.taxBasisNote.call({ dashboard: { tax_basis_note: served } })).toBe(served);
    expect(TaxStrategy.computed.taxBasisNote.call({ dashboard: {} })).toBe('');
  });
});
