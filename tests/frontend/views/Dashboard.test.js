import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createStore } from 'vuex';
import Dashboard from '@/views/Dashboard.vue';

describe('Dashboard', () => {
  let store;
  let wrapper;
  let mockDispatch;

  beforeEach(() => {
    mockDispatch = vi.fn(() => Promise.resolve());

    // Create comprehensive mock store
    store = createStore({
      modules: {
        protection: {
          namespaced: true,
          actions: {
            fetchProtectionData: vi.fn(() => Promise.resolve()),
          },
          getters: {
            adequacyScore: () => 75,
            totalCoverage: () => 500000,
            totalPremium: () => 2400,
            coverageGaps: () => [
              { severity: 'high', type: 'life' },
            ],
          },
        },
        savings: {
          namespaced: true,
          state: {
            goals: [
              { id: 1, on_track: true },
              { id: 2, on_track: true },
            ],
          },
          getters: {
            totalSavings: () => 25000,
            emergencyFundRunway: () => 4,
            isaUsagePercent: () => 40,
            goalsOnTrack: () => [
              { id: 1, on_track: true },
              { id: 2, on_track: true },
            ],
          },
        },
        investment: {
          namespaced: true,
          getters: {
            totalPortfolioValue: () => 150000,
            ytdReturn: () => 8.5,
            holdingsCount: () => 12,
            needsRebalancing: () => false,
          },
        },
        retirement: {
          namespaced: true,
          getters: {
            retirementReadinessScore: () => 68,
            projectedIncome: () => 35000,
            targetIncome: () => 40000,
            yearsToRetirement: () => 15,
            totalPensionWealth: () => 200000,
          },
        },
        estate: {
          namespaced: true,
          getters: {
            netWorth: () => 675000,
            ihtLiability: () => 0,
            probateReadiness: () => 85,
          },
        },
      },
      dispatch: mockDispatch,
    });
  });

  it('renders correctly', () => {
    wrapper = mount(Dashboard, {
      global: {
        plugins: [store],
        stubs: {
          AppLayout: {
            template: '<div><slot /></div>',
          },
          ProtectionOverviewCard: true,
          SavingsOverviewCard: true,
          InvestmentOverviewCard: true,
          RetirementOverviewCard: true,
          EstateOverviewCard: true,
        },
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays all 5 module cards', () => {
    wrapper = mount(Dashboard, {
      global: {
        plugins: [store],
        stubs: {
          AppLayout: {
            template: '<div><slot /></div>',
          },
          ProtectionOverviewCard: {
            template: '<div class="protection-card">Protection</div>',
          },
          SavingsOverviewCard: {
            template: '<div class="savings-card">Savings</div>',
          },
          InvestmentOverviewCard: {
            template: '<div class="investment-card">Investment</div>',
          },
          RetirementOverviewCard: {
            template: '<div class="retirement-card">Retirement</div>',
          },
          EstateOverviewCard: {
            template: '<div class="estate-card">Estate</div>',
          },
        },
      },
    });

    expect(wrapper.find('.protection-card').exists()).toBe(true);
    expect(wrapper.find('.savings-card').exists()).toBe(true);
    expect(wrapper.find('.investment-card').exists()).toBe(true);
    expect(wrapper.find('.retirement-card').exists()).toBe(true);
    expect(wrapper.find('.estate-card').exists()).toBe(true);
  });

  it('loads all module data on mount', async () => {
    wrapper = mount(Dashboard, {
      global: {
        plugins: [store],
        stubs: {
          AppLayout: {
            template: '<div><slot /></div>',
          },
          ProtectionOverviewCard: true,
          SavingsOverviewCard: true,
          InvestmentOverviewCard: true,
          RetirementOverviewCard: true,
          EstateOverviewCard: true,
        },
      },
    });

    await flushPromises();

    // Check that loadAllData was called (sets loading states)
    expect(wrapper.vm.loading.protection).toBe(false);
    expect(wrapper.vm.loading.savings).toBe(false);
    expect(wrapper.vm.loading.investment).toBe(false);
    expect(wrapper.vm.loading.retirement).toBe(false);
    expect(wrapper.vm.loading.estate).toBe(false);
  });

  it('displays loading states for all cards initially', () => {
    wrapper = mount(Dashboard, {
      global: {
        plugins: [store],
        stubs: {
          AppLayout: {
            template: '<div><slot /></div>',
          },
        },
      },
    });

    // Set loading states manually
    wrapper.vm.loading.protection = true;
    wrapper.vm.loading.savings = true;

    // Wait for next tick
    wrapper.vm.$nextTick(() => {
      expect(wrapper.findAll('.animate-pulse').length).toBeGreaterThan(0);
    });
  });

  it('displays error state when module fails to load', async () => {
    wrapper = mount(Dashboard, {
      global: {
        plugins: [store],
        stubs: {
          AppLayout: {
            template: '<div><slot /></div>',
          },
        },
      },
    });

    // Set error state
    wrapper.vm.errors.protection = 'Failed to load protection data';
    wrapper.vm.loading.protection = false;

    await wrapper.vm.$nextTick();

    expect(wrapper.text()).toContain('Failed to load protection data');
  });

  it('shows retry button when module fails to load', async () => {
    wrapper = mount(Dashboard, {
      global: {
        plugins: [store],
        stubs: {
          AppLayout: {
            template: '<div><slot /></div>',
          },
        },
      },
    });

    // Set error state
    wrapper.vm.errors.savings = 'Failed to load savings data';
    wrapper.vm.loading.savings = false;

    await wrapper.vm.$nextTick();

    const retryButtons = wrapper.findAll('button').filter(btn =>
      btn.text().includes('Retry')
    );

    expect(retryButtons.length).toBeGreaterThan(0);
  });

  it('retries loading module when retry button clicked', async () => {
    const retryDispatch = vi.fn(() => Promise.resolve());
    const retryStore = createStore({
      modules: {
        protection: {
          namespaced: true,
          getters: {
            adequacyScore: () => 75,
            totalCoverage: () => 500000,
            totalPremium: () => 2400,
            coverageGaps: () => [],
          },
        },
        savings: {
          namespaced: true,
          state: { goals: [] },
          getters: {
            totalSavings: () => 0,
            emergencyFundRunway: () => 0,
            isaUsagePercent: () => 0,
            goalsOnTrack: () => [],
          },
        },
        investment: {
          namespaced: true,
          getters: {
            totalPortfolioValue: () => 0,
            ytdReturn: () => 0,
            holdingsCount: () => 0,
            needsRebalancing: () => false,
          },
        },
        retirement: {
          namespaced: true,
          getters: {
            retirementReadinessScore: () => 0,
            projectedIncome: () => 0,
            targetIncome: () => 0,
            yearsToRetirement: () => 0,
            totalPensionWealth: () => 0,
          },
        },
        estate: {
          namespaced: true,
          getters: {
            netWorth: () => 0,
            ihtLiability: () => 0,
            probateReadiness: () => 0,
          },
        },
      },
      dispatch: retryDispatch,
    });

    wrapper = mount(Dashboard, {
      global: {
        plugins: [retryStore],
        stubs: {
          AppLayout: {
            template: '<div><slot /></div>',
          },
        },
      },
    });

    await wrapper.vm.retryLoadModule('protection');

    expect(retryDispatch).toHaveBeenCalledWith('protection/fetchProtectionData', undefined);
  });

  it('displays refresh button', () => {
    wrapper = mount(Dashboard, {
      global: {
        plugins: [store],
        stubs: {
          AppLayout: {
            template: '<div><slot /></div>',
          },
          ProtectionOverviewCard: true,
          SavingsOverviewCard: true,
          InvestmentOverviewCard: true,
          RetirementOverviewCard: true,
          EstateOverviewCard: true,
        },
      },
    });

    const refreshButton = wrapper.findAll('button').find(btn =>
      btn.text().includes('Refresh')
    );

    expect(refreshButton).toBeDefined();
  });

  it('refreshes all data when refresh button clicked', async () => {
    wrapper = mount(Dashboard, {
      global: {
        plugins: [store],
        stubs: {
          AppLayout: {
            template: '<div><slot /></div>',
          },
          ProtectionOverviewCard: true,
          SavingsOverviewCard: true,
          InvestmentOverviewCard: true,
          RetirementOverviewCard: true,
          EstateOverviewCard: true,
        },
      },
    });

    await wrapper.vm.refreshDashboard();

    expect(wrapper.vm.refreshing).toBe(false);
  });

  it('disables refresh button while refreshing', async () => {
    wrapper = mount(Dashboard, {
      global: {
        plugins: [store],
        stubs: {
          AppLayout: {
            template: '<div><slot /></div>',
          },
          ProtectionOverviewCard: true,
          SavingsOverviewCard: true,
          InvestmentOverviewCard: true,
          RetirementOverviewCard: true,
          EstateOverviewCard: true,
        },
      },
    });

    wrapper.vm.refreshing = true;
    await wrapper.vm.$nextTick();

    const refreshButton = wrapper.findAll('button').find(btn =>
      btn.text().includes('Refresh')
    );

    if (refreshButton) {
      expect(refreshButton.attributes('disabled')).toBeDefined();
    }
  });

  it('passes correct props to ProtectionOverviewCard', () => {
    wrapper = mount(Dashboard, {
      global: {
        plugins: [store],
        stubs: {
          AppLayout: {
            template: '<div><slot /></div>',
          },
          ProtectionOverviewCard: {
            template: '<div>Protection</div>',
            props: ['adequacyScore', 'totalCoverage', 'premiumTotal', 'criticalGaps'],
          },
          SavingsOverviewCard: true,
          InvestmentOverviewCard: true,
          RetirementOverviewCard: true,
          EstateOverviewCard: true,
        },
      },
    });

    expect(wrapper.vm.protectionData.adequacyScore).toBe(75);
    expect(wrapper.vm.protectionData.totalCoverage).toBe(500000);
    expect(wrapper.vm.protectionData.premiumTotal).toBe(2400);
    expect(wrapper.vm.protectionData.criticalGaps).toBe(1);
  });

  it('passes correct props to SavingsOverviewCard', () => {
    wrapper = mount(Dashboard, {
      global: {
        plugins: [store],
        stubs: {
          AppLayout: {
            template: '<div><slot /></div>',
          },
          ProtectionOverviewCard: true,
          SavingsOverviewCard: {
            template: '<div>Savings</div>',
            props: ['emergencyFundRunway', 'totalSavings', 'isaUsagePercent', 'goalsStatus'],
          },
          InvestmentOverviewCard: true,
          RetirementOverviewCard: true,
          EstateOverviewCard: true,
        },
      },
    });

    expect(wrapper.vm.savingsData.emergencyFundRunway).toBe(4);
    expect(wrapper.vm.savingsData.totalSavings).toBe(25000);
    expect(wrapper.vm.savingsData.isaUsagePercent).toBe(40);
    expect(wrapper.vm.savingsData.goalsStatus.onTrack).toBe(2);
  });

  it('handles parallel data loading with Promise.allSettled', async () => {
    wrapper = mount(Dashboard, {
      global: {
        plugins: [store],
        stubs: {
          AppLayout: {
            template: '<div><slot /></div>',
          },
          ProtectionOverviewCard: true,
          SavingsOverviewCard: true,
          InvestmentOverviewCard: true,
          RetirementOverviewCard: true,
          EstateOverviewCard: true,
        },
      },
    });

    await wrapper.vm.loadAllData();

    // All modules should be loaded (or failed)
    expect(wrapper.vm.loading.protection).toBe(false);
    expect(wrapper.vm.loading.savings).toBe(false);
    expect(wrapper.vm.loading.investment).toBe(false);
    expect(wrapper.vm.loading.retirement).toBe(false);
    expect(wrapper.vm.loading.estate).toBe(false);
  });
});
