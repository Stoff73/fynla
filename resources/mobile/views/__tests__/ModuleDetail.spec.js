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
import { store } from '../../store.js';

function mountModuleDetail(push) {
  return mount(ModuleDetail, {
    props: { slug: 'protection' },
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
