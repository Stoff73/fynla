import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({
  apiGet: vi.fn(),
  apiPut: vi.fn(),
}));

vi.mock('../../navigation/webHandoff.js', () => ({
  issueWebHandoff: vi.fn(),
  openPublicWebPath: vi.fn(),
}));

import { apiGet, apiPut } from '../../api.js';
import { issueWebHandoff, openPublicWebPath } from '../../navigation/webHandoff.js';
import { store } from '../../store.js';
import NotificationPreferences from '../NotificationPreferences.vue';
import Settings from '../Settings.vue';

const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel', 'back'],
  emits: ['back'],
  template: '<main><h1>{{ title }}</h1><slot /></main>',
};

function mountSettings(push = vi.fn()) {
  return mount(Settings, {
    global: {
      mocks: { $router: { push }, $route: { path: '/settings', query: {} } },
      stubs: { MobileChrome: MobileChromeStub },
    },
  });
}

function mountNotifications() {
  return mount(NotificationPreferences, {
    global: {
      mocks: { $router: { push: vi.fn() }, $route: { path: '/notifications', query: {} } },
      stubs: { MobileChrome: MobileChromeStub },
    },
  });
}

describe('Settings.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    issueWebHandoff.mockResolvedValue();
  });

  it('keeps notifications native and uses a secure privacy handoff', async () => {
    const push = vi.fn();
    const wrapper = mountSettings(push);

    await wrapper.get('[data-testid="settings-notifications"]').trigger('click');
    await wrapper.get('[data-testid="settings-privacy-data"]').trigger('click');
    await flushPromises();

    expect(push).toHaveBeenCalledWith('/notifications');
    expect(issueWebHandoff).toHaveBeenCalledWith('privacy');
  });

  it.each([
    ['settings-help', '/help'],
    ['settings-privacy-policy', '/privacy'],
    ['settings-terms', '/terms'],
  ])('opens %s as a credential-free public link', async (testId, path) => {
    const wrapper = mountSettings();

    await wrapper.get(`[data-testid="${testId}"]`).trigger('click');

    expect(openPublicWebPath).toHaveBeenCalledWith(path);
    expect(issueWebHandoff).not.toHaveBeenCalled();
  });
});

describe('NotificationPreferences.vue', () => {
  const preferences = {
    policy_renewals: true,
    market_updates: false,
    fyn_daily_insight: true,
    security_alerts: true,
    payment_alerts: true,
  };

  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    apiGet.mockResolvedValue({ ok: true, status: 200, data: { data: preferences } });
    apiPut.mockResolvedValue({ ok: true, status: 200, data: { success: true } });
  });

  it('persists a toggle through the mobile preference endpoint before displaying it', async () => {
    const wrapper = mountNotifications();
    await flushPromises();
    const toggle = wrapper.get('[data-testid="notification-market_updates"]');

    expect(toggle.attributes('aria-pressed')).toBe('false');
    await toggle.trigger('click');
    await flushPromises();

    expect(apiPut).toHaveBeenCalledWith(
      '/api/v1/mobile/notifications/preferences',
      { market_updates: true },
      'live-token',
    );
    expect(toggle.attributes('aria-pressed')).toBe('true');
  });

  it('retains the canonical value on failure and retries the same change', async () => {
    apiPut
      .mockResolvedValueOnce({ ok: false, status: 503, data: {} })
      .mockResolvedValueOnce({ ok: true, status: 200, data: { success: true } });
    const wrapper = mountNotifications();
    await flushPromises();
    const toggle = wrapper.get('[data-testid="notification-fyn_daily_insight"]');

    expect(toggle.attributes('aria-pressed')).toBe('true');
    await toggle.trigger('click');
    await flushPromises();

    expect(toggle.attributes('aria-pressed')).toBe('true');
    expect(wrapper.get('[role="alert"]').text()).toContain('We could not save that preference.');

    await wrapper.get('[data-testid="notification-retry"]').trigger('click');
    await flushPromises();

    expect(apiPut).toHaveBeenLastCalledWith(
      '/api/v1/mobile/notifications/preferences',
      { fyn_daily_insight: false },
      'live-token',
    );
    expect(toggle.attributes('aria-pressed')).toBe('false');
  });
});
