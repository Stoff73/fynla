import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({
  apiGet: vi.fn(),
}));

import { apiGet } from '../../api.js';
import { store } from '../../store.js';
import ConversationHistory from '../ConversationHistory.vue';

const openConversation = vi.fn(() => Promise.resolve(42));
const revealLoadedConversation = vi.fn(() => Promise.resolve());

const MobileChromeStub = {
  props: ['title', 'subtitle', 'loading', 'loadingLabel', 'editDetails'],
  methods: { openConversation, revealLoadedConversation },
  template: '<main :data-loading="loading"><h1>{{ title }}</h1><slot /></main>',
};

const history = [
  {
    id: 7,
    title: 'Your Fynla setup',
    mode: 'onboarding',
    purpose: 'Complete your Fynla setup',
    related_entity: null,
    status: 'paused',
    last_message_at: '2026-08-09T12:00:00+01:00',
    last_message_summary: 'Let us continue setting up your plan.',
    fallback_destination: { screen: 'dashboard', params: {}, fallback: 'dashboard' },
  },
  {
    id: 42,
    title: 'Edit Rainy Day Account',
    mode: 'contextual',
    purpose: 'Edit Bank Account',
    related_entity: {
      type: 'savings_account', id: 4, label: 'Rainy Day Account', available: true, explanation: null,
    },
    status: 'active',
    last_message_at: '2026-08-10T09:30:00+01:00',
    last_message_summary: 'Tell me what has changed.',
    fallback_destination: { screen: 'savings', params: {}, fallback: 'dashboard' },
  },
  {
    id: 43,
    title: 'Edit former account',
    mode: 'contextual',
    purpose: 'Edit Bank Account',
    related_entity: {
      type: 'savings_account', id: 5, label: null, available: false,
      explanation: 'This related item is no longer available.',
    },
    status: 'paused',
    last_message_at: '2026-08-08T09:30:00+01:00',
    last_message_summary: 'A previous update.',
    fallback_destination: { screen: 'savings', params: {}, fallback: 'dashboard' },
  },
  {
    id: 8,
    title: 'General question',
    mode: 'general',
    purpose: 'General Fyn conversation',
    related_entity: null,
    status: 'active',
    last_message_at: '2026-08-07T09:30:00+01:00',
    last_message_summary: 'How should I plan an emergency fund?',
    fallback_destination: { screen: 'dashboard', params: {}, fallback: 'dashboard' },
  },
];

function mountView() {
  const push = vi.fn();
  const wrapper = mount(ConversationHistory, {
    global: {
      mocks: {
        $router: { push },
        $route: { path: '/conversation-history', query: {} },
      },
      stubs: { MobileChrome: MobileChromeStub },
    },
  });

  return { wrapper, push };
}

describe('ConversationHistory.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    openConversation.mockResolvedValue(42);
    revealLoadedConversation.mockResolvedValue();
    store.token = 'live-token';
    store.user = { id: 1, onboarding_completed: true };
    apiGet.mockResolvedValue({ ok: true, status: 200, data: { data: history } });
  });

  it('renders separate server-projected modes and useful safe metadata', async () => {
    const { wrapper } = mountView();
    await flushPromises();

    expect(apiGet).toHaveBeenCalledWith('/api/ai-chat/conversations', 'live-token');
    expect(wrapper.get('[data-testid="history-onboarding"]').text()).toContain('Complete your Fynla setup');
    expect(wrapper.get('[data-testid="history-contextual"]').text()).toContain('Edit Bank Account');
    expect(wrapper.get('[data-testid="history-contextual"]').text()).toContain('Rainy Day Account');
    expect(wrapper.get('[data-testid="history-contextual"]').text()).toContain('Active');
    expect(wrapper.get('[data-testid="history-contextual"]').text()).toContain('Tell me what has changed.');
    expect(wrapper.get('[data-testid="history-general"]').text()).toContain('General Fyn conversation');
  });

  it('opens the exact selected transcript without creating or resuming another conversation', async () => {
    const { wrapper } = mountView();
    await flushPromises();

    await wrapper.get('[data-testid="open-conversation-42"]').trigger('click');
    await flushPromises();

    expect(openConversation).toHaveBeenCalledWith(42);
    expect(revealLoadedConversation).toHaveBeenCalledOnce();
  });

  it('shows the server explanation for an unavailable entity and uses its semantic fallback', async () => {
    const { wrapper, push } = mountView();
    await flushPromises();

    expect(wrapper.get('[data-testid="conversation-43"]').text())
      .toContain('This related item is no longer available.');
    expect(wrapper.find('[data-testid="open-conversation-43"]').exists()).toBe(false);
    await wrapper.get('[data-testid="fallback-conversation-43"]').trigger('click');

    expect(push).toHaveBeenCalledWith('/savings');
  });

  it('keeps history visible and retryable when the transcript GET fails', async () => {
    openConversation.mockResolvedValueOnce(null);
    const { wrapper } = mountView();
    await flushPromises();

    await wrapper.get('[data-testid="open-conversation-42"]').trigger('click');
    await flushPromises();

    expect(revealLoadedConversation).not.toHaveBeenCalled();
    expect(wrapper.get('[data-testid="conversation-open-error"]').text())
      .toContain('could not open');
    expect(wrapper.get('[data-testid="open-conversation-42"]').exists()).toBe(true);
  });

  it('renders loading, empty, and retryable error states', async () => {
    let resolveLoad;
    apiGet.mockReturnValueOnce(new Promise((resolve) => { resolveLoad = resolve; }));
    const pending = mountView().wrapper;
    expect(pending.findComponent(MobileChromeStub).props('loading')).toBe(true);
    resolveLoad({ ok: true, status: 200, data: { data: [] } });
    await flushPromises();
    expect(pending.text()).toContain('No conversations yet.');

    apiGet
      .mockResolvedValueOnce({ ok: false, status: 503, data: {} })
      .mockResolvedValueOnce({ ok: true, status: 200, data: { data: history } });
    const failed = mountView().wrapper;
    await flushPromises();
    expect(failed.get('[role="alert"]').text()).toContain('We could not load your conversations.');
    await failed.get('[data-testid="conversation-history-retry"]').trigger('click');
    await flushPromises();
    expect(failed.text()).toContain('Edit Bank Account');
  });
});
