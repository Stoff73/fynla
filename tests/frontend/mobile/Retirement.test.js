import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { apiGet, apiPost } from '../../../resources/mobile/api.js';
import Retirement from '../../../resources/mobile/views/modules/Retirement.vue';
import RetirementPensionDetail from '../../../resources/mobile/views/modules/RetirementPensionDetail.vue';

vi.mock('../../../resources/mobile/api.js', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
}));

afterEach(() => {
  vi.clearAllMocks();
});

describe('mobile Retirement', () => {
  it('reconciles total pension wealth with the current Defined Contribution pension values', () => {
    const total = Retirement.computed.totalPensionWealth.call({
      dcPensions: [
        { current_fund_value: '47500.00' },
        { current_fund_value: 12500 },
      ],
      analysis: { total_pension_wealth: 0 },
    });

    expect(total).toBe(60000);
  });

  it('uses the conservative projection income instead of an analysis readiness placeholder', () => {
    const projected = Retirement.computed.projectedIncome.call({
      analysisReady: false,
      analysis: { projected_income: 0 },
      dbPensions: [],
      incomeDrawdown: {
        yearly_income: [{ total_income: 16315.91 }],
      },
    });

    expect(projected).toBe(16315.91);
  });

  it('uses full analysis income when that analysis is available', () => {
    const projected = Retirement.computed.projectedIncome.call({
      analysisReady: true,
      analysis: { projected_income: 18250 },
      dbPensions: [],
      incomeDrawdown: { yearly_income: [{ total_income: 16315.91 }] },
    });

    expect(projected).toBe(18250);
  });

  it('does not use the simplified fallback for a Defined Benefit pension', () => {
    const projected = Retirement.computed.projectedIncome.call({
      analysisReady: false,
      analysis: null,
      dbPensions: [{ id: 1 }],
      incomeDrawdown: { yearly_income: [{ total_income: 20000 }] },
    });

    expect(projected).toBeNull();
  });

  it('does not present a readiness placeholder as zero years to a target the user has not set', () => {
    const years = Retirement.computed.yearsToRetirement.call({
      targetRetirementAge: null,
      analysis: { years_to_retirement: 0 },
      profile: null,
    });

    expect(years).toBeNull();
  });

  it('does not present a missing target as a zero-value target or surplus', () => {
    const target = Retirement.computed.targetIncome.call({ analysis: null });
    const hasTarget = Retirement.computed.hasTargetIncome.call({ targetIncome: target });
    const comparison = Retirement.computed.incomeComparison.call({
      hasTargetIncome: hasTarget,
      incomeGap: null,
      fmt: () => 'unused',
    });

    expect(target).toBeNull();
    expect(hasTarget).toBe(false);
    expect(comparison).toEqual({ label: 'Comparison', value: '—', tone: '' });
  });

  it('identifies a projection age as assumed when the user has not set a target age', () => {
    const label = Retirement.computed.projectionAgeLabel.call({ targetRetirementAge: null });

    expect(label).toBe('an assumed retirement age');
  });

  it('uses the saved retirement-profile target when full analysis is unavailable', () => {
    const target = Retirement.computed.targetIncome.call({
      analysisReady: false,
      analysis: null,
      profile: { target_retirement_income: '28000.00' },
    });

    expect(target).toBe(28000);
  });

  it('uses projection years only when they correspond to the user target age', () => {
    const years = Retirement.computed.yearsToRetirement.call({
      targetRetirementAge: 65,
      pot: { retirement_age: 65, years_to_retirement: 24 },
      analysisReady: false,
      analysis: null,
    });
    const mismatched = Retirement.computed.yearsToRetirement.call({
      targetRetirementAge: 65,
      pot: { retirement_age: 67, years_to_retirement: 26 },
      analysisReady: false,
      analysis: null,
    });

    expect(years).toBe(24);
    expect(mismatched).toBeNull();
  });

  it('discloses the exact assumed current age used by a projection', () => {
    const text = Retirement.computed.currentAgeAssumption.call({
      pot: { current_age_source: 'assumed', current_age: 40 },
    });

    expect(text).toBe('This projection uses an assumed current age of 40.');
  });

  it('registers the same-route refresh watcher before the first load', () => {
    const order = [];
    const context = {
      $watch: vi.fn(() => order.push('watch')),
      load: vi.fn(() => order.push('load')),
    };

    Retirement.created.call(context);

    expect(order).toEqual(['watch', 'load']);
    const refresh = context.$watch.mock.calls[0][1];
    refresh();
    expect(context.load).toHaveBeenCalledTimes(2);
  });

  it('prevents an older overlapping response from replacing the latest retirement data', async () => {
    const deferred = () => {
      let resolve;
      const promise = new Promise((done) => { resolve = done; });
      return { promise, resolve };
    };
    const oldIndex = deferred();
    const oldAnalysis = deferred();
    let indexCalls = 0;
    let analysisCalls = 0;

    apiGet.mockImplementation(async (url) => {
      if (url === '/api/retirement') {
        indexCalls += 1;
        if (indexCalls === 1) return oldIndex.promise;
        return { ok: true, data: { data: { profile: { target_retirement_age: 65 }, dc_pensions: [], db_pensions: [] } } };
      }
      return { ok: true, data: { data: { pension_pot_projection: { retirement_age: 65, years_to_retirement: 24 } } } };
    });
    apiPost.mockImplementation(async () => {
      analysisCalls += 1;
      if (analysisCalls === 1) return oldAnalysis.promise;
      return { ok: true, data: { success: true, data: { projected_income: 22000 } } };
    });

    const wrapper = mount(Retirement, {
      global: {
        stubs: { MobileChrome: { template: '<main><slot /></main>' } },
      },
    });

    await wrapper.vm.load();
    expect(wrapper.vm.profile.target_retirement_age).toBe(65);

    oldIndex.resolve({ ok: true, data: { data: { profile: { target_retirement_age: 67 }, dc_pensions: [], db_pensions: [] } } });
    oldAnalysis.resolve({ ok: true, data: { success: true, data: { projected_income: 99999 } } });
    await flushPromises();

    expect(wrapper.vm.profile.target_retirement_age).toBe(65);
    expect(wrapper.vm.projectedIncome).toBe(22000);
    wrapper.unmount();
  });

  it('renders every retirement figure consistently when Save Tax has not created a retirement profile', async () => {
    apiGet.mockImplementation(async (url) => {
      if (url === '/api/retirement') {
        return {
          ok: true,
          data: {
            data: {
              profile: null,
              dc_pensions: [{
                id: 1,
                scheme_name: 'Aviva Workplace Pension',
                current_fund_value: '47500.00',
              }],
              db_pensions: [],
              state_pension: null,
              account_count: 1,
              account_limit: 5,
            },
          },
        };
      }

      return {
        ok: true,
        data: {
          data: {
            pension_pot_projection: {
              current_value: 47500,
              monthly_contribution: 546.67,
              percentile_20_at_retirement: 347147,
              median_at_retirement: 575866,
              retirement_age: 67,
              years_to_retirement: 26,
              expected_return: 6.5,
            },
            income_drawdown: {
              yearly_income: [{ total_income: 16315.91 }],
            },
            planning_projection: {
              contract_version: 'retirement_projection_v1',
              target_retirement_age: 67,
              planning_total_at_target_age: 16315.91,
              products: [{
                resource_type: 'dc_pension',
                resource_id: 1,
                name: 'Aviva Workplace Pension',
                commencement_age: 67,
                annual_income: 16315.91,
              }],
              age_bands: [{ start_age: 67, end_age: 100, annual_income: 16315.91 }],
              assumptions: {
                sustainable_withdrawal_rate: { percent: 4.7 },
                growth_rate_percent: 6.5,
                net_growth_rate_percent: 6,
                inflation_rate_percent: 2.5,
                fee_rate_percent: 0.5,
                basis: 'nominal',
              },
            },
          },
        },
      };
    });
    apiPost.mockResolvedValue({
      ok: true,
      data: { success: false, message: 'No retirement profile found', data: [] },
    });

    const wrapper = mount(Retirement, {
      global: {
        stubs: {
          MobileChrome: {
            template: '<main><slot /></main>',
          },
        },
      },
    });
    await flushPromises();

    const text = wrapper.text();
    expect(wrapper.find('.m-metric').text()).toContain('£16,316');
    expect(wrapper.find('.mr-pension__value').text()).toBe('£47,500');
    expect(text).toContain('Defined Contribution pension value£47,500');
    expect(text).toContain('Target income—');
    expect(text).toContain('Comparison—');
    expect(text).toContain('Aviva Workplace Pension from age 67£16,316 a year');
    expect(text).toContain('Age 67–100£16,316 a year');
    expect(text).toContain('4.7% sustainable withdrawal rate');
    expect(text).not.toContain('Median projection');

    wrapper.unmount();
  });

  it('renders reconciled planning income bands and disclosed assumptions without a median label', async () => {
    apiGet.mockImplementation(async (url) => {
      if (url === '/api/retirement') {
        return {
          ok: true,
          data: {
            data: {
              profile: { target_retirement_age: 60, target_retirement_income: 30000 },
              dc_pensions: [{ id: 1, scheme_name: 'SIPP', current_fund_value: 200000 }],
              db_pensions: [{ id: 2, scheme_name: 'DB Scheme', accrued_annual_pension: 8000 }],
              state_pension: { id: 3, state_pension_forecast_annual: 11500 },
            },
          },
        };
      }

      return {
        ok: true,
        data: {
          data: {
            pension_pot_projection: {
              current_value: 200000,
              monthly_contribution: 500,
              median_at_retirement: 999999,
            },
            planning_projection: {
              contract_version: 'retirement_projection_v1',
              planning_total_at_target_age: 9400,
              products: [
                { resource_type: 'dc_pension', resource_id: 1, name: 'SIPP', commencement_age: 60, projected_value: 200000, annual_income: 9400 },
                { resource_type: 'db_pension', resource_id: 2, name: 'DB Scheme', commencement_age: 65, projected_value: null, annual_income: 8000 },
                { resource_type: 'state_pension', resource_id: 3, name: 'State Pension', commencement_age: 67, projected_value: null, annual_income: 11500 },
              ],
              age_bands: [
                { start_age: 60, end_age: 64, annual_income: 9400, source_ids: ['dc_pension:1'] },
                { start_age: 65, end_age: 66, annual_income: 17400, source_ids: ['dc_pension:1', 'db_pension:2'] },
                { start_age: 67, end_age: 100, annual_income: 28900, source_ids: ['dc_pension:1', 'db_pension:2', 'state_pension:3'] },
              ],
              assumptions: {
                sustainable_withdrawal_rate: { decimal: 0.047, percent: 4.7, source: 'tax_configuration' },
                growth_rate_percent: 5.5,
                net_growth_rate_percent: 4.7,
                inflation_rate_percent: 2.5,
                fee_rate_percent: 0.8,
                compound_periods: 12,
                basis: 'nominal',
                has_user_overrides: true,
              },
              uncertainty: { method: 'monte_carlo_percentile_bands', primary_projection: false, products: [] },
              warnings: [],
            },
          },
        },
      };
    });
    apiPost.mockResolvedValue({
      ok: true,
      data: { success: true, data: { projected_income: 99999, target_income: 30000 } },
    });

    const wrapper = mount(Retirement, {
      global: {
        stubs: { MobileChrome: { template: '<main><slot /></main>' } },
      },
    });
    await flushPromises();

    expect(wrapper.find('.m-metric').text()).toContain('£9,400');
    expect(wrapper.text()).toContain('SIPP from age 60');
    expect(wrapper.text()).toContain('Age 60–64');
    expect(wrapper.text()).toContain('£9,400 a year');
    expect(wrapper.text()).toContain('4.7% sustainable withdrawal rate');
    expect(wrapper.text()).toContain('5.5% growth');
    expect(wrapper.text()).toContain('0.8% fees');
    expect(wrapper.text()).not.toContain('Median projection');

    wrapper.unmount();
  });
});

