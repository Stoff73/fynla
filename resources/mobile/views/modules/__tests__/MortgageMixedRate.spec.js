import { describe, it, expect, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../../api.js', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
}));

import { apiGet } from '../../../api.js';
import MortgageDetail from '../MortgageDetail.vue';

/**
 * W-0351. A mixed-rate mortgage's split — what proportion of the balance sits on the
 * fixed rate and what on the variable — was stored correctly and displayable nowhere.
 * Web gated its two rows on `fixed_rate_percentage` / `variable_rate_percentage`, which
 * `MortgageResource` never serialised, so the gate read `undefined` and the rows were
 * structurally unreachable. `/m` had no row for it at all.
 *
 * The API serves both fields now, so both surfaces state the same fact (Rule 19).
 */
const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel', 'contextualRequest'],
  template: '<main><slot /></main>',
};

function stubMortgage(overrides = {}) {
  apiGet.mockImplementation(() => Promise.resolve({
    ok: true,
    status: 200,
    data: {
      // The view reads `data.data.mortgage` — a fixture shaped `data.data` alone
      // leaves `this.mortgage` null and every assertion passes vacuously.
      data: {
        mortgage: {
        id: 4,
        lender_name: 'Example Bank',
        mortgage_type: 'repayment',
        ownership_type: 'individual',
        monthly_payment: 1200,
        interest_rate: 5.25,
        rate_type: 'mixed',
        fixed_rate_percentage: 60,
        variable_rate_percentage: 40,
        fixed_interest_rate: 12,
        variable_interest_rate: 14.75,
        remaining_term_months: 240,
        ...overrides,
        },
      },
    },
  }));
}

async function mountDetail() {
  const wrapper = mount(MortgageDetail, {
    global: {
      stubs: { MobileChrome: MobileChromeStub },
      mocks: { $route: { params: { id: 4 } }, $router: { push: vi.fn() } },
    },
  });
  await flushPromises();
  await flushPromises();
  return wrapper;
}

describe('/m mortgage detail — mixed rate split (W-0351)', () => {
  it('shows the split as the label and the rate as the value', async () => {
    stubMortgage();
    const wrapper = await mountDetail();

    expect(wrapper.text()).toContain('Fixed (60.00%)');
    expect(wrapper.text()).toContain('12.00%');
    expect(wrapper.text()).toContain('Variable (40.00%)');
    expect(wrapper.text()).toContain('14.75%');
  });

  it('shows no split rows for a mortgage that is not mixed rate', async () => {
    stubMortgage({ rate_type: 'fixed' });
    const wrapper = await mountDetail();

    expect(wrapper.text()).not.toContain('Fixed (60.00%)');
    expect(wrapper.text()).not.toContain('Variable (40.00%)');
  });
});
