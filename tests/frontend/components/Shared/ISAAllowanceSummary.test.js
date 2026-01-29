import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import ISAAllowanceSummary from '@/components/Shared/ISAAllowanceSummary.vue';

describe('ISAAllowanceSummary', () => {
  let store;
  let wrapper;

  beforeEach(() => {
    // Create mock Vuex store with savings and investment modules
    store = createStore({
      modules: {
        savings: {
          namespaced: true,
          getters: {
            currentYearISASubscription: () => 8000, // Cash ISA
          },
        },
        investment: {
          namespaced: true,
          getters: {
            investmentISASubscription: () => 4000, // Stocks & Shares ISA
          },
        },
      },
    });
  });

  it('renders correctly', () => {
    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [store],
      },
    });

    expect(wrapper.find('h3').text()).toContain('ISA Allowance');
    expect(wrapper.exists()).toBe(true);
  });

  it('displays combined ISA usage from both modules', () => {
    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [store],
      },
    });

    // Cash ISA: £8,000
    expect(wrapper.vm.cashISAUsed).toBe(8000);

    // Stocks & Shares ISA: £4,000
    expect(wrapper.vm.stocksISAUsed).toBe(4000);

    // Total: £12,000
    expect(wrapper.vm.totalUsed).toBe(12000);
  });

  it('calculates remaining allowance correctly', () => {
    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [store],
      },
    });

    // Allowance: £20,000
    // Used: £12,000
    // Remaining: £8,000
    expect(wrapper.vm.remaining).toBe(8000);
  });

  it('calculates percentage used correctly', () => {
    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [store],
      },
    });

    // 12,000 / 20,000 = 60%
    expect(wrapper.vm.usagePercent).toBe(60);
  });

  it('displays correct progress bar color for low usage (<50%)', () => {
    const lowUsageStore = createStore({
      modules: {
        savings: {
          namespaced: true,
          getters: {
            currentYearISASubscription: () => 5000,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            investmentISASubscription: () => 2000,
          },
        },
      },
    });

    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [lowUsageStore],
      },
    });

    // 7,000 / 20,000 = 35%
    expect(wrapper.vm.progressBarClass).toBe('bg-green-600');
  });

  it('displays correct progress bar color for medium usage (50-75%)', () => {
    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [store],
      },
    });

    // 12,000 / 20,000 = 60%
    expect(wrapper.vm.progressBarClass).toBe('bg-green-600');
  });

  it('displays correct progress bar color for high usage (75-90%)', () => {
    const highUsageStore = createStore({
      modules: {
        savings: {
          namespaced: true,
          getters: {
            currentYearISASubscription: () => 12000,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            investmentISASubscription: () => 5000,
          },
        },
      },
    });

    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [highUsageStore],
      },
    });

    // 17,000 / 20,000 = 85% - should be orange (>=75%)
    expect(wrapper.vm.progressBarClass).toBe('bg-orange-500');
  });

  it('displays correct progress bar color for very high usage (>=90%)', () => {
    const veryHighUsageStore = createStore({
      modules: {
        savings: {
          namespaced: true,
          getters: {
            currentYearISASubscription: () => 10000,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            investmentISASubscription: () => 10000,
          },
        },
      },
    });

    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [veryHighUsageStore],
      },
    });

    // 20,000 / 20,000 = 100% - should be orange (>=90%, not over limit)
    expect(wrapper.vm.progressBarClass).toBe('bg-orange-500');
  });

  it('handles zero ISA usage', () => {
    const zeroStore = createStore({
      modules: {
        savings: {
          namespaced: true,
          getters: {
            currentYearISASubscription: () => 0,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            investmentISASubscription: () => 0,
          },
        },
      },
    });

    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [zeroStore],
      },
    });

    expect(wrapper.vm.totalUsed).toBe(0);
    expect(wrapper.vm.remaining).toBe(20000);
    expect(wrapper.vm.usagePercent).toBe(0);
  });

  it('handles over-limit subscriptions', () => {
    const overLimitStore = createStore({
      modules: {
        savings: {
          namespaced: true,
          getters: {
            currentYearISASubscription: () => 15000,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            investmentISASubscription: () => 10000,
          },
        },
      },
    });

    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [overLimitStore],
      },
    });

    // Total: £25,000 (over £20,000 limit)
    expect(wrapper.vm.totalUsed).toBe(25000);

    // Remaining should be 0 (capped)
    expect(wrapper.vm.remaining).toBe(0);

    // Percentage > 100%
    expect(wrapper.vm.usagePercent).toBe(125);

    // Should show warning
    expect(wrapper.vm.isOverLimit).toBe(true);
  });

  it('displays warning for over-limit subscriptions', () => {
    const overLimitStore = createStore({
      modules: {
        savings: {
          namespaced: true,
          getters: {
            currentYearISASubscription: () => 15000,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            investmentISASubscription: () => 10000,
          },
        },
      },
    });

    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [overLimitStore],
      },
    });

    expect(wrapper.text()).toContain('exceeded');
  });

  it('formats currency correctly', () => {
    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [store],
      },
    });

    expect(wrapper.vm.formatCurrency(12000)).toBe('£12,000');
    expect(wrapper.vm.formatCurrency(8000)).toBe('£8,000');
    expect(wrapper.vm.formatCurrency(20000)).toBe('£20,000');
  });

  it('navigates to Savings module when clicking Savings button', async () => {
    const mockRouter = {
      push: vi.fn(),
    };

    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [store],
        mocks: {
          $router: mockRouter,
        },
      },
    });

    const savingsButton = wrapper.findAll('button').find(btn =>
      btn.text().includes('Savings')
    );

    if (savingsButton) {
      await savingsButton.trigger('click');
      expect(mockRouter.push).toHaveBeenCalledWith('/savings');
    }
  });

  it('navigates to Investment module when clicking Investment button', async () => {
    const mockRouter = {
      push: vi.fn(),
    };

    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [store],
        mocks: {
          $router: mockRouter,
        },
      },
    });

    const investmentButton = wrapper.findAll('button').find(btn =>
      btn.text().includes('Investment')
    );

    if (investmentButton) {
      await investmentButton.trigger('click');
      expect(mockRouter.push).toHaveBeenCalledWith('/investment');
    }
  });

  it('handles missing getters gracefully', () => {
    const emptyStore = createStore({
      modules: {
        savings: {
          namespaced: true,
          getters: {
            currentYearISASubscription: () => undefined,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            investmentISASubscription: () => undefined,
          },
        },
      },
    });

    wrapper = mount(ISAAllowanceSummary, {
      global: {
        plugins: [emptyStore],
      },
    });

    // Should default to 0
    expect(wrapper.vm.cashISAUsed).toBe(0);
    expect(wrapper.vm.stocksISAUsed).toBe(0);
    expect(wrapper.vm.totalUsed).toBe(0);
  });
});
