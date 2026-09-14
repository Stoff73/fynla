import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
import { createStore } from 'vuex';
import AiChatPanel from '../../Shared/AiChatPanel.vue';

vi.mock('@/services/analyticsService', () => ({
  default: { trackChatOpened: vi.fn(), trackChatMessageSent: vi.fn() },
}));
vi.mock('@/utils/chatNavigationRouter', () => ({ matchNavigationIntent: vi.fn(() => null) }));
vi.mock('@/constants/fynIcon', () => ({ fynIconUrl: '' }));
vi.mock('@/utils/subscriptionNavigation', () => ({
  subscriptionOptionsLocation: () => ({ path: '/settings/subscription' }),
}));

/**
 * MB-27 / MB-47. Fyn navigates after a write, and the page it sends the user to
 * must show what was just written. Two ways that failed on web:
 *
 *  - profile-backed pages (income, expenditure) render from auth/currentUser and
 *    userProfile/profile, both loaded at sign-in and never refreshed on a Fyn
 *    navigation, so the expenditure verify showed £0 with £3,200 in the database;
 *  - a route that resolves to the page already on screen (a verify edit
 *    re-sending /investment while on /net-worth/investments) never remounts, so
 *    the page kept the pre-edit figure. The old same-route check compared the
 *    raw route string, which a redirect alias defeats.
 */
describe('AiChatPanel — Fyn navigation refreshes the destination', () => {
  let fetchUser;
  let fetchProfile;
  let close;
  let route;
  let push;

  const build = () => {
    fetchUser = vi.fn(() => Promise.resolve());
    fetchProfile = vi.fn(() => Promise.resolve());
    close = vi.fn();
    const store = createStore({
      modules: {
        aiChat: {
          namespaced: true,
          state: () => ({ messages: [], conversations: [], suggestedPrompts: [] }),
          getters: {
            isOpen: () => false, messages: (s) => s.messages, conversations: () => [],
            currentConversation: () => null, loading: () => false, loadingConversations: () => false,
            streaming: () => false, streamingText: () => '', showHistory: () => false,
            isOnboardingActive: () => false, pendingResumption: () => null, tokenLimitReached: () => false,
            secondsUntilReset: () => 0, suggestedPrompts: () => [], queuedMessageId: () => null,
            prefilledPrompt: () => null, pendingNavigation: () => null, onboardingLayout: () => null,
          },
          actions: { close, fetchConversations: vi.fn(), fetchResumption: vi.fn() },
          mutations: { SET_PENDING_NAVIGATION: vi.fn() },
        },
        auth: {
          namespaced: true,
          state: () => ({ user: { onboarding_completed: true, onboarding_fyn_step: null } }),
          getters: { user: (s) => s.user },
          actions: { fetchUser },
        },
        userProfile: { namespaced: true, actions: { fetchProfile } },
        infoGuide: { namespaced: true, actions: { close: vi.fn() } },
      },
    });
    // The router follows the app's redirect aliases the way the real one does.
    route = { path: '/net-worth/investments', fullPath: '/net-worth/investments' };
    push = vi.fn(async (to) => {
      const resolved = { '/investment': '/net-worth/investments', '/expenditure': '/valuable-info' }[to.path] || to.path;
      const qs = new URLSearchParams(to.query || {}).toString();
      route.path = resolved;
      route.fullPath = qs ? `${resolved}?${qs}` : resolved;
    });
    return shallowMount(AiChatPanel, {
      global: { plugins: [store], stubs: { RouterLink: true }, mocks: { $router: { push }, $route: route } },
    });
  };

  beforeEach(() => vi.clearAllMocks());

  it('refreshes the cached user and profile before navigating', async () => {
    const wrapper = build();
    await wrapper.vm.handleNavigation('/expenditure');
    await flushPromises();
    expect(fetchUser).toHaveBeenCalledTimes(1);
    expect(fetchProfile).toHaveBeenCalledTimes(1);
    expect(push).toHaveBeenCalledWith({ path: '/expenditure', query: {} });
    expect(fetchUser.mock.invocationCallOrder[0]).toBeLessThan(push.mock.invocationCallOrder[0]);
  });

  it('parses a query string into the router push', async () => {
    const wrapper = build();
    await wrapper.vm.handleNavigation('/valuable-info?section=expenditure');
    expect(push).toHaveBeenCalledWith({ path: '/valuable-info', query: { section: 'expenditure' } });
  });

  it('asks the page to refetch when the route resolves to the screen already shown', async () => {
    const wrapper = build();
    const refresh = vi.fn();
    const closeEvent = vi.fn();
    window.addEventListener('fyn-screen-refresh', refresh);
    window.addEventListener('fyn-close-chat', closeEvent);
    await wrapper.vm.handleNavigation('/investment');
    await flushPromises();
    expect(refresh).toHaveBeenCalledTimes(1);
    expect(closeEvent).toHaveBeenCalledTimes(1);
    expect(close).toHaveBeenCalledTimes(1);
    window.removeEventListener('fyn-screen-refresh', refresh);
    window.removeEventListener('fyn-close-chat', closeEvent);
  });

  it('does not fire the refresh or close the chat when the screen changes', async () => {
    const wrapper = build();
    const refresh = vi.fn();
    window.addEventListener('fyn-screen-refresh', refresh);
    await wrapper.vm.handleNavigation('/retirement');
    await flushPromises();
    expect(refresh).not.toHaveBeenCalled();
    expect(close).not.toHaveBeenCalled();
    window.removeEventListener('fyn-screen-refresh', refresh);
  });
});
