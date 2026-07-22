import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import PersonalAccounts from '../../UserProfile/PersonalAccounts.vue';

describe('PersonalAccounts.vue', () => {
  let wrapper;
  let store;
  let calculatePersonalAccounts;

  const personalAccounts = {
    profitAndLoss: {
      totalIncome: 87000,
      totalExpenses: 18000,
      netProfitLoss: 69000,
    },
    cashflow: {
      totalInflows: 75000,
      totalOutflows: 18000,
      netCashflow: 57000,
    },
    balanceSheet: {
      totalAssets: 550000,
      totalLiabilities: 300000,
      totalEquity: 250000,
    },
  };

  const spouseAccounts = {
    profitAndLoss: { totalIncome: 42000 },
    cashflow: { totalInflows: 42000 },
    balanceSheet: { totalAssets: 100000 },
  };

  beforeEach(async () => {
    window.localStorage.clear();
    calculatePersonalAccounts = vi.fn(() => Promise.resolve());

    store = createStore({
      modules: {
        userProfile: {
          namespaced: true,
          state: () => ({
            personalAccounts,
            spouseAccounts,
            loading: false,
          }),
          getters: {
            personalAccounts: (state) => state.personalAccounts,
            spouseAccounts: (state) => state.spouseAccounts,
            loading: (state) => state.loading,
          },
          actions: {
            calculatePersonalAccounts,
          },
        },
      },
    });

    wrapper = mount(PersonalAccounts, {
      global: {
        plugins: [store],
        stubs: {
          BalanceSheetView: {
            name: 'BalanceSheetView',
            props: ['data', 'spouseData'],
            template: '<div class="balance-sheet-stub">Balance sheet</div>',
          },
          CashflowView: {
            name: 'CashflowView',
            props: ['data', 'spouseData'],
            template: '<div class="cashflow-stub">Cashflow statement</div>',
          },
          ProfitAndLossView: {
            name: 'ProfitAndLossView',
            props: ['data', 'spouseData'],
            template: '<div class="profit-loss-stub">Profit and loss statement</div>',
          },
        },
      },
    });
    await flushPromises();
  });

  it('renders the personal accounts heading and description', () => {
    expect(wrapper.get('h2').text()).toBe('Personal Accounts');
    expect(wrapper.text()).toContain('Auto-calculated financial statements');
  });

  it('renders the current balance-sheet, cashflow, and profit-and-loss tabs', () => {
    const tabs = wrapper.findAll('nav[aria-label="Tabs"] button');
    expect(tabs.map((tab) => tab.text())).toEqual([
      'Balance Sheet',
      'Cashflow',
      'Profit & Loss',
    ]);
  });

  it('shows the balance sheet by default', () => {
    expect(wrapper.vm.activeTab).toBe('balance_sheet');
    expect(wrapper.find('.balance-sheet-stub').isVisible()).toBe(true);
    expect(wrapper.find('.cashflow-stub').isVisible()).toBe(false);
    expect(wrapper.find('.profit-loss-stub').isVisible()).toBe(false);
  });

  it('switches to the cashflow statement', async () => {
    const tabs = wrapper.findAll('nav[aria-label="Tabs"] button');
    await tabs[1].trigger('click');

    expect(wrapper.vm.activeTab).toBe('cashflow');
    expect(wrapper.find('.cashflow-stub').isVisible()).toBe(true);
  });

  it('switches to the profit-and-loss statement', async () => {
    const tabs = wrapper.findAll('nav[aria-label="Tabs"] button');
    await tabs[2].trigger('click');

    expect(wrapper.vm.activeTab).toBe('profit_loss');
    expect(wrapper.find('.profit-loss-stub').isVisible()).toBe(true);
  });

  it('renders start, end, and balance-sheet dates', () => {
    expect(wrapper.get('#start_date').attributes('type')).toBe('date');
    expect(wrapper.get('#end_date').attributes('type')).toBe('date');
    expect(wrapper.get('#as_of_date').attributes('type')).toBe('date');
    expect(wrapper.get('#start_date').element.value).toMatch(/^\d{4}-04-06$/);
    expect(wrapper.get('#end_date').element.value).toMatch(/^\d{4}-04-05$/);
  });

  it('auto-calculates the accounts on mount', () => {
    expect(calculatePersonalAccounts).toHaveBeenCalledTimes(1);
    expect(calculatePersonalAccounts.mock.calls[0][1]).toEqual(wrapper.vm.period);
  });

  it('recalculates when a period date changes', async () => {
    await wrapper.get('#start_date').setValue('2025-04-06');
    await flushPromises();

    expect(calculatePersonalAccounts).toHaveBeenCalledTimes(2);
    expect(calculatePersonalAccounts.mock.calls[1][1]).toMatchObject({
      start_date: '2025-04-06',
    });
  });

  it('passes the balance-sheet data for both account holders', () => {
    const view = wrapper.findComponent({ name: 'BalanceSheetView' });
    expect(view.props('data')).toEqual(personalAccounts.balanceSheet);
    expect(view.props('spouseData')).toEqual(spouseAccounts.balanceSheet);
  });

  it('passes the cashflow data for both account holders', () => {
    const view = wrapper.findComponent({ name: 'CashflowView' });
    expect(view.props('data')).toEqual(personalAccounts.cashflow);
    expect(view.props('spouseData')).toEqual(spouseAccounts.cashflow);
  });

  it('passes the profit-and-loss data for both account holders', () => {
    const view = wrapper.findComponent({ name: 'ProfitAndLossView' });
    expect(view.props('data')).toEqual(personalAccounts.profitAndLoss);
    expect(view.props('spouseData')).toEqual(spouseAccounts.profitAndLoss);
  });

  it('shows the calculating state while data is loading', async () => {
    store.state.userProfile.loading = true;
    await wrapper.vm.$nextTick();

    expect(wrapper.text()).toContain('Calculating accounts...');
    expect(wrapper.find('.balance-sheet-stub').exists()).toBe(false);
  });
});
