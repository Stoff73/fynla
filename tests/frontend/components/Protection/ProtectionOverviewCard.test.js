import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createStore } from 'vuex';
import ProtectionOverviewCard from '@/components/Protection/ProtectionOverviewCard.vue';
import userProfileService from '@/services/userProfileService';

vi.mock('@/services/userProfileService', () => ({
  default: { getProfile: vi.fn() },
}));

describe('ProtectionOverviewCard', () => {
  const lifePolicies = [{
    id: 1,
    provider: 'Life Provider',
    policy_type: 'level_term',
    sum_assured: 200000,
    premium_amount: 50,
  }];

  const criticalIllnessPolicies = [{
    id: 2,
    provider: 'Critical Illness Provider',
    policy_type: 'standalone',
    sum_assured: 100000,
    premium_amount: 30,
  }];

  const sicknessIllnessPolicies = [{
    id: 3,
    provider: 'Sickness Provider',
    benefit_amount: 1000,
    benefit_frequency: 'monthly',
    premium_amount: 20,
  }];

  const disabilityPolicies = [{
    id: 4,
    provider: 'Disability Provider',
    benefit_amount: 2000,
    benefit_frequency: 'monthly',
    premium_amount: 25,
  }];

  const createStoreForCard = () => createStore({
    modules: {
      taxConfig: {
        namespaced: true,
        getters: { sspWeeklyRate: () => 120 },
      },
    },
  });

  const mountCard = (props = {}, push = vi.fn()) => mount(ProtectionOverviewCard, {
    props,
    global: {
      plugins: [createStoreForCard()],
      mocks: { $router: { push } },
    },
  });

  beforeEach(() => {
    vi.clearAllMocks();
    userProfileService.getProfile.mockResolvedValue({
      data: {
        liabilities_summary: {
          mortgages: { total: 300000 },
          other: { total: 50000 },
        },
        income_occupation: {
          annual_employment_income: 60000,
          annual_self_employment_income: 20000,
        },
      },
    });
  });

  it('renders policy cover and monthly premiums without an adequacy score', async () => {
    const wrapper = mountCard({ lifePolicies, criticalIllnessPolicies });
    await flushPromises();

    expect(wrapper.text()).toContain('Life Insurance');
    expect(wrapper.text()).toContain('Life Provider');
    expect(wrapper.text()).toContain('Cover: £200,000');
    expect(wrapper.text()).toContain('£50/mo');
    expect(wrapper.text()).not.toMatch(/\/100/);
  });

  it('calculates debt protection from debt and existing life cover', async () => {
    const wrapper = mountCard({ lifePolicies });
    await flushPromises();

    expect(wrapper.vm.totalDebt).toBe(350000);
    expect(wrapper.vm.totalLifeCoverage).toBe(200000);
    expect(wrapper.vm.debtProtectionGap).toBe(150000);
    expect(wrapper.text()).toContain('Debt Protection');
    expect(wrapper.text()).toContain('£150,000 shortfall');
  });

  it('calculates income replacement as a specific annual shortfall', async () => {
    const wrapper = mountCard({ lifePolicies });
    await flushPromises();

    expect(wrapper.vm.incomeReplacementNeed).toBe(60000);
    expect(wrapper.vm.incomeReplacementGap).toBe(60000);
    expect(wrapper.text()).toContain('£60,000/yr shortfall');
  });

  it('calculates critical illness need against existing cover', async () => {
    const wrapper = mountCard({ criticalIllnessPolicies });
    await flushPromises();

    expect(wrapper.vm.criticalIllnessNeed).toBe(160000);
    expect(wrapper.vm.criticalIllnessCoverage).toBe(100000);
    expect(wrapper.vm.criticalIllnessGap).toBe(60000);
    expect(wrapper.text()).toContain('Critical Illness');
    expect(wrapper.text()).toContain('£60,000 shortfall');
  });

  it('states when there is no critical illness policy', async () => {
    userProfileService.getProfile.mockResolvedValueOnce({
      data: {
        liabilities_summary: {},
        income_occupation: {},
      },
    });
    const wrapper = mountCard();
    await flushPromises();

    expect(wrapper.text()).toContain('Critical Illness');
    expect(wrapper.text()).toContain('No cover');
  });

  it('includes statutory and private sickness cover for an employee', async () => {
    const wrapper = mountCard({ sicknessIllnessPolicies });
    await flushPromises();

    expect(wrapper.vm.sspAnnualEquivalent).toBe(6240);
    expect(wrapper.vm.privateSicknessCover).toBe(12000);
    expect(wrapper.vm.totalSicknessCover).toBe(18240);
    expect(wrapper.vm.sicknessGap).toBe(21760);
  });

  it('calculates disability cover from the policy payment frequency', async () => {
    const wrapper = mountCard({ disabilityPolicies });
    await flushPromises();

    expect(wrapper.vm.disabilityCover).toBe(24000);
    expect(wrapper.vm.disabilityNeed).toBe(40000);
    expect(wrapper.vm.disabilityGap).toBe(16000);
  });

  it('renders the critical-gap count supplied by the parent', async () => {
    const wrapper = mountCard({ criticalGaps: 3 });
    await flushPromises();

    expect(wrapper.text()).toContain('3 critical gaps identified');
  });

  it('formats large policy cover values as currency', async () => {
    const wrapper = mountCard({
      lifePolicies: [{ ...lifePolicies[0], sum_assured: 1234567 }],
    });
    await flushPromises();

    expect(wrapper.text()).toContain('£1,234,567');
  });

  it('navigates to the Protection dashboard when selected', async () => {
    const push = vi.fn();
    const wrapper = mountCard({}, push);
    await flushPromises();

    await wrapper.trigger('click');

    expect(push).toHaveBeenCalledWith('/protection');
  });
});
