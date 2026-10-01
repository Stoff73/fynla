import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';

/**
 * W-0461 criterion 1 — the guard the family never had.
 *
 * The Rule 2 charitable/rate family was swept twice and declared closed twice,
 * and nine literals stood in the estate frontend the whole time. The reason is
 * structural, not careless: **every existing guard drives PHP and asserts on
 * service output.** `RateLiteralsComeFromConfigurationTest` moves four rates
 * through `TaxConfigService`; `IHTPlanningRateLabel.test.js` calls a computed
 * directly and deliberately does not mount. So re-hardcoding any caption in a
 * Vue template left the entire suite green.
 *
 * This file is the missing shape: it MOVES a configured rate off its statutory
 * value and asserts on the text a mounted template actually RENDERS. A literal
 * put back into any of these captions fails here.
 *
 * The moved values are deliberately not this year's: 31% standard, 29% reduced,
 * £190,000 residence band. Nothing may render 40%, 36%, 10% or £175,000.
 */

const MOVED = {
  standardRate: 0.31,
  reducedRate: 0.29,
  residenceNilRateBand: 190000,
  nilRateBand: 300000,
};

const taxConfigModule = () => ({
  namespaced: true,
  getters: {
    ihtNilRateBand: () => MOVED.nilRateBand,
    ihtResidenceNilRateBand: () => MOVED.residenceNilRateBand,
    ihtRnrbTaperThreshold: () => 2000000,
    ihtStandardRate: () => MOVED.standardRate,
    ihtReducedRate: () => MOVED.reducedRate,
    annualGiftExemption: () => 3000,
  },
});

describe('W-0461 — a moved rate reaches the rendered template', () => {
  // The IHTPlanning strategies card this case rendered could never show in
  // production: it read `ihtData.iht_liability`, which nothing sets, and the
  // case set it by hand. The card was deleted (audit item 36, CSJ 2026-10-01).

  it('EstateCurrentSituation labels the threshold with the configured reduced rate', async () => {
    const EstateCurrentSituation = (await import('@/components/Plans/Estate/EstateCurrentSituation.vue')).default;

    const situation = {
      asset_breakdown: { liquid: 100000, semi_liquid: 50000, illiquid: 900000 },
      charitable_giving: {
        current_percentage: 4,
        threshold: 10,
        shortfall: 0,
        potential_saving: 0,
        basis: null,
      },
    };

    const wrapper = mount(EstateCurrentSituation, {
      props: { situation },
      global: {
        plugins: [createStore({ modules: { taxConfig: taxConfigModule() } })],
        stubs: { PlanSectionHeader: true, IHTCalculationTable: true },
      },
    });

    expect(wrapper.text()).toContain('Threshold for 29% Rate');
    expect(wrapper.text()).not.toContain('Threshold for 36% Rate');
  });

  it('the printed plan draws that label from the same source as the screen', async () => {
    const { planPrintMixin } = await import('@/components/Plans/Shared/planPrintMixin');

    // Criterion 3 — one source, not two mechanisms edited in lockstep. The same
    // panel, drawn by the print mixin, must move with the same configured rate.
    const html = planPrintMixin.methods.buildEstateCurrentSituationHtml.call({
      $store: { getters: { 'taxConfig/ihtReducedRate': MOVED.reducedRate } },
      fmtCurrency: (v) => `£${Number(v || 0).toLocaleString()}`,
      fmtPercentage: (v) => `${Number(v || 0)}%`,
      escapeHtml: (v) => String(v),
    }, {
      iht_summary: {
        current: { gross_assets: 1000000, liabilities: 0, net_estate: 1000000 },
        projected: { gross_assets: 1000000, liabilities: 0, net_estate: 1000000, estimated_age_at_death: 84 },
      },
      charitable_giving: {
        current_percentage: 4,
        threshold: 10,
        shortfall: 0,
        potential_saving: 0,
        basis: null,
      },
    });

    expect(html).toContain('Threshold for 29% Rate');
    expect(html).not.toContain('Threshold for 36% Rate');
  });

  it('TrustPlanningStrategy renders the configured death rate', async () => {
    const TrustPlanningStrategy = (await import('@/components/Estate/TrustPlanningStrategy.vue')).default;

    const wrapper = mount(TrustPlanningStrategy, {
      global: {
        plugins: [createStore({
          modules: {
            taxConfig: taxConfigModule(),
            preview: { namespaced: true, getters: { isPreviewMode: () => false } },
          },
        })],
      },
    });

    // The panel is behind the loaded state; the fetch is irrelevant to what the
    // caption reads, so the loaded state is set directly.
    await wrapper.setData({
      loadingTrustStrategy: false,
      trustStrategy: {
        strategies: [],
        liquidity_analysis: { summary: { total_value: 1000000 } },
        giftable_amounts: {
          immediately_giftable: 100000,
          giftable_with_planning: 50000,
          not_giftable: 850000,
          liquid_asset_count: 1,
          semi_liquid_asset_count: 1,
          illiquid_asset_count: 1,
        },
        strategy_impact: {
          total_amount_transferred: 100000,
          total_iht_saving: 40000,
          total_lifetime_charges: 0,
          net_saving: 40000,
          worst_case_cost: 0,
          worst_case_net_saving: 0,
        },
        summary: { recommended_strategy: 'Discretionary trust', effectiveness_rating: 'High', net_benefit: 40000 },
      },
    });

    expect(wrapper.text()).toContain('31% death rate');
    expect(wrapper.text()).not.toContain('40% death rate');
  });
});
