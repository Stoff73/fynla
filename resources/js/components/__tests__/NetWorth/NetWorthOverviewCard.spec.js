import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import { createMemoryHistory, createRouter } from 'vue-router';
import NetWorthOverviewCard from '../../Dashboard/NetWorthOverviewCard.vue';

describe('NetWorthOverviewCard.vue', () => {
  let wrapper;
  let store;
  let router;
  let fetchOverview;

  const overview = {
    totalAssets: 750000,
    totalLiabilities: 300000,
    netWorth: 450000,
    breakdown: {
      pensions: 50000,
      property: 500000,
      investments: 150000,
      cash: 50000,
      business: 0,
      chattels: 0,
    },
    liabilitiesBreakdown: {
      mortgages: 280000,
      loans: 15000,
      credit_cards: 5000,
      other: 0,
    },
    asOfDate: '2024-10-18',
  };

  beforeEach(async () => {
    fetchOverview = vi.fn(() => Promise.resolve());

    store = createStore({
      modules: {
        netWorth: {
          namespaced: true,
          state: () => ({
            overview: structuredClone(overview),
            loading: false,
            error: null,
          }),
          getters: {
            formattedNetWorth: (state) => `£${state.overview.netWorth.toLocaleString('en-GB')}`,
            netWorth: (state) => state.overview.netWorth,
            totalAssets: (state) => state.overview.totalAssets,
            totalLiabilities: (state) => state.overview.totalLiabilities,
          },
          actions: {
            fetchOverview,
          },
        },
        preview: {
          namespaced: true,
          getters: {
            isPreviewMode: () => false,
          },
        },
      },
    });

    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/:pathMatch(.*)*', component: { template: '<div />' } },
      ],
    });
    await router.push('/dashboard');
    await router.isReady();

    wrapper = mount(NetWorthOverviewCard, {
      global: {
        plugins: [store, router],
        mocks: {
          $route: { path: '/dashboard' },
          $router: router,
        },
      },
    });
  });

  it('renders the total net-worth summary', () => {
    expect(wrapper.find('.value-label').text()).toBe('Total Net Worth');
    expect(wrapper.find('.value-amount').text()).toBe('£450,000');
  });

  it('uses positive styling for a positive net worth', () => {
    expect(wrapper.find('.value-amount').classes()).toContain('positive');
  });

  it('displays the populated asset categories', () => {
    const text = wrapper.text();
    expect(text).toContain('Pensions');
    expect(text).toContain('Property');
    expect(text).toContain('Investments');
    expect(text).toContain('Cash');
    expect(text).not.toContain('Personal Valuables');
  });

  it('displays asset values as currency', () => {
    expect(wrapper.text()).toContain('£500,000');
    expect(wrapper.text()).toContain('£150,000');
  });

  it('displays the populated liability categories', () => {
    const text = wrapper.text();
    expect(text).toContain('Liabilities');
    expect(text).toContain('Mortgages');
    expect(text).toContain('Loans');
    expect(text).toContain('Credit Cards');
    expect(text).not.toContain('Other');
  });

  it('navigates to the wealth summary when clicked', async () => {
    await wrapper.get('[role="button"]').trigger('click');
    await flushPromises();

    expect(router.currentRoute.value.path).toBe('/net-worth/wealth-summary');
  });

  it('supports keyboard navigation to the wealth summary', async () => {
    await wrapper.get('[role="button"]').trigger('keypress', { key: 'Enter' });
    await flushPromises();

    expect(router.currentRoute.value.path).toBe('/net-worth/wealth-summary');
  });

  it('shows a skeleton while the overview is loading', async () => {
    store.state.netWorth.loading = true;
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.loading-skeleton').exists()).toBe(true);
    expect(wrapper.find('.card-content').exists()).toBe(false);
  });

  it('shows a skeleton before the first overview has arrived', async () => {
    store.state.netWorth.overview.asOfDate = null;
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.loading-skeleton').exists()).toBe(true);
  });

  it('displays an error and retries the overview request', async () => {
    store.state.netWorth.error = 'Failed to load net worth';
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.error-message').text()).toBe('Failed to load net worth');
    await wrapper.get('.retry-button').trigger('click');
    expect(fetchOverview).toHaveBeenCalledTimes(1);
  });

  it('derives net worth from the fixture totals', () => {
    expect(store.getters['netWorth/netWorth']).toBe(
      store.getters['netWorth/totalAssets'] - store.getters['netWorth/totalLiabilities']
    );
  });

  it('uses negative styling when liabilities exceed assets', async () => {
    store.state.netWorth.overview.netWorth = -25000;
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.value-amount').text()).toBe('£-25,000');
    expect(wrapper.find('.value-amount').classes()).toContain('negative');
  });
});
