import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import { createMemoryHistory, createRouter } from 'vue-router';
import NetWorthOverview from '../../NetWorth/NetWorthOverview.vue';

describe('NetWorthOverview.vue', () => {
  let wrapper;
  let store;
  let router;
  let fetchAssetsSummaryDetailed;

  const assetsSummaryDetailed = {
    pensions: {
      total_value: 120000,
      items: [
        { id: 1, type: 'defined_contribution', name: 'Workplace Pension', value: 90000 },
        { id: 2, type: 'sipp', name: 'Personal Pension', value: 30000 },
      ],
    },
    property: {
      total_value: 500000,
      items: [
        { id: 1, name: 'Main Residence', value: 500000 },
      ],
    },
    investments: {
      total_value: 150000,
      items: [
        { id: 1, name: 'Investment Account', value: 150000 },
      ],
    },
    cash: {
      total_value: 50000,
      items: [
        { id: 1, name: 'Cash ISA', value: 30000, is_isa: true },
        { id: 2, name: 'Emergency Savings', value: 20000, is_emergency_fund: true },
      ],
    },
    business: {
      total_value: 40000,
      items: [
        { id: 1, name: 'Consulting Company', value: 40000, business_type: 'limited_company' },
      ],
    },
    chattels: {
      total_value: 10000,
      items: [
        { id: 1, name: 'Classic Car', value: 10000, chattel_type: 'vehicle' },
      ],
    },
  };

  const mountComponent = () => mount(NetWorthOverview, {
    global: {
      plugins: [store, router],
      mocks: {
        $route: { path: router.currentRoute.value.path },
        $router: router,
      },
    },
  });

  beforeEach(async () => {
    fetchAssetsSummaryDetailed = vi.fn(() => Promise.resolve());

    store = createStore({
      modules: {
        netWorth: {
          namespaced: true,
          state: () => ({
            assetsSummaryDetailed: structuredClone(assetsSummaryDetailed),
            loading: false,
          }),
          actions: {
            fetchAssetsSummaryDetailed,
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

    wrapper = mountComponent();
    await flushPromises();
  });

  it('renders the six asset category cards', () => {
    expect(wrapper.findAll('.asset-card')).toHaveLength(6);
    expect(wrapper.findAll('.card-title').map((title) => title.text())).toEqual([
      'Retirement',
      'Property',
      'Investments',
      'Cash',
      'Business Interest',
      'Personal Valuables',
    ]);
  });

  it('displays category totals as currency', () => {
    const html = wrapper.html();
    expect(html).toContain('£120,000');
    expect(html).toContain('£500,000');
    expect(html).toContain('£150,000');
    expect(html).toContain('£50,000');
  });

  it('displays the detailed asset names and values', () => {
    expect(wrapper.text()).toContain('Workplace Pension');
    expect(wrapper.text()).toContain('Main Residence');
    expect(wrapper.text()).toContain('Investment Account');
    expect(wrapper.text()).toContain('Classic Car');
  });

  it('marks ISA and emergency-fund cash accounts', () => {
    expect(wrapper.find('.badge.isa').text()).toBe('ISA');
    expect(wrapper.find('.badge.emergency').text()).toBe('Emergency');
  });

  it('formats business and personal-valuable types', () => {
    expect(wrapper.find('.business-type').text()).toBe('Ltd');
    expect(wrapper.find('.chattel-type').text()).toBe('Vehicle');
  });

  it('limits each category to three items and reports the remainder', async () => {
    store.state.netWorth.assetsSummaryDetailed.property.items = [
      { id: 1, name: 'Property One', value: 100000 },
      { id: 2, name: 'Property Two', value: 100000 },
      { id: 3, name: 'Property Three', value: 100000 },
      { id: 4, name: 'Property Four', value: 100000 },
    ];
    await wrapper.vm.$nextTick();

    const propertyCard = wrapper.find('.property-card');
    expect(propertyCard.findAll('.item-row')).toHaveLength(3);
    expect(propertyCard.text()).toContain('+1 more');
    expect(propertyCard.text()).not.toContain('Property Four');
  });

  it('calls the detailed summary action on mount', () => {
    expect(fetchAssetsSummaryDetailed).toHaveBeenCalledTimes(1);
  });

  it('shows the loading state while assets are being fetched', async () => {
    store.state.netWorth.loading = true;
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.loading-state').exists()).toBe(true);
    expect(wrapper.text()).toContain('Loading your assets...');
  });

  it('shows category-specific empty states', async () => {
    store.state.netWorth.assetsSummaryDetailed.cash.items = [];
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.cash-card').text()).toContain('No cash accounts recorded');
  });

  it('navigates to the selected net-worth category', async () => {
    await wrapper.find('.property-card').trigger('click');
    await flushPromises();

    expect(router.currentRoute.value.path).toBe('/net-worth/property');
  });

  it('preserves the preview prefix when navigating', async () => {
    await router.push('/preview/dashboard');
    wrapper.vm.$route.path = '/preview/dashboard';
    await wrapper.find('.cash-card').trigger('click');
    await flushPromises();

    expect(router.currentRoute.value.path).toBe('/preview/net-worth/cash');
  });

  it('exposes the same arrays used to render each category', () => {
    expect(wrapper.vm.pensionItems).toHaveLength(2);
    expect(wrapper.vm.propertyItems).toHaveLength(1);
    expect(wrapper.vm.investmentItems).toHaveLength(1);
    expect(wrapper.vm.cashItems).toHaveLength(2);
    expect(wrapper.vm.businessItems).toHaveLength(1);
    expect(wrapper.vm.chattelItems).toHaveLength(1);
  });

  it('falls back to the source type when no display mapping exists', () => {
    expect(wrapper.vm.formatBusinessType('cooperative')).toBe('cooperative');
    expect(wrapper.vm.formatChattelType('memorabilia')).toBe('memorabilia');
  });
});
