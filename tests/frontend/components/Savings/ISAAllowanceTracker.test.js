import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import ISAAllowanceTracker from '@/components/Savings/ISAAllowanceTracker.vue';

const createMockStore = ({ cash = 0, stocks = 0, allowance = 20000, user = null, accounts = [], property = 0 } = {}) => createStore({
  modules: {
    savings: {
      namespaced: true,
      state: () => ({
        isaAllowance: {
          cash_isa_used: cash,
          stocks_shares_isa_used: stocks,
          total_allowance: allowance,
        },
      }),
      getters: { isaAllowanceRemaining: () => Math.max(0, allowance - cash - stocks) },
    },
    auth: {
      namespaced: true,
      getters: { currentUser: () => user },
    },
    netWorth: {
      namespaced: true,
      state: () => ({ overview: { breakdown: { property } } }),
    },
    investment: {
      namespaced: true,
      state: () => ({ accounts }),
    },
    taxConfig: {
      namespaced: true,
      getters: {
        isaAnnualAllowance: () => allowance,
        lifetimeIsaAllowance: () => 4000,
      },
    },
  },
});

const mountTracker = (values) => mount(ISAAllowanceTracker, {
  global: { plugins: [createMockStore(values)] },
});

describe('ISAAllowanceTracker', () => {
  it('renders from the savings store', () => {
    expect(mountTracker().exists()).toBe(true);
  });

  it('displays the configured overall allowance', () => {
    expect(mountTracker().text()).toContain('£20,000 total');
  });

  it('displays cash ISA usage from the store', () => {
    const wrapper = mountTracker({ cash: 8000, stocks: 4000 });
    expect(wrapper.vm.cashISAUsed).toBe(8000);
    expect(wrapper.text()).toContain('£8,000');
  });

  it('displays Stocks & Shares ISA usage from the store', () => {
    const wrapper = mountTracker({ cash: 5000, stocks: 7500 });
    expect(wrapper.vm.stocksISAUsed).toBe(7500);
    expect(wrapper.text()).toContain('£7,500');
  });

  it('calculates the remaining allowance', () => {
    expect(mountTracker({ cash: 8000, stocks: 5000 }).vm.remaining).toBe(7000);
  });

  it('calculates combined usage from the two progress segments', () => {
    const wrapper = mountTracker({ cash: 10000, stocks: 5000 });
    expect(wrapper.vm.cashISAPercent + wrapper.vm.stocksISAPercent).toBe(75);
  });

  it('handles zero subscriptions', () => {
    const wrapper = mountTracker();
    expect(wrapper.vm.cashISAPercent + wrapper.vm.stocksISAPercent).toBe(0);
    expect(wrapper.vm.remaining).toBe(20000);
  });

  it('handles a fully used allowance', () => {
    const wrapper = mountTracker({ cash: 12000, stocks: 8000 });
    expect(wrapper.vm.cashISAPercent + wrapper.vm.stocksISAPercent).toBe(100);
    expect(wrapper.vm.remaining).toBe(0);
  });

  it('derives the active tax year', () => {
    expect(mountTracker().text()).toContain('2026/27');
  });

  it('renders separate progress segments for both ISA types', () => {
    const wrapper = mountTracker({ cash: 8000, stocks: 4000 });
    const widths = wrapper.findAll('[style]').map(node => node.attributes('style'));
    expect(widths).toContain('width: 40%;');
    expect(widths).toContain('width: 20%;');
  });

  it('shows the remaining amount when the allowance is nearly exhausted', () => {
    const wrapper = mountTracker({ cash: 15000, stocks: 4000 });
    expect(wrapper.vm.remaining).toBe(1000);
    expect(wrapper.text()).toContain('£1,000');
  });

  it('uses current palette classes for subscription segments', () => {
    const wrapper = mountTracker({ cash: 3000, stocks: 2000 });
    expect(wrapper.findAll('.bg-violet-500')).toHaveLength(2);
  });

  it('caps remaining allowance when subscriptions exceed the limit', () => {
    const wrapper = mountTracker({ cash: 15000, stocks: 10000 });
    expect(wrapper.vm.remaining).toBe(0);
    expect(wrapper.vm.cashISAPercent + wrapper.vm.stocksISAPercent).toBe(125);
  });

  it('displays the Cash and Stocks & Shares ISA breakdown', () => {
    const text = mountTracker({ cash: 12000, stocks: 6000 }).text();
    expect(text).toContain('Cash ISA');
    expect(text).toContain('Stocks & Shares ISA');
    expect(text).toContain('£12,000');
    expect(text).toContain('£6,000');
  });
});
