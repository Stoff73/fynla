import { describe, expect, it, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createStore } from 'vuex';
import AiChatPanel from '../../Shared/AiChatPanel.vue';

vi.mock('@/services/analyticsService', () => ({ default: { trackChatOpened: vi.fn(), trackChatMessageSent: vi.fn() } }));
vi.mock('@/constants/fynIcon', () => ({ fynIconUrl: '' }));
vi.mock('@/utils/subscriptionNavigation', () => ({ subscriptionOptionsLocation: () => ({ path: '/settings/subscription' }) }));

/**
 * T48 (CSJ 2026-10-12): one Fyn limit per rolling week. The notice shows the
 * server's own message, and the reset can be days away, so the panel no
 * longer says "daily" or "midnight" itself.
 */
const WEEKLY = "You've reached your weekly Fyn usage limit.";

describe('AiChatPanel — weekly limit notice (T48)', () => {
  const build = (secondsUntilReset) => {
    const store = createStore({
      modules: {
        aiChat: {
          namespaced: true,
          state: () => ({}),
          actions: { fetchConversations: vi.fn(), fetchResumption: vi.fn() },
          getters: {
            isOpen: () => true, messages: () => [], conversations: () => [], currentConversation: () => null,
            loading: () => false, loadingConversations: () => false, streaming: () => false, streamingText: () => '', showHistory: () => false,
            isOnboardingActive: () => false, pendingResumption: () => null, suggestedPrompts: () => [], queuedMessageId: () => null,
            prefilledPrompt: () => null, pendingNavigation: () => null, onboardingLayout: () => null,
            tokenLimitReached: () => true, tokenLimitMessage: () => WEEKLY, secondsUntilReset: () => secondsUntilReset,
          },
          mutations: { SET_PREFILLED_PROMPT: vi.fn(), SET_TOKEN_LIMIT: vi.fn() },
        },
        auth: { namespaced: true, state: () => ({ user: { onboarding_completed: true } }), getters: { user: (s) => s.user } },
        infoGuide: { namespaced: true, actions: { close: vi.fn() } },
      },
    });
    return shallowMount(AiChatPanel, { global: { plugins: [store], stubs: { RouterLink: true }, mocks: { $router: { push: vi.fn() }, $route: { path: '/dashboard' } } } });
  };

  it('shows the server message, not its own daily wording', () => {
    const wrapper = build(3600);
    expect(wrapper.vm.tokenLimitMessage).toBe(WEEKLY);
    expect(wrapper.vm.inputPlaceholder).not.toMatch(/daily|midnight/i);
  });

  // countdownSeconds is the ticking copy the secondsUntilReset watcher keeps.
  it('counts down in days and hours when the reset is days away', async () => {
    const wrapper = build(0);
    await wrapper.setData({ countdownSeconds: 2 * 86400 + 4 * 3600 + 120 });
    expect(wrapper.vm.resetCountdown).toBe('2d 4h');
    expect(wrapper.vm.inputPlaceholder).toBe('Fyn usage limit reached — resets in 2d 4h');
  });

  it('keeps hours and minutes under a day', async () => {
    const wrapper = build(0);
    await wrapper.setData({ countdownSeconds: 5 * 3600 + 7 * 60 });
    expect(wrapper.vm.resetCountdown).toBe('5h 7m');
  });
});
