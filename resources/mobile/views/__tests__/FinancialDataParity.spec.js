import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({
  apiGet: vi.fn(),
}));

import { apiGet } from '../../api.js';
import CanonicalPortfolio from '../../components/CanonicalPortfolio.vue';
import ISAContributionHistory from '../../components/ISAContributionHistory.vue';
import InvestmentAccountDetail from '../modules/InvestmentAccountDetail.vue';
import Protection from '../modules/Protection.vue';
import RetirementPensionDetail from '../modules/RetirementPensionDetail.vue';
import Savings from '../modules/Savings.vue';
import SavingsAccount from '../modules/SavingsAccount.vue';
import { store } from '../../store.js';

const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel', 'contextualRequest'],
  template: '<section><slot /></section>',
};

const isaStatus = {
  tax_year: '2026/27',
  current_tax_year: '2026/27',
  prior_tax_year: '2025/26',
  available_tax_years: ['2026/27', '2025/26'],
  total_used: 7500,
  total_allowance: 20000,
  remaining: 12500,
  owners: [{
    owner: { id: 1, label: 'You', name: 'Alex Example' },
    total_used: 7500,
    account_breakdown: [{
      account_id: 11,
      account_type: 'App\\Models\\SavingsAccount',
      account_name: 'Cash ISA',
      isa_type: 'cash_isa',
      owner: { id: 1, label: 'You', name: 'Alex Example' },
      contributed: 3000,
      provenance: 'recorded_ledger',
      contributions: [{ date: '2026-05-12', amount: 3000, provenance: 'recorded_ledger' }],
    }, {
      account_id: 22,
      account_type: 'App\\Models\\Investment\\InvestmentAccount',
      account_name: 'Stocks & Shares ISA',
      isa_type: 'stocks_and_shares_isa',
      owner: { id: 1, label: 'You', name: 'Alex Example' },
      contributed: 4500,
      provenance: 'legacy_current_year_summary',
      contributions: [{ date: null, amount: 4500, provenance: 'legacy_current_year_summary' }],
    }],
  }],
};

const portfolio = {
  contract_version: 'financial_portfolio_v1',
  wrapper_name: 'Balanced Account',
  holdings: [{
    id: 8,
    name: 'Mixed Fund',
    current_value: 4000,
    wrapper_percentage: 40,
    whole_relevant_portfolio_percentage: 25,
    classified_exposure: [
      { asset_class: 'equities', holding_percentage: 60 },
      { asset_class: 'unclassified', holding_percentage: 40 },
    ],
    fees: { available: false, unavailable_reason: 'recorded_holding_charge_unavailable' },
    performance: { available: false, unavailable_reason: 'recorded_cost_basis_unavailable' },
  }],
  analysis: {
    coverage_percent: 85,
    coverage_threshold_percent: 80,
    unclassified_value: 600,
    drift_available: true,
    allocation: [
      { asset_class: 'equities', portfolio_percentage: 60, classified_percentage: 70.59 },
      { asset_class: 'unclassified', portfolio_percentage: 15, classified_percentage: null },
    ],
    comparisons: {
      entered: {
        source: 'user_entered',
        drift_percentage_points: { equities: 5, bonds: -5 },
        unavailable_reason: null,
      },
      recommended: {
        source: 'fynla_recommended_asset_allocation',
        effective_at: '2026-08-01',
        drift_percentage_points: { equities: 10, bonds: -10 },
        unavailable_reason: null,
      },
    },
  },
  performance_history: {
    available: true,
    method: 'recorded_value_snapshots',
    points: [
      { date: '2026-01-01', value: 9000 },
      { date: '2026-07-01', value: 10000 },
    ],
  },
};

