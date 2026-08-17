import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({
  apiGet: vi.fn(),
}));

vi.mock('../../navigation/webHandoff.js', () => ({
  issueWebHandoff: vi.fn(),
}));

import { apiGet } from '../../api.js';
import { upgradeMixin } from '../../mixins/upgrade.js';
import { issueWebHandoff } from '../../navigation/webHandoff.js';
import { store } from '../../store.js';
import Subscription from '../Subscription.vue';

const tiers = [
  {
    tier: 'free',
    display_name: 'Free',
    price_monthly_pence: 0,
    price_annual_pence: 0,
    features: [
      { key: 'dashboard', label: 'Financial dashboard', included: true, availability: 'full' },
      { key: 'savings_account', label: 'Up to 2 bank accounts', included: true, availability: 'limited' },
      { key: 'estate', label: 'Estate planning — preview only', included: true, availability: 'teaser' },
    ],
  },
  {
    tier: 'premium',
    display_name: 'Premium',
    price_monthly_pence: 999,
    price_annual_pence: 9990,
    features: [
      { key: 'dashboard', label: 'Financial dashboard', included: true, availability: 'full' },
      { key: 'savings_account', label: 'Unlimited bank accounts', included: true, availability: 'full' },
      { key: 'estate', label: 'Estate planning', included: true, availability: 'full' },
    ],
  },
];

const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel'],
  template: '<main><h1>{{ title }}</h1><slot /></main>',
};

function mockEndpoints({ paymentEnabled = true } = {}) {
  apiGet.mockImplementation((path) => {
    if (path === '/api/payment/subscription-status') {
      return Promise.resolve({
        ok: true,
        status: 200,
        data: {
          tier: 'free',
          tier_display_name: 'Free',
          payment_enabled: paymentEnabled,
          has_subscription: false,
        },
      });
    }
    if (path === '/api/pricing-config') {
      return Promise.resolve({ ok: true, status: 200, data: { data: tiers } });
    }
    return Promise.resolve({ ok: false, status: 404, data: {} });
  });
}

function mountView() {
  return mount(Subscription, {
    global: {
      mocks: { $router: { push: vi.fn() }, $route: { path: '/subscription', query: {} } },
      stubs: { MobileChrome: MobileChromeStub },
    },
  });
}

describe('Subscription.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.subscriptionStatus = null;
    issueWebHandoff.mockResolvedValue();
    mockEndpoints();
  });

  it('renders the current plan and its server-owned feature comparison', async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(apiGet).toHaveBeenCalledWith('/api/payment/subscription-status', 'live-token');
    expect(apiGet).toHaveBeenCalledWith('/api/pricing-config', 'live-token');
    expect(wrapper.text()).toContain('Your current plan');
    expect(wrapper.text()).toContain('Free');
    expect(wrapper.text()).toContain('Financial dashboard');
    expect(wrapper.text()).toContain('Up to 2 bank accounts');
    expect(wrapper.text()).toContain('Estate planning — preview only');
  });

  it('uses a single-use web handoff without copying the bearer into top-frame storage', async () => {
    const storageSpy = vi.spyOn(window.sessionStorage.__proto__, 'setItem');
    const wrapper = mountView();
    await flushPromises();

    await wrapper.get('[data-testid="subscription-upgrade"]').trigger('click');
    await flushPromises();

    expect(issueWebHandoff).toHaveBeenCalledWith('subscription');
    expect(storageSpy).not.toHaveBeenCalled();
    storageSpy.mockRestore();
  });

  it('shows a safe message when web payment is unavailable', async () => {
    mockEndpoints({ paymentEnabled: false });
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.text()).toContain('Upgrades are temporarily unavailable. Your current plan remains active.');
    expect(wrapper.find('[data-testid="subscription-upgrade"]').exists()).toBe(false);
  });

  it('routes existing mobile upgrade gates to the in-app comparison without bearer copying', () => {
    const push = vi.fn();
    const storageSpy = vi.spyOn(window.sessionStorage.__proto__, 'setItem');

    upgradeMixin.methods.goUpgrade.call({ paidUpgradeAvailable: true, $router: { push } });

    expect(push).toHaveBeenCalledWith('/subscription');
    expect(storageSpy).not.toHaveBeenCalled();
    storageSpy.mockRestore();
  });
});
