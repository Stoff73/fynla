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
  // Every retirement figure on this screen is the server's RetirementHeadline,
  // rendered as sent (CSJ 2026-10-01: one figure, every surface). These tests
  // pin that the screen no longer works any figure out for itself.
  const headline = (overrides = {}) => ({
    kind: 'projected',
    value: 18250,
    projected_income: 18250,
    guaranteed_income: 8000,
    target_income: 30000,
    target_source: 'profile',
    income_gap: 11750,
    progress_percent: 61,
    target_age: 65,
    years_to_retirement: 24,
    dc_value_today: 60000,
    required_capital: 640000,
    ...overrides,
  });

  it('reads the pension value today from the server, not a sum of pensions', () => {
    const total = Retirement.computed.totalPensionWealth.call({
      headline: headline({ dc_value_today: 60000 }),
      dcPensions: [{ current_fund_value: 1 }, { current_fund_value: 2 }],
    });

    expect(total).toBe(60000);
  });

  it('reads the projected income from the server, ignoring analysis and drawdown figures', () => {
    const projected = Retirement.computed.projectedIncome.call({
      headline: headline({ projected_income: 23694 }),
      analysisReady: true,
      analysis: { projected_income: 99999 },
      incomeDrawdown: { yearly_income: [{ total_income: 11111 }] },
    });

    expect(projected).toBe(23694);
  });

  it('leads the hero with the secured income when the server says the household has no pot', () => {
    const hero = Retirement.computed.heroHeadline.call({
      headline: headline({ kind: 'guaranteed', value: 35000 }),
    });

    expect(hero.value).toBe(35000);
    expect(hero.isGuaranteed).toBe(true);
    expect(hero.label).toBe('Guaranteed retirement income');
  });

  it('keeps the projected-income hero when the server says there is a pot', () => {
    const hero = Retirement.computed.heroHeadline.call({ headline: headline() });

    expect(hero.value).toBe(18250);
    expect(hero.isGuaranteed).toBe(false);
    expect(hero.label).toBe('Projected retirement income');
  });

  it('reads the signed gap from the server instead of subtracting', () => {
    expect(Retirement.computed.incomeGap.call({ headline: headline({ income_gap: 11306 }) })).toBe(11306);
    expect(Retirement.computed.incomeGap.call({ headline: headline({ income_gap: -2000 }) })).toBe(-2000);
  });

  it('reads the target and where it came from from the server', () => {
    expect(Retirement.computed.targetIncome.call({ headline: headline({ target_income: 28000 }) })).toBe(28000);
    expect(Retirement.computed.targetIsStated.call({ headline: headline({ target_source: 'calculated' }) })).toBe(false);
    expect(Retirement.computed.targetIsStated.call({ headline: headline({ target_source: 'profile' }) })).toBe(true);
  });

  it('does not present a missing target as a zero-value target or surplus', () => {
    const target = Retirement.computed.targetIncome.call({ headline: headline({ target_income: null, income_gap: null }) });
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

  it('reads years to retirement from the server', () => {
    expect(Retirement.computed.yearsToRetirement.call({ headline: headline({ years_to_retirement: 24 }) })).toBe(24);
    expect(Retirement.computed.yearsToRetirement.call({ headline: null })).toBeNull();
  });

  it('identifies a projection age as assumed when the user has not set a target age', () => {
    const label = Retirement.computed.projectionAgeLabel.call({ targetRetirementAge: null });

    expect(label).toBe('an assumed retirement age');
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
      return { ok: true, data: { data: { pension_pot_projection: { retirement_age: 65, years_to_retirement: 24 }, headline: { kind: 'projected', value: 22000, projected_income: 22000, target_income: null, income_gap: null, years_to_retirement: 24, dc_value_today: 0 } } } };
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
            headline: {
              kind: 'projected',
              value: 16315.91,
              projected_income: 16315.91,
              guaranteed_income: 0,
              target_income: null,
              target_source: null,
              income_gap: null,
              progress_percent: null,
              target_age: 67,
              years_to_retirement: 26,
              dc_value_today: 47500,
            },
            planning_projection: {
              contract_version: 'retirement_projection_v1',
              target_retirement_age: 67,
              planning_total_at_target_age: 16315.91,
              products: [{
                resource_type: 'dc_pension',
                resource_id: 1,
                name: 'Aviva Workplace Pension',
                commencement_age: 67, commencement_age_label: '67',
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
    // The agent no longer refuses to answer without a `retirement_profiles` row —
    // it returns the facts with a null projection (W-0244). Mocking the old
    // `success: false` shape here would have pinned a response the backend can no
    // longer produce, so this is the shape `POST /api/retirement/analyze` really
    // returns for a user with pensions and no target. The nulls matter: the
    // controller must not coerce them to zero, or the hero reads "£0 a year".
    apiPost.mockResolvedValue({
      ok: true,
      data: {
        success: true,
        message: 'Retirement provision found; no retirement target set yet',
        data: {
          projected_income: null,
          target_income: null,
          income_gap: null,
          years_to_retirement: null,
          has_retirement_target: false,
          guaranteed_annual_income: 0,
          total_pension_wealth: 47500,
        },
      },
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

  it('shows someone drawing their pension their income and how long it lasts, not a saver target (TODO item 6)', async () => {
    apiGet.mockImplementation(async (url) => {
      if (url === '/api/retirement') {
        return { ok: true, data: { data: {
          profile: null,
          dc_pensions: [{ id: 278, scheme_name: 'Aviva personal pension', current_fund_value: '200000.00' }],
          db_pensions: [], state_pension: null, account_count: 1, account_limit: 5,
        } } };
      }

      return { ok: true, data: { data: {
        pension_pot_projection: { current_value: 200000, retirement_age: 67, years_to_retirement: 1 },
        income_drawdown: null,
        planning_projection: null,
        drawdown_position: {
          retired_since: { date: '2020-01-01', age: 61 },
          income: {
            lines: [{ key: 'drawdown_278', label: 'Drawdown from Aviva personal pension', amount: 30000 }],
            state_pension_status: 'missing',
            total: 30000, income_tax: 3486, national_insurance: 0, take_home: 26514,
          },
          pot: {
            value: 200000, drawing_per_year: 30000, risk_level: 'lower_medium', risk_level_label: 'Lower-Medium',
            expected_return: 3.5, current_age: 68, end_age: 100,
            lasts_to_age: { middle: 76, lower: 75 },
            lasts_labels: { middle: 'runs out by about age 76', lower: 'runs out by about age 75' },
            life_expectancy: { age: 86, source: 'ons' },
            income_to_last_to_life_expectancy: 13400,
            year_by_year: [],
          },
        },
      } } };
    });
    apiPost.mockResolvedValue({ ok: true, data: { success: true, data: { projected_income: null, target_income: null, years_to_retirement: null, guaranteed_annual_income: 0, total_pension_wealth: 200000 } } });

    const wrapper = mount(Retirement, { global: { stubs: { MobileChrome: { template: '<main><slot /></main>' } } } });
    await flushPromises();
    const text = wrapper.text();

    expect(wrapper.find('[data-testid="retirement-drawing-hero"] .m-metric').text()).toContain('£26,514');
    expect(text).toContain('Retired since January 2020, at 61');
    expect(text).toContain('Drawdown from Aviva personal pension£30,000');
    expect(text).toContain('State PensionAdd it');
    expect(text).toContain('Income Tax£3,486');
    expect(text).toContain('Drawing £30,000 a year from £200,000');
    expect(text).toContain('Middle outcome (half do better)runs out by about age 76');
    expect(text).toContain('Lower outcome (4 in 5 do better)runs out by about age 75');
    expect(text).toContain('These are projections, not guarantees.');
    expect(text).toContain('Life expectancy (Office for National Statistics)86 on average');
    expect(text).toContain('about £13,400 a year');
    expect(text).toContain('Lower-Medium risk level');
    // The saver's view is gone.
    expect(wrapper.find('.mr-target').exists()).toBe(false);
    expect(text).not.toContain('Years to retirement');
    expect(text).not.toContain('Target income');
    expect(text).not.toContain('Retirement income projection');

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
            headline: { kind: 'projected', value: 9400, projected_income: 9400, guaranteed_income: 19500, target_income: 30000, target_source: 'profile', income_gap: 20600, target_age: 60, years_to_retirement: 10, dc_value_today: 200000 },
            planning_projection: {
              contract_version: 'retirement_projection_v1',
              planning_total_at_target_age: 9400,
              products: [
                { resource_type: 'dc_pension', resource_id: 1, name: 'SIPP', commencement_age: 60, commencement_age_label: '60', projected_value: 200000, annual_income: 9400 },
                { resource_type: 'db_pension', resource_id: 2, name: 'DB Scheme', commencement_age: 65, commencement_age_label: '65', projected_value: null, annual_income: 8000 },
                { resource_type: 'state_pension', resource_id: 3, name: 'State Pension', commencement_age: 67, commencement_age_label: '67', projected_value: null, annual_income: 11500 },
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
                commencement_age: 60, commencement_age_label: '60',
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

describe('mobile Retirement State Pension update (TODO item 6)', () => {
  it('opens Fyn on the recorded State Pension the way its detail screen does', () => {
    const openContextualFyn = vi.fn();
    Retirement.methods.addStatePension.call({
      drawing: { income: { state_pension_status: 'not_paid' } },
      statePension: { id: 42 },
      $refs: { chrome: { openContextualFyn } },
    });

    expect(openContextualFyn).toHaveBeenCalledWith(expect.objectContaining({
      action: 'edit',
      resource_type: 'state_pension',
      resource_id: 42,
      current_destination: { screen: 'pension_detail', params: { pension_id: 42, pension_type: 'state' }, fallback: 'retirement' },
    }));
  });
});

describe('mobile Retirement State Pension add (regression walk 2026-10-09, R13)', () => {
  it('opens Fyn on the State Pension form when none is recorded', () => {
    const openContextualFyn = vi.fn();
    Retirement.methods.addStatePension.call({
      drawing: null,
      statePension: null,
      $refs: { chrome: { openContextualFyn } },
    });

    expect(openContextualFyn).toHaveBeenCalledWith(expect.objectContaining({
      action: 'add',
      resource_type: 'state_pension_forecast',
    }));
  });
});
