import { describe, expect, it, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createStore } from 'vuex';
import AiChatPanel from '../../Shared/AiChatPanel.vue';

vi.mock('@/services/analyticsService', () => ({ default: { trackChatOpened: vi.fn(), trackChatMessageSent: vi.fn() } }));
vi.mock('@/constants/fynIcon', () => ({ fynIconUrl: '' }));
vi.mock('@/utils/subscriptionNavigation', () => ({ subscriptionOptionsLocation: () => ({ path: '/settings/subscription' }) }));

/**
 * T58 (CSJ 2026-10-12): while bubbles that answer the step are waiting, the
 * chat box is disabled, so the answer is a tap, never typed words.
 */
const bubbles = (list, extra = {}) => ({ id: 'qr', role: 'quick_replies', content: 'Do you have a partner?', metadata: { bubbles: list, ...extra } });

describe('AiChatPanel — the chat box while bubbles wait (T58)', () => {
  const build = (messages) => {
    const store = createStore({
      modules: {
        aiChat: {
          namespaced: true,
          state: () => ({}),
          actions: { fetchConversations: vi.fn(), fetchResumption: vi.fn() },
          getters: {
            isOpen: () => true, messages: () => messages, conversations: () => [], currentConversation: () => null,
            loading: () => false, loadingConversations: () => false, streaming: () => false, streamingText: () => '', showHistory: () => false,
            isOnboardingActive: () => true, pendingResumption: () => null, suggestedPrompts: () => [], queuedMessageId: () => null,
            prefilledPrompt: () => null, pendingNavigation: () => null, onboardingLayout: () => null,
            tokenLimitReached: () => false, tokenLimitMessage: () => '', secondsUntilReset: () => 0,
          },
          mutations: { SET_PREFILLED_PROMPT: vi.fn(), SET_TOKEN_LIMIT: vi.fn() },
        },
        auth: { namespaced: true, state: () => ({ user: { onboarding_completed: false } }), getters: { user: (s) => s.user } },
        infoGuide: { namespaced: true, actions: { close: vi.fn() } },
      },
    });
    return shallowMount(AiChatPanel, { global: { plugins: [store], stubs: { RouterLink: true, AiChatPanelShell: { template: '<div><slot /></div>' } }, mocks: { $router: { push: vi.fn() }, $route: { path: '/dashboard' } } } });
  };
  const textarea = (wrapper) => wrapper.find('textarea');

  it('disables the box while answer bubbles wait', () => {
    const wrapper = build([bubbles([{ id: 'yes', label: 'Yes' }, { id: 'no', label: 'No' }])]);
    expect(textarea(wrapper).attributes('disabled')).toBeDefined();
    expect(wrapper.vm.inputPlaceholder).toBe('Pick one of the options above');
  });

  it('disables the box for the resume bubbles (Continue / Something else)', () => {
    const wrapper = build([bubbles([{ id: 'continue', label: 'Continue' }, { id: 'something_else', label: 'Something else' }], { action_bubbles: true })]);
    expect(textarea(wrapper).attributes('disabled')).toBeDefined();
  });

  it('opens the box again once the user has answered', () => {
    const wrapper = build([
      bubbles([{ id: 'yes', label: 'Yes' }, { id: 'no', label: 'No' }]),
      { id: 'u1', role: 'user', content: 'Yes' },
    ]);
    expect(textarea(wrapper).attributes('disabled')).toBeUndefined();
  });

  it('leaves the box open for a navigation bubble alone', () => {
    const wrapper = build([bubbles([{ id: 'view_plan', label: 'Take me to my tax strategy', route: '/tax-strategy' }])]);
    expect(textarea(wrapper).attributes('disabled')).toBeUndefined();
    expect(wrapper.vm.inputPlaceholder).toBe('Ask Fyn...');
  });

  it('leaves the box open for a bubble-less note', () => {
    const wrapper = build([bubbles([])]);
    expect(textarea(wrapper).attributes('disabled')).toBeUndefined();
  });

  it('leaves the box open when a form has opened after the bubbles', () => {
    const wrapper = build([
      bubbles([{ id: 'yes', label: 'Yes' }, { id: 'no', label: 'No' }]),
      { id: 'cf', role: 'capture_form', content: '', metadata: { capture_form: { fields: [] } } },
    ]);
    expect(textarea(wrapper).attributes('disabled')).toBeUndefined();
  });
});