describe('/m financial data parity', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'parity-token';
    store.subscriptionStatus = { tier: 'premium', payment_enabled: true };
  });

  it('renders tappable canonical protection gap explanations without client recalculation', async () => {
    apiGet.mockResolvedValue({
      ok: true,
      status: 200,
      data: { data: {
        profile: { mortgage_balance: 999999, other_debts: 999999, annual_income: 999999 },
        policies: {},
        coverage_gaps: {
          contract_version: 'protection_gap_v1',
          calculated_at: '2026-08-10T12:00:00Z',
          categories: [{
            key: 'debt_protection',
            label: 'Debt protection',
            need: 150000,
            cover: 100000,
            shortfall: 50000,
            status: 'gap',
            severity: 'medium',
            inputs: { mortgage_balance: 140000, other_debts: 10000 },
            assumptions: [{ key: 'allocation_priority', value: 'Life cover is allocated to debt first' }],
            explanation: 'This gap compares your recorded debts with allocated life cover.',
            relevant_policies: [{ id: 4, provider: 'Canonical Life', cover: 100000 }],
          }],
        },
      } },
    });

    const wrapper = mount(Protection, {
      global: { stubs: { MobileChrome: MobileChromeStub }, mocks: { $router: { push: vi.fn() } } },
    });
    await flushPromises();

    expect(wrapper.text()).toContain('£50,000 short');
    expect(wrapper.text()).toContain('£100,000 of £150,000');
    expect(wrapper.text()).not.toContain('£1,999,998');

    await wrapper.get('[data-test="protection-gap-debt_protection"]').trigger('click');
    expect(wrapper.text()).toContain('This gap compares your recorded debts');
    expect(wrapper.text()).toContain('Mortgage Balance');
    expect(wrapper.text()).toContain('Canonical Life');
    expect(wrapper.text()).toContain('Calculated 10 Aug 2026');
  });

  it('renders one canonical portfolio component with look-through, unknown exposure, drift, fees and recorded history', () => {
    const wrapper = mount(CanonicalPortfolio, { props: { portfolio } });

    expect(wrapper.text()).toContain('Mixed Fund');
    expect(wrapper.text()).toContain('40.0% of this account');
    expect(wrapper.text()).toContain('25.0% of your relevant portfolio');
    expect(wrapper.text()).toContain('Equities 60.0%');
    expect(wrapper.text()).toContain('Unclassified 40.0%');
    expect(wrapper.text()).toContain('Holding charge unavailable');
    expect(wrapper.text()).toContain('Performance unavailable');
    expect(wrapper.text()).toContain('85.0% classified');
    expect(wrapper.text()).toContain('Entered portfolio');
    expect(wrapper.text()).toContain('Recommended allocation');
    expect(wrapper.text()).toContain('Effective 1 Aug 2026');
    expect(wrapper.text()).toContain('Equities+10.0pp');
    expect(wrapper.find('[data-test="portfolio-performance-chart"]').exists()).toBe(true);

    const unavailable = mount(CanonicalPortfolio, {
      props: { portfolio: { ...portfolio, performance_history: { available: false, points: [] } } },
    });
    expect(unavailable.text()).toContain('Recorded performance history is unavailable');
  });

  it('renders owner-aware ISA ledger history and visibly labels legacy summaries', () => {
    const wrapper = mount(ISAContributionHistory, {
      props: { status: isaStatus, accountId: 22, accountClass: 'investment' },
    });

    expect(wrapper.text()).toContain('2026/27');
    expect(wrapper.text()).toContain('Alex Example');
    expect(wrapper.text()).toContain('Stocks & Shares ISA');
    expect(wrapper.text()).toContain('£4,500');
    expect(wrapper.text()).toContain('Annual summary from the account record');
    expect(wrapper.text()).not.toContain('Cash ISA');
  });

  it('opens the overview ISA contribution list containing cash and Stocks & Shares ISA records', async () => {
    apiGet.mockResolvedValue({
      ok: true,
      status: 200,
      data: { data: { accounts: [], account_count: 0, account_limit: 2, isa_allowance: isaStatus } },
    });
    const wrapper = mount(Savings, {
      global: { stubs: { MobileChrome: MobileChromeStub }, mocks: { $router: { push: vi.fn() } } },
    });
    await flushPromises();

    await wrapper.get('[data-test="isa-allowance-card"]').trigger('click');
    expect(wrapper.text()).toContain('Cash ISA');
    expect(wrapper.text()).toContain('Stocks & Shares ISA');
    expect(wrapper.text()).toContain('Alex Example');
  });

  it('uses owner-aware ISA history and canonical portfolio data on an investment ISA detail page', async () => {
    apiGet.mockImplementation(async (path) => {
      if (path === '/api/investment') {
        return {
          ok: true,
          status: 200,
          data: { data: { accounts: [{
            id: 22,
            provider: 'Canonical Investments',
            platform: 'Example Platform',
            account_type: 'isa',
            current_value: 10000,
            owner_name: 'Alex Example',
            is_primary_owner: true,
            portfolio,
          }] } },
        };
      }
      return { ok: true, status: 200, data: { data: { isa_allowance: isaStatus } } };
    });

    const wrapper = mount(InvestmentAccountDetail, {
      global: {
        stubs: { MobileChrome: MobileChromeStub },
        mocks: { $route: { params: { id: '22' } }, $router: { push: vi.fn() } },
      },
    });
    await flushPromises();

    expect(wrapper.text()).toContain('OwnerAlex Example');
    expect(wrapper.text()).not.toContain('Ownership');
    expect(wrapper.text()).toContain('Stocks & Shares ISA');
    expect(wrapper.text()).toContain('Mixed Fund');
  });

  it('dates an investment contribution summary to its recorded tax year', async () => {
    apiGet.mockImplementation(async (path) => {
      if (path === '/api/investment') {
        return {
          ok: true,
          status: 200,
          data: { data: { accounts: [{
            id: 22,
            provider: 'Canonical Investments',
            platform: 'Example Platform',
            account_type: 'isa',
            current_value: 10000,
            contributions_ytd: 4800,
            monthly_contribution_amount: 400,
            tax_year: '2025/26',
            isa_subscription_current_year: 4800,
            owner_name: 'Alex Example',
            is_primary_owner: true,
            portfolio,
          }] } },
        };
      }
      return { ok: true, status: 200, data: { data: { isa_allowance: isaStatus } } };
    });

    const wrapper = mount(InvestmentAccountDetail, {
      global: {
        stubs: { MobileChrome: MobileChromeStub },
        mocks: { $route: { params: { id: '22' } }, $router: { push: vi.fn() } },
      },
    });
    await flushPromises();

    expect(wrapper.text()).toContain('£4,800 contributed in 2025/26');
    expect(wrapper.text()).not.toContain('£4,800 contributed this tax year');
  });

  it('shows the cash ISA owner and navigates canonical contribution tax years', async () => {
    const priorStatus = { ...isaStatus, tax_year: '2025/26', total_used: 1000 };
    apiGet.mockImplementation(async (path) => {
      if (path === '/api/savings/accounts/11') {
        return {
          ok: true,
          status: 200,
          data: { data: {
            id: 11,
            account_name: 'Cash ISA',
            account_type: 'cash_isa',
            current_balance: 5000,
            is_isa: true,
            isa_type: 'cash',
            owner_name: 'Alex Example',
            ownership_type: 'individual',
          } },
        };
      }
      if (path === '/api/savings') {
        return { ok: true, status: 200, data: { data: { isa_allowance: isaStatus } } };
      }
      return { ok: true, status: 200, data: { data: priorStatus } };
    });

    const wrapper = mount(SavingsAccount, {
      global: {
        stubs: { MobileChrome: MobileChromeStub },
        mocks: { $route: { params: { id: '11' } }, $router: { push: vi.fn() } },
      },
    });
    await flushPromises();

    expect(wrapper.text()).toContain('OwnerAlex Example');
    expect(wrapper.text()).not.toContain('Ownership');
    await wrapper.get('.msa-year:nth-child(2)').trigger('click');
    await flushPromises();
    expect(apiGet).toHaveBeenCalledWith('/api/savings/isa-allowance/2025/26', 'parity-token');
  });

  it('renders the same canonical portfolio contract on a DC pension detail page', async () => {
    apiGet.mockImplementation(async (path) => {
      if (path === '/api/retirement') {
        return {
          ok: true,
          status: 200,
          data: { data: { dc_pensions: [{
            id: 31,
            scheme_name: 'Workplace pension',
            current_fund_value: 10000,
            portfolio,
          }] } },
        };
      }
      return { ok: true, status: 200, data: { data: null } };
    });

    const wrapper = mount(RetirementPensionDetail, {
      global: {
        stubs: { MobileChrome: MobileChromeStub },
        mocks: { $route: { params: { type: 'dc', id: '31' } }, $router: { push: vi.fn() } },
      },
    });
    await flushPromises();

    expect(wrapper.text()).toContain('Mixed Fund');
    expect(wrapper.text()).toContain('Entered portfolio');
    expect(wrapper.text()).toContain('Recommended allocation');
  });

  it('suppresses contextual creation at the freemium cap and opens plan comparison', async () => {
    store.subscriptionStatus = { tier: 'free', payment_enabled: true };
    apiGet.mockResolvedValue({
      ok: true,
      status: 200,
      data: { data: { accounts: [{ id: 1 }, { id: 2 }], account_count: 2, account_limit: 2 } },
    });
    const push = vi.fn();
    const wrapper = mount(Savings, {
      global: { stubs: { MobileChrome: MobileChromeStub }, mocks: { $router: { push } } },
    });
    await flushPromises();

    expect(wrapper.findComponent(MobileChromeStub).props('contextualRequest')).toBeNull();
    await wrapper.get('.m-cap__upgrade').trigger('click');
    expect(push).toHaveBeenCalledWith('/subscription');
  });
});
