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

  // The count is the server's (TaxStrategyService summary.headroom_count, which
  // leaves out unconfirmed allowances and never adds unlike allowances); /m
  // shows it as sent (CSJ 2026-10-01).
  it('shows the server\'s count of allowances with headroom', () => {
    expect(TaxStrategy.computed.headroomCount.call({ dashboard: { summary: { headroom_count: 2 } } })).toBe(2);
    expect(TaxStrategy.computed.headroomCount.call({ dashboard: {} })).toBe(0);
  });

  it('labels allowance use as unconfirmed instead of showing false availability', () => {
    const label = TaxStrategy.methods.remainingLabel.call({
      fmt: (value) => `£${value}`,
    }, {
      available: true,
      known: false,
      utilisation_pct: 0,
      remaining: 0,
      tile_state: 'unconfirmed',
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
