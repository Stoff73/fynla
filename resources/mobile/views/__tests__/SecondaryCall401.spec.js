import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

// Task 6 of the structured turn-intent plan (approved minor, closing the
// 2026-07-22 wave's deliberate primary-only scope): a 401 on a SECONDARY
// data call — Estate's net-worth follow-up, the second leg of NetWorth's
// and Goals' Promise.all — must also route through the shared authExpiry
// helper. A dead token fails every request identically; handling only the
// primary left these views half-rendered on a dead session.
vi.mock('../../api.js', () => ({
  apiGet: vi.fn(),
}));

import { apiGet } from '../../api.js';
import Estate from '../modules/Estate.vue';
import NetWorth from '../modules/NetWorth.vue';
import Goals from '../modules/Goals.vue';
import { store } from '../../store.js';

function mountView(component, push) {
  return mount(component, {
    global: {
      mocks: {
        $route: { path: '/x', query: {} },
        $router: { push },
      },
    },
  });
}

describe('secondary data calls re-authenticate on 401', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'dead-token';
  });

  it('Estate: a 401 on the net-worth follow-up logs out and redirects', async () => {
    apiGet.mockImplementation((url) =>
      Promise.resolve(
        url === '/api/estate'
          ? { ok: true, status: 200, data: { mode: 'full', data: {} } }
          : { ok: false, status: 401, data: {} },
      ),
    );
    const push = vi.fn();
    mountView(Estate, push);
    await flushPromises();

    expect(push).toHaveBeenCalledWith('/login');
    expect(store.token).toBeNull();
  });

  it('NetWorth: a 401 on the detailed-summary leg logs out and redirects', async () => {
    apiGet.mockImplementation((url) =>
      Promise.resolve(
        url === '/api/net-worth/overview'
          ? { ok: true, status: 200, data: { data: {} } }
          : { ok: false, status: 401, data: {} },
      ),
    );
    const push = vi.fn();
    mountView(NetWorth, push);
    await flushPromises();

    expect(push).toHaveBeenCalledWith('/login');
    expect(store.token).toBeNull();
  });

  it('Goals: a 401 on the overview leg logs out and redirects', async () => {
    apiGet.mockImplementation((url) =>
      Promise.resolve(
        url === '/api/goals'
          ? { ok: true, status: 200, data: { data: { goals: [] } } }
          : { ok: false, status: 401, data: {} },
      ),
    );
    const push = vi.fn();
    mountView(Goals, push);
    await flushPromises();

    expect(push).toHaveBeenCalledWith('/login');
    expect(store.token).toBeNull();
  });
});
