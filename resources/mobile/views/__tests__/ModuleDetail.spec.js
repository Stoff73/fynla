import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

// Representative coverage for the /m dead-token DATA-VIEW wave (CSJ audit,
// 2026-07-21, follow-on to the chat-path fix in 323915e/5b2b9dd): every view
// load() that does NOT mix in onboardingChat must still route a 401 through
// the shared authExpiry.js helper (logout + redirect to /m login) instead of
// rendering a generic "could not load" error with no way forward.
// ModuleDetail.vue stands in for the whole wave — its load() shape
// (destructure { ok, status, data } from a single apiGet call) is the exact
// pattern every one of the 19 fixed views shares.
vi.mock('../../api.js', () => ({
  apiGet: vi.fn(),
}));

import { apiGet } from '../../api.js';
import ModuleDetail from '../ModuleDetail.vue';
import Savings from '../modules/Savings.vue';
import NetWorth from '../modules/NetWorth.vue';
import NetWorthCategory from '../modules/NetWorthCategory.vue';
import { store } from '../../store.js';

const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel', 'back'],
  emits: ['back'],
  template: '<section><p data-test="mobile-title">{{ title }}</p><slot /></section>',
};

function mountMobile(component, push, params = {}) {
  return mount(component, {
    global: {
      stubs: { MobileChrome: MobileChromeStub },
      mocks: {
        $route: { params },
        $router: { push },
      },
    },
  });
}

function mountModuleDetail(push, slug = 'protection') {
  return mount(ModuleDetail, {
    props: { slug },
    global: {
      mocks: {
        $route: { path: '/module/protection', query: {} },
        $router: { push },
      },
    },
  });
}

describe('ModuleDetail.vue — load() routes a 401 through handleAuthExpiry', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('logs out and redirects to /m login on a 401, without setting the generic error', async () => {
    apiGet.mockResolvedValue({ ok: false, status: 401, data: {} });
    store.token = 'dead-token';
    const push = vi.fn();
    const wrapper = mountModuleDetail(push);
    await flushPromises();

    expect(push).toHaveBeenCalledWith('/login');
    expect(store.token).toBeNull();
    expect(wrapper.vm.error).toBe('');
  });

  it('shows the generic error on a non-401 failure (unchanged behaviour)', async () => {
    apiGet.mockResolvedValue({ ok: false, status: 500, data: {} });
    store.token = 'live-token';
    const push = vi.fn();
    const wrapper = mountModuleDetail(push);
    await flushPromises();

    expect(push).not.toHaveBeenCalledWith('/login');
    expect(store.token).toBe('live-token');
    expect(wrapper.vm.error).toBe('We could not load this module.');
  });
});

describe('/m presentation naming preserves compatible route and API keys', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'naming-token';
  });

  it('presents Savings as Bank Accounts while retaining the /api/savings and /savings routes', async () => {
    apiGet.mockResolvedValue({
      ok: true,
      status: 200,
      data: {
        accounts: [{ id: 7, provider: 'Example Bank', current_balance: 5000, is_isa: false }],
        account_count: 1,
      },
    });
    const push = vi.fn();
    const wrapper = mountMobile(Savings, push);
    await flushPromises();

    expect(wrapper.get('[data-test="mobile-title"]').text()).toBe('Bank Accounts');
    expect(apiGet).toHaveBeenCalledWith('/api/savings', 'naming-token');
    await wrapper.get('.ms-acct').trigger('click');
    expect(push).toHaveBeenCalledWith('/savings/account/7');

    apiGet.mockReset();
    apiGet.mockResolvedValue({ ok: true, status: 200, data: { summary: {} } });
    const legacyOverview = mountModuleDetail(vi.fn(), 'savings');
    await flushPromises();
    expect(legacyOverview.text()).toContain('Bank Accounts');
  });

  it('presents the chattels category as Valuables while retaining the chattels route key', async () => {
    apiGet
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: { total_assets: 1000, total_liabilities: 0, net_worth: 1000 },
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: {
          chattels: {
            count: 1,
            total_value: 1000,
            items: [{ id: 9, name: 'Watch', value: 1000 }],
          },
        },
      });
    const push = vi.fn();
    const wrapper = mountMobile(NetWorth, push);
    await flushPromises();

    const valuables = wrapper.findAll('button').find((button) => button.text().includes('Valuables'));
    expect(valuables).toBeTruthy();
    await valuables.trigger('click');
    expect(push).toHaveBeenCalledWith('/net-worth/chattels');

    apiGet.mockReset();
    apiGet.mockResolvedValue({
      ok: true,
      status: 200,
      data: { chattels: { count: 0, total_value: 0, items: [] } },
    });
    const detail = mountMobile(NetWorthCategory, vi.fn(), { category: 'chattels' });
    await flushPromises();

    expect(detail.get('.m-h1').text()).toBe('Valuables');
    expect(apiGet).toHaveBeenCalledWith('/api/net-worth/assets-summary-detailed', 'naming-token');
  });
});
