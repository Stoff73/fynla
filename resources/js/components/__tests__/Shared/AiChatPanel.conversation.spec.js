import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
import { createStore } from 'vuex';
import AiChatPanel from '../../Shared/AiChatPanel.vue';

vi.mock('@/services/analyticsService', () => ({
  default: { trackChatOpened: vi.fn(), trackChatMessageSent: vi.fn() },
}));

// Navigation intents are handled locally and never need a conversation, so the
// default here is "not a navigation phrase" — the send path under test.
vi.mock('@/utils/chatNavigationRouter', () => ({
  matchNavigationIntent: vi.fn(() => null),
}));

vi.mock('@/constants/fynIcon', () => ({ fynIconUrl: '' }));
vi.mock('@/utils/subscriptionNavigation', () => ({
  subscriptionOptionsLocation: () => ({ path: '/settings/subscription' }),
}));

/**
 * The docked desktop panel is rendered by AppLayout on `chatCollapsed`, not on
 * aiChat.isOpen, so the panel is visible and typeable while the store still
 * holds isOpen === false. onOpen() — the only place that created a conversation
 * — runs off the isOpen watcher, so a brand-new user had a usable panel, no
 * conversation, and aiChat/sendMessage returning silently. Their message was
 * destroyed with no error and no request. These tests pin the guarantee that
 * every send path establishes a conversation first.
 */
describe('AiChatPanel — conversation bootstrap before send', () => {
  let store;
  let actions;
  let state;

  const build = () => {
    actions = {
      close: vi.fn(),
      toggle: vi.fn(),
      toggleHistory: vi.fn(),
      fetchConversations: vi.fn(),
      // Creating a conversation is what populates currentConversation. Mutate
      // through the action's own context so the reactive proxy is updated and
      // the currentConversation computed actually invalidates.
      startNewConversation: vi.fn((ctx) => {
        ctx.state.currentConversation = { id: 42 };
      }),
      loadConversation: vi.fn(),
      deleteConversation: vi.fn(),
      sendMessage: vi.fn(),
      abortStreaming: vi.fn(),
      postAction: vi.fn(),
      cancelQueued: vi.fn(),
      fetchResumption: vi.fn(),
      acknowledgeResumption: vi.fn(),
      startOnboardingConversation: vi.fn(),
    };

    state = {
      isOpen: false,
      messages: [],
      conversations: [],
      currentConversation: null,
      loading: false,
      loadingConversations: false,
      streaming: false,
      streamingText: '',
      showHistory: false,
      isOnboardingActive: false,
      prefilledPrompt: null,
      pendingResumption: null,
      tokenLimitReached: false,
      secondsUntilReset: 0,
      queuedMessageId: null,
      suggestedPrompts: [],
    };

    store = createStore({
      modules: {
        aiChat: {
          namespaced: true,
          state: () => state,
          getters: {
            isOpen: (s) => s.isOpen,
            messages: (s) => s.messages,
            conversations: (s) => s.conversations,
            currentConversation: (s) => s.currentConversation,
            loading: (s) => s.loading,
            loadingConversations: (s) => s.loadingConversations,
            streaming: (s) => s.streaming,
            streamingText: (s) => s.streamingText,
            showHistory: (s) => s.showHistory,
            isOnboardingActive: (s) => s.isOnboardingActive,
            pendingResumption: (s) => s.pendingResumption,
            tokenLimitReached: (s) => s.tokenLimitReached,
            secondsUntilReset: (s) => s.secondsUntilReset,
            suggestedPrompts: (s) => s.suggestedPrompts,
            queuedMessageId: (s) => s.queuedMessageId,
            prefilledPrompt: (s) => s.prefilledPrompt,
            pendingNavigation: () => null,
            onboardingLayout: () => null,
          },
          actions,
          mutations: {
            ADD_MESSAGE: (s, m) => s.messages.push(m),
            SET_PREFILLED_PROMPT: (s, v) => { s.prefilledPrompt = v; },
            SET_MESSAGES: (s, v) => { s.messages = v; },
          },
        },
        auth: {
          namespaced: true,
          state: () => ({ user: { onboarding_completed: true, onboarding_fyn_step: null } }),
          getters: { user: (s) => s.user },
        },
        infoGuide: { namespaced: true, actions: { close: vi.fn() } },
      },
    });

    return shallowMount(AiChatPanel, {
      global: {
        plugins: [store],
        stubs: { RouterLink: true },
        mocks: { $router: { push: vi.fn() } },
      },
    });
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('creates a conversation when none exists, then sends', async () => {
    const wrapper = build();
    wrapper.vm.inputMessage = 'Introduce yourself';

    await wrapper.vm.send();
    await flushPromises();

    expect(actions.startNewConversation).toHaveBeenCalledTimes(1);
    expect(actions.sendMessage.mock.calls[0][1]).toBe('Introduce yourself');
    expect(wrapper.vm.inputMessage).toBe('');
  });

  it('does not create a second conversation when one is already active', async () => {
    const wrapper = build();
    store.state.aiChat.currentConversation = { id: 7 };
    wrapper.vm.inputMessage = 'Second turn';

    await wrapper.vm.send();
    await flushPromises();

    expect(actions.startNewConversation).not.toHaveBeenCalled();
    expect(actions.sendMessage.mock.calls[0][1]).toBe('Second turn');
  });

  it('keeps the text in the box when the conversation cannot be created', async () => {
    const wrapper = build();
    actions.startNewConversation.mockImplementation(() => {
      // creation failed — currentConversation stays null
    });
    wrapper.vm.inputMessage = 'Do not lose me';

    await wrapper.vm.send();
    await flushPromises();

    expect(actions.sendMessage).not.toHaveBeenCalled();
    expect(wrapper.vm.inputMessage).toBe('Do not lose me');
  });

  it('establishes a conversation for a quick-reply label too', async () => {
    const wrapper = build();

    await wrapper.vm.handleQuickReplySelect({ label: 'Yes please' }, {});
    await flushPromises();

    expect(actions.startNewConversation).toHaveBeenCalledTimes(1);
    expect(actions.sendMessage.mock.calls[0][1]).toBe('Yes please');
  });
});
