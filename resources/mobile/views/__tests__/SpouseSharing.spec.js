import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiDelete: vi.fn(),
}));

import { apiGet, apiPost, apiDelete } from '../../api.js';
import SpouseSharing from '../SpouseSharing.vue';

const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel', 'back', 'editDetails'],
  emits: ['back'],
  template: '<main><h1>{{ title }}</h1><slot /></main>',
};

function mountScreen(push = vi.fn()) {
  return mount(SpouseSharing, {
    global: {
      mocks: { $router: { push }, $route: { path: '/spouse-sharing', query: {} } },
      stubs: { MobileChrome: MobileChromeStub },
    },
  });
}

const ok = (data) => ({ ok: true, status: 200, data: { success: true, data } });

/**
 * Rule 19 parity for the consent screen (W-0347).
 *
 * The branch that matters most is `awaiting_your_response`: the invitee is the
 * person being asked, they are the one most likely to be on a phone, and the
 * notification email lands them here. Before this screen existed there was
 * nowhere on /m to answer at all — which is the condition that made consent
 * unobtainable and led the backend to forge it.
 */
describe('/m household sharing', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lets an invitee accept a request addressed to them', async () => {
    apiGet.mockResolvedValue(ok({
      has_spouse: true,
      awaiting_your_response: true,
      can_view_spouse_data: false,
      spouse: { id: 7, name: 'Sarah Jones', email: 'sarah@example.com' },
      permission: { status: 'pending' },
    }));
    apiPost.mockResolvedValue({ ok: true, status: 200, data: {} });

    const wrapper = mountScreen();
    await flushPromises();

    expect(wrapper.find('[data-testid="sharing-incoming"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('Sarah Jones');
    expect(wrapper.text()).toContain('Nothing is shared until you accept');

    await wrapper.find('[data-testid="sharing-accept"]').trigger('click');
    await flushPromises();

    expect(apiPost.mock.calls[0][0]).toBe('/api/spouse-permission/accept');
  });

  it('lets an invitee decline', async () => {
    apiGet.mockResolvedValue(ok({
      has_spouse: true, awaiting_your_response: true, can_view_spouse_data: false,
      spouse: { id: 7, name: 'Sarah Jones' }, permission: { status: 'pending' },
    }));
    apiPost.mockResolvedValue({ ok: true, status: 200, data: {} });

    const wrapper = mountScreen();
    await flushPromises();
    await wrapper.find('[data-testid="sharing-decline"]').trigger('click');
    await flushPromises();

    expect(apiPost.mock.calls[0][0]).toBe('/api/spouse-permission/reject');
  });

  it('shows a requester that nothing is shared yet, and never names the account they typed', async () => {
    apiGet.mockResolvedValue(ok({
      has_spouse: true,
      awaiting_their_response: true,
      can_view_spouse_data: false,
      // The server sends back the caller's OWN card, with no account id or
      // email — returning the real account holder's identity here would answer
      // "who owns this address?" for anything they cared to type (W-0349).
      spouse: { id: null, name: 'Cap Throwaway', email: null },
      permission: { status: 'pending' },
    }));

    const wrapper = mountScreen();
    await flushPromises();

    expect(wrapper.find('[data-testid="sharing-outgoing"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('Nothing is shared until they accept');
    expect(wrapper.find('[data-testid="sharing-accept"]').exists()).toBe(false);
  });

  it('offers to stop sharing when it is on', async () => {
    apiGet.mockResolvedValue(ok({
      has_spouse: true, can_view_spouse_data: true,
      spouse: { id: 7, name: 'Chris Jones' }, permission: { status: 'accepted' },
    }));
    apiDelete.mockResolvedValue({ ok: true, status: 200, data: {} });

    const wrapper = mountScreen();
    await flushPromises();

    expect(wrapper.find('[data-testid="sharing-active"]').exists()).toBe(true);
    await wrapper.find('[data-testid="sharing-revoke"]').trigger('click');
    await flushPromises();

    expect(apiDelete.mock.calls[0][0]).toBe('/api/spouse-permission/revoke');
  });

  it('says sharing is off for a linked pair that withdrew it', async () => {
    apiGet.mockResolvedValue(ok({
      has_spouse: true, can_view_spouse_data: false,
      spouse: { id: 7, name: 'Chris Jones' }, permission: { status: 'rejected' },
    }));

    const wrapper = mountScreen();
    await flushPromises();

    expect(wrapper.find('[data-testid="sharing-off"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('not being shared');
  });

  it('sends an expired session to login rather than showing a load error', async () => {
    apiGet.mockResolvedValue({ ok: false, status: 401, data: {} });
    const push = vi.fn();

    const wrapper = mountScreen(push);
    await flushPromises();

    expect(push).toHaveBeenCalledWith('/login');
    expect(wrapper.text()).not.toContain('could not load');
  });
});
