import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import AccountStrategyCard from '@/components/Investment/AccountStrategyCard.vue';

describe('AccountStrategyCard', () => {
  const baseAccount = {
    id: 1,
    account_type: 'isa',
    current_value: 25000,
    contributions_ytd: 5000,
    isa_subscription_current_year: 5000,
    platform_fee_type: 'percentage',
    platform_fee_percent: 0.15,
    holdings: [
      { id: 1, fund_name: 'Global Equity', asset_type: 'equity', current_value: 10000, ocf_percent: 0.15 },
      { id: 2, fund_name: 'Bond Fund', asset_type: 'bonds', current_value: 8000, ocf_percent: 0.1 },
      { id: 3, fund_name: 'Property Fund', asset_type: 'property', current_value: 7000, ocf_percent: 0.2 },
    ],
  };

  const mountCard = ({ account = baseAccount, hasRiskProfile = true, rebalancingData = null } = {}) => {
    const store = createStore({
      modules: {
        investment: {
          namespaced: true,
          getters: { hasRiskProfile: () => hasRiskProfile },
        },
        taxConfig: {
          namespaced: true,
          getters: { isaAnnualAllowance: () => 20000 },
        },
      },
    });

    return mount(AccountStrategyCard, {
      props: { account, rebalancingData },
      global: { plugins: [store] },
    });
  };

  it('renders the live strategies card', () => {
    const wrapper = mountCard();

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain('Strategies');
  });

  it('recommends adding holdings when an account is empty', () => {
    const wrapper = mountCard({
      account: { ...baseAccount, holdings: [] },
    });

    expect(wrapper.text()).toContain('Add Your Holdings');
  });

  it('recommends completing the risk profile when it is missing', () => {
    const wrapper = mountCard({ hasRiskProfile: false });

    expect(wrapper.text()).toContain('Set Risk Profile');
  });

  it('calculates percentage platform fees with weighted holding charges', () => {
    const wrapper = mountCard({
      account: {
        ...baseAccount,
        platform_fee_percent: 0.6,
        holdings: baseAccount.holdings.map(holding => ({ ...holding, ocf_percent: 0.3 })),
      },
    });

    const recommendation = wrapper.vm.accountRecommendations.find(rec => rec.title === 'Review Account Fees');
    expect(recommendation.description).toContain('0.90%');
    expect(recommendation.description).toContain('£225');
  });

  it('annualises a monthly fixed platform fee', () => {
    const wrapper = mountCard({
      account: {
        ...baseAccount,
        current_value: 10000,
        platform_fee_type: 'fixed',
        platform_fee_amount: 10,
        platform_fee_frequency: 'monthly',
        holdings: [],
      },
    });

    expect(wrapper.text()).toContain('Review Account Fees');
    expect(wrapper.text()).toContain('£120');
  });

  it('identifies a concentrated holding using specific allocation data', () => {
    const wrapper = mountCard({
      account: {
        ...baseAccount,
        holdings: [
          { fund_name: 'Global Equity', asset_type: 'equity', current_value: 18000, ocf_percent: 0.1 },
          { fund_name: 'Bond Fund', asset_type: 'bonds', current_value: 7000, ocf_percent: 0.1 },
        ],
      },
    });

    expect(wrapper.text()).toContain('High Concentration');
    expect(wrapper.text()).toContain('72%');
  });

  it('identifies limited asset-class diversification', () => {
    const wrapper = mountCard({
      account: {
        ...baseAccount,
        holdings: [
          { fund_name: 'Fund One', asset_type: 'equity', current_value: 12000, ocf_percent: 0.1 },
          { fund_name: 'Fund Two', asset_type: 'equity', current_value: 13000, ocf_percent: 0.1 },
        ],
      },
    });

    expect(wrapper.text()).toContain('Limited Diversification');
    expect(wrapper.text()).toContain('Equity');
  });

  it('identifies individual holdings with high fees', () => {
    const wrapper = mountCard({
      account: {
        ...baseAccount,
        holdings: baseAccount.holdings.map((holding, index) => ({
          ...holding,
          ocf_percent: index === 0 ? 0.9 : 0.1,
        })),
      },
    });

    expect(wrapper.text()).toContain('High-Fee Holdings');
    expect(wrapper.text()).toContain('1 holding(s)');
  });

  it('uses the configured ISA allowance when calculating the remaining amount', () => {
    const wrapper = mountCard();

    expect(wrapper.text()).toContain('ISA Allowance Available');
    expect(wrapper.text()).toContain('£15,000');
  });

  it('recommends rebalancing when allocation drift exceeds the threshold', () => {
    const wrapper = mountCard({
      rebalancingData: {
        drift_analysis: { needs_rebalancing: true, drift_score: 8.2 },
      },
    });

    expect(wrapper.text()).toContain('Rebalancing Needed');
    expect(wrapper.text()).toContain('8.2%');
  });

  it('limits the visible recommendations to three', () => {
    const wrapper = mountCard({
      account: {
        ...baseAccount,
        platform_fee_percent: 1,
        holdings: [],
      },
      hasRiskProfile: false,
    });

    expect(wrapper.findAll('.strategy-card')).toHaveLength(3);
  });

  it('emits add-holding for the empty-account recommendation', async () => {
    const wrapper = mountCard({ account: { ...baseAccount, holdings: [] } });

    await wrapper.find('.strategy-card').trigger('click');

    expect(wrapper.emitted('add-holding')).toEqual([[]]);
  });

  it('emits the requested tab for an actionable recommendation', async () => {
    const wrapper = mountCard({
      account: { ...baseAccount, platform_fee_percent: 1 },
    });
    const feeCard = wrapper.findAll('.strategy-card').find(card => card.text().includes('Review Account Fees'));

    await feeCard.trigger('click');

    expect(wrapper.emitted('change-tab')).toContainEqual(['fees']);
  });

  it('does not emit navigation for informational recommendations', async () => {
    const wrapper = mountCard();
    const allowanceCard = wrapper.findAll('.strategy-card').find(card => card.text().includes('ISA Allowance Available'));

    await allowanceCard.trigger('click');

    expect(wrapper.emitted('change-tab')).toBeUndefined();
  });

  it('uses the canonical palette for recommendation priority badges', () => {
    const wrapper = mountCard();

    expect(wrapper.vm.getPriorityBadgeClass(1)).toContain('bg-raspberry-500');
    expect(wrapper.vm.getPriorityBadgeClass(3)).toContain('bg-violet-500');
    expect(wrapper.vm.getPriorityBadgeClass(4)).toContain('bg-savannah-300');
  });

  it('shows the positive empty state when no recommendations apply', () => {
    const wrapper = mountCard({
      account: {
        ...baseAccount,
        account_type: 'nsi',
        isa_subscription_current_year: null,
      },
    });

    expect(wrapper.text()).toContain('Looking Good');
    expect(wrapper.text()).toContain('No recommendations for this account');
  });
});