describe('mobile Retirement pension detail', () => {
  it('uses the shared planning product and assumptions without presenting a median', async () => {
    apiGet.mockImplementation(async (url) => {
      if (url === '/api/retirement') {
        return {
          ok: true,
          status: 200,
          data: {
            data: {
              dc_pensions: [{
                id: 1,
                scheme_name: 'SIPP',
                provider: 'Example Pensions',
                current_fund_value: 100000,
                monthly_contribution_amount: 500,
                retirement_age: 60,
              }],
              db_pensions: [],
              state_pension: null,
            },
          },
        };
      }

      return {
        ok: true,
        data: {
          data: {
            planning_projection: {
              products: [{
                resource_type: 'dc_pension',
                resource_id: 1,
                name: 'SIPP',
                commencement_age: 60,
                current_value: 100000,
                monthly_contribution: 500,
                projected_value: 236260.18,
                annual_income: 11104.23,
              }],
              assumptions: {
                sustainable_withdrawal_rate: { percent: 4.7 },
                growth_rate_percent: 5.5,
                net_growth_rate_percent: 4.7,
                inflation_rate_percent: 2.5,
                fee_rate_percent: 0.8,
                basis: 'nominal',
              },
            },
          },
        },
      };
    });

    const wrapper = mount(RetirementPensionDetail, {
      global: {
        mocks: {
          $route: { params: { type: 'dc', id: '1' } },
          $router: { push: vi.fn() },
        },
        stubs: {
          MobileChrome: { template: '<main><slot /></main>' },
          CanonicalPortfolio: true,
        },
      },
    });
    await flushPromises();

    expect(wrapper.text()).toContain('Planning value at retirement£236,260');
    expect(wrapper.text()).toContain('Projected income from age 60£11,104 a year');
    expect(wrapper.text()).toContain('4.7% sustainable withdrawal rate');
    expect(wrapper.text()).not.toContain('Median projection');
    expect(apiGet).toHaveBeenCalledWith('/api/retirement/projections', null);

    wrapper.unmount();
  });
});
