import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import CanonicalPortfolio from '../CanonicalPortfolio.vue';

/**
 * W-0442 acceptance 3. Units, purchase price, current price and purchase date are
 * captured and stored, shown on both web tables since the web half of this item, and
 * invisible on `/m` — the `financial_portfolio_v1` contract did not carry them, so no
 * template change alone could have fixed it.
 *
 * The units formatter is IMPORTED from the same module the two web tables use rather
 * than reimplemented here, because it distinguishes "no unit count recorded" from
 * "zero units held" and a second copy would be free to lose that.
 */
function portfolio(holdingOverrides = {}) {
  return {
    contract_version: 'financial_portfolio_v1',
    wrapper_type: 'investment_account',
    wrapper_name: 'Dealing Account',
    recorded_wrapper_value: 50000,
    holdings: [{
      id: 1,
      name: 'Global Equity Fund',
      ticker: 'GEF',
      asset_type: 'fund',
      current_value: 50000,
      wrapper_percentage: 100,
      whole_relevant_portfolio_percentage: 100,
      classified_exposure: [],
      classification: null,
      fees: { available: false, unavailable_reason: 'recorded_holding_charge_unavailable' },
      performance: { available: false },
      quantity: 4211.5,
      purchase_price: 9.87,
      current_price: 11.87,
      purchase_date: '2021-06-14',
      ...holdingOverrides,
    }],
    analysis: { allocation: [], comparisons: {}, coverage_percent: 100 },
    performance_history: { available: false, points: [] },
  };
}

describe('/m holdings show what they store (W-0442)', () => {
  it('renders units, both prices and the purchase date', () => {
    const wrapper = mount(CanonicalPortfolio, { props: { portfolio: portfolio() } });

    // Thousands-separated: `formatUnits` uses `toLocaleString('en-GB')`. Asserting the
    // raw '4211.5' would have failed against correct code, and "fixing" the formatter
    // to match would have broken both web tables that share it.
    expect(wrapper.text()).toContain('4,211.5 units');
    expect(wrapper.text()).toContain('Bought at');
    expect(wrapper.text()).toContain('Now');
    expect(wrapper.text()).toContain('2021-06-14');
  });

  /**
   * Pence matter on a unit price in a way they do not on an account balance, so the
   * price formatter is deliberately not the currency one, whose fraction digits are 0.
   */
  it('keeps the pence on a unit price', () => {
    const wrapper = mount(CanonicalPortfolio, { props: { portfolio: portfolio() } });

    expect(wrapper.text()).toContain('£9.87');
    expect(wrapper.text()).toContain('£11.87');
  });

  it('shows nothing rather than zeros when a holding records none of them', () => {
    const wrapper = mount(CanonicalPortfolio, {
      props: {
        portfolio: portfolio({
          quantity: null, purchase_price: null, current_price: null, purchase_date: null,
        }),
      },
    });

    expect(wrapper.text()).not.toContain('units');
    expect(wrapper.text()).not.toContain('Bought at');
    expect(wrapper.text()).not.toContain('£0');
  });
});
