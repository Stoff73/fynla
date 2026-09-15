import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@/services/aiChatService', () => ({
  default: { sendMessageStream: vi.fn(), streamQueuedMessage: vi.fn(), getConversation: vi.fn() },
}));
vi.mock('@/services/analyticsService', () => ({ default: { trackChatMessageSent: vi.fn() } }));

import aiChat from '@/store/modules/aiChat';
import aiChatService from '@/services/aiChatService';

function streamReader(events) {
  const payload = `${events.map((event) => `data: ${JSON.stringify(event)}`).join('\n\n')}\n\n`;
  const chunks = [new TextEncoder().encode(payload)];
  return { read: vi.fn(async () => (chunks.length > 0 ? { done: false, value: chunks.shift() } : { done: true, value: undefined })) };
}

const schema = { name: 'property', submit_label: 'Save', kinds: [{ key: 'main_residence', label: 'Home', fields: ['current_value'] }], fields: { current_value: { type: 'money', label: 'Value', required: true } } };

function makeCtx(overrides = {}) {
  const state = { ...aiChat.state, currentConversation: { id: 7 }, messages: [], ...overrides };
  const commit = vi.fn((type, payload) => { if (aiChat.mutations[type]) aiChat.mutations[type](state, payload); });
  const dispatch = vi.fn();
  return { state, commit, dispatch, rootState: { auth: { user: { id: 1 } } } };
}

describe('capture form in the chat store', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders a capture_form event as a form row and never trips the empty-response banner', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'capture_form', prompt_text: 'Now your property.', form: schema },
      { type: 'done', message_id: 9 },
    ]));
    const ctx = makeCtx();

    await aiChat.actions.sendMessage(ctx, 'Continue');

    const row = ctx.state.messages.find((m) => m.role === 'capture_form');
    expect(row).toBeTruthy();
    expect(row.content).toBe('Now your property.');
    expect(row.metadata.capture_form).toEqual(schema);
    expect(ctx.state.error).toBeNull();
  });

  it('posts a form answer with the forms header and no message, then replaces the placeholder user row', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'form_received', text: 'Home worth £750,000, no mortgage, individual.' },
      { type: 'done', message_id: 10 },
    ]));
    const ctx = makeCtx();
    const form = { name: 'property', answers: { main_residence: { current_value: 750000, mortgage_outstanding_balance: null, ownership_type: 'individual' } } };

    await aiChat.actions.sendMessage(ctx, { form });

    expect(aiChatService.sendMessageStream).toHaveBeenCalledWith(7, null, expect.anything(), expect.objectContaining({ form }));
    const userRow = ctx.state.messages.find((m) => m.role === 'user');
    expect(userRow.content).toBe('Home worth £750,000, no mortgage, individual.');
  });

  it('attaches capture_form_errors to the latest form row and never trips the empty-response banner', async () => {
    // The director always yields form_received before the errors on a
    // refused submission (OnboardingChatDirector.php:3690 then :3697/:3740)
    // — neither event pushes a new message, so this is the sequence that
    // previously fell through to the "Fyn couldn't generate a response"
    // banner despite the form errors rendering correctly underneath it.
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'form_received', text: 'A buy-to-let worth £300,000.' },
      { type: 'capture_form_errors', form: 'property', errors: { buy_to_let: { message: 'You have reached your plan\'s property limit.', fields: {} } } },
      { type: 'done', message_id: 11 },
    ]));
    const ctx = makeCtx({ messages: [{ id: 'cf_1', role: 'capture_form', content: '', metadata: { capture_form: schema, errors: null } }] });

    await aiChat.actions.sendMessage(ctx, { form: { name: 'property', answers: {} } });

    expect(ctx.state.messages[0].metadata.errors).not.toBeNull();
    expect(ctx.state.messages[0].metadata.errors.buy_to_let.message).toContain('property limit');
    expect(ctx.state.error).toBeNull();
  });

  it('clears a prior refusal\'s errors once form_received confirms a successful retry', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'form_received', text: 'Home worth £750,000, no mortgage, individual.' },
      { type: 'done', message_id: 12 },
    ]));
    const ctx = makeCtx({ messages: [{ id: 'cf_1', role: 'capture_form', content: '', metadata: { capture_form: schema, errors: { buy_to_let: { message: 'You have reached your plan\'s property limit.', fields: {} } } } }] });

    await aiChat.actions.sendMessage(ctx, { form: { name: 'property', answers: { main_residence: { current_value: 750000 } } } });

    expect(ctx.state.messages[0].metadata.errors).toBeNull();
  });

  it('re-renders a persisted form on history load as text plus a form row', async () => {
    aiChatService.getConversation.mockResolvedValue({ data: { conversation: { id: 7 }, messages: [
      { id: 40, role: 'assistant', content: 'Now your property.', metadata: { capture_form: schema, onboarding_step: 'campaign_property' }, created_at: 'x' },
    ] } });
    const ctx = makeCtx();

    await aiChat.actions.loadConversation(ctx, 7);

    const roles = ctx.state.messages.map((m) => m.role);
    expect(roles).toEqual(['assistant', 'capture_form']);
    expect(ctx.state.messages[1].metadata.capture_form).toEqual(schema);
  });
});
