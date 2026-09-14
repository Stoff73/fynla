import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
import { createStore } from 'vuex';
import AiChatPanel from '../../Shared/AiChatPanel.vue';

vi.mock('@/services/analyticsService', () => ({ default: { trackChatOpened: vi.fn(), trackChatMessageSent: vi.fn() } }));
vi.mock('@/utils/chatNavigationRouter', () => ({ matchNavigationIntent: vi.fn(() => null) }));
vi.mock('@/constants/fynIcon', () => ({ fynIconUrl: '' }));
vi.mock('@/utils/subscriptionNavigation', () => ({ subscriptionOptionsLocation: () => ({ path: '/settings/subscription' }) }));

/**
 * MB-26. Opening Fyn as a user who registered but never took the first turn
 * gave the advice greeting on web while /m started onboarding. The server
 * now says who needs starting (onboarding_fyn_needs_start) and the panel
 * obeys it, alongside the existing mid-walk resume.
 */
describe('AiChatPanel — onOpen starts onboarding for a not-started user (MB-26)', () => {
  let actions;

  const build = (user) => {
    actions = {
      close: vi.fn(), toggle: vi.fn(), toggleHistory: vi.fn(),
      fetchConversations: vi.fn(), startNewConversation: vi.fn(), loadConversation: vi.fn(),
      deleteConversation: vi.fn(), sendMessage: vi.fn(), abortStreaming: vi.fn(), postAction: vi.fn(),
      cancelQueued: vi.fn(), fetchResumption: vi.fn(), acknowledgeResumption: vi.fn(),
      startOnboardingConversation: vi.fn(),
    };
    const state = {
      isOpen: false, messages: [], conversations: [], currentConversation: null, loading: false,
      loadingConversations: false, streaming: false, streamingText: '', showHistory: false,
      isOnboardingActive: false, prefilledPrompt: null, pendingResumption: null, tokenLimitReached: false,
      secondsUntilReset: 0, queuedMessageId: null, suggestedPrompts: [],
    };
    const store = createStore({
      modules: {
        aiChat: {
          namespaced: true, state: () => state, actions,
          getters: {
            isOpen: (s) => s.isOpen, messages: (s) => s.messages, conversations: () => [], currentConversation: (s) => s.currentConversation,
            loading: () => false, loadingConversations: () => false, streaming: () => false, streamingText: () => '', showHistory: () => false,
            isOnboardingActive: () => false, pendingResumption: () => null, tokenLimitReached: () => false, secondsUntilReset: () => 0,
            suggestedPrompts: () => [], queuedMessageId: () => null, prefilledPrompt: () => null, pendingNavigation: () => null, onboardingLayout: () => null,
          },
          mutations: { SET_PREFILLED_PROMPT: vi.fn() },
        },
        auth: { namespaced: true, state: () => ({ user }), getters: { user: (s) => s.user } },
        infoGuide: { namespaced: true, actions: { close: vi.fn() } },
      },
    });
    return shallowMount(AiChatPanel, { global: { plugins: [store], stubs: { RouterLink: true }, mocks: { $router: { push: vi.fn() }, $route: { path: '/dashboard' } } } });
  };

  beforeEach(() => vi.clearAllMocks());

  it('starts onboarding when the server flags the user as needing a start', async () => {
    const wrapper = build({ onboarding_completed: false, onboarding_fyn_step: null, onboarding_fyn_needs_start: true });
    await wrapper.vm.onOpen();
    await flushPromises();
    expect(actions.startOnboardingConversation).toHaveBeenCalledTimes(1);
    expect(actions.startNewConversation).not.toHaveBeenCalled();
  });

  it('still resumes a mid-walk user and still opens a plain chat for everyone else', async () => {
    const mid = build({ onboarding_completed: false, onboarding_fyn_step: 'base_work', onboarding_fyn_needs_start: false });
    await mid.vm.onOpen();
    await flushPromises();
    expect(actions.startOnboardingConversation).toHaveBeenCalledTimes(1);

    const paused = build({ onboarding_completed: false, onboarding_fyn_step: null, onboarding_fyn_needs_start: false, onboarding_fyn_paused: true });
    await paused.vm.onOpen();
    await flushPromises();
    expect(actions.startOnboardingConversation).not.toHaveBeenCalled();
    expect(actions.startNewConversation).toHaveBeenCalledTimes(1);
  });
});
