import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import ISAAllowanceSummary from '@/components/Shared/ISAAllowanceSummary.vue';

const createISAStore = (cash = 8000, stocks = 4000, allowance = 20000) => createStore({
  modules: {
    savings: {
      namespaced: true,
      getters: { currentYearISASubscription: () => cash },
    },
    investment: {
      namespaced: true,
      getters: { investmentISASubscription: () => stocks },
    },
    taxConfig: {
      namespaced: true,
      getters: { isaAnnualAllowance: () => allowance },
    },
  },
});

describe('ISAAllowanceSummary', () => {
  let router;

  const mountSummary = (cash, stocks, allowance) => mount(ISAAllowanceSummary, {
    global: {
      plugins: [createISAStore(cash, stocks, allowance)],
      mocks: { $router: router },
    },
  });

  beforeEach(() => {
    router = { push: vi.fn() };
  });

  it('renders the shared ISA allowance summary', () => {
    const wrapper = mountSummary();
    expect(wrapper.get('h3').text()).toContain('ISA Allowance');
  });

  it('combines cash and Stocks & Shares ISA subscriptions', () => {
    const wrapper = mountSummary();
    expect(wrapper.vm.cashISAUsed).toBe(8000);
    expect(wrapper.vm.stocksISAUsed).toBe(4000);
    expect(wrapper.vm.totalUsed).toBe(12000);
  });

  it('calculates the remaining configured allowance', () => {
    expect(mountSummary().vm.remaining).toBe(8000);
  });

  it('calculates percentage used', () => {
    expect(mountSummary().vm.usagePercent).toBe(60);
  });

  it('uses the success palette below half of the allowance', () => {
    expect(mountSummary(5000, 2000).vm.progressBarClass).toBe('bg-spring-600');
  });

  it('keeps the success palette for medium usage', () => {
    expect(mountSummary().vm.progressBarClass).toBe('bg-spring-600');
  });

  it('uses the warning palette from seventy-five percent', () => {
    expect(mountSummary(12000, 5000).vm.progressBarClass).toBe('bg-violet-500');
  });

  it('uses the warning palette at the full allowance', () => {
    expect(mountSummary(10000, 10000).vm.progressBarClass).toBe('bg-violet-500');
  });

  it('handles zero ISA usage', () => {
    const wrapper = mountSummary(0, 0);
    expect(wrapper.vm.totalUsed).toBe(0);
    expect(wrapper.vm.remaining).toBe(20000);
    expect(wrapper.vm.usagePercent).toBe(0);
  });

  it('caps remaining allowance at zero when subscriptions exceed it', () => {
    const wrapper = mountSummary(15000, 10000);
    expect(wrapper.vm.totalUsed).toBe(25000);
    expect(wrapper.vm.remaining).toBe(0);
    expect(wrapper.vm.usagePercent).toBe(125);
    expect(wrapper.vm.isOverLimit).toBe(true);
  });

  it('renders the over-limit warning', () => {
    expect(mountSummary(15000, 10000).text()).toContain('ISA allowance exceeded');
  });

  it('formats currency consistently', () => {
    const wrapper = mountSummary();
    expect(wrapper.vm.formatCurrency(12000)).toBe('£12,000');
    expect(wrapper.vm.formatCurrency(8000)).toBe('£8,000');
    expect(wrapper.vm.formatCurrency(20000)).toBe('£20,000');
  });

  it('navigates to Savings management', () => {
    const wrapper = mountSummary();
    wrapper.vm.navigateToSavings();
    expect(router.push).toHaveBeenCalledWith('/savings');
  });

  it('navigates to Investment management', () => {
    const wrapper = mountSummary();
    wrapper.vm.navigateToInvestment();
    expect(router.push).toHaveBeenCalledWith('/investment');
  });

  it('uses the configured allowance rather than a test hardcode', () => {
    const wrapper = mountSummary(2000, 1000, 24000);
    expect(wrapper.vm.remaining).toBe(21000);
    expect(wrapper.vm.formattedAllowance).toBe('£24,000');
  });
});
