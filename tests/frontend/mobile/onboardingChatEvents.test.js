import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { apiStream } from '../../../resources/mobile/api.js';
import onboardingChat from '../../../resources/mobile/mixins/onboardingChat.js';
import { store } from '../../../resources/mobile/store.js';

function makeHarness() {
  const reply = { role: 'fyn', text: '', bubbles: [] };
  const cursor = { reply, got: false, navigation: null };
  const vm = {
    messages: [reply],
    conversationId: null,
    resumeId: null,
    $route: { path: '/dashboard' },
    $nextTick: (callback) => callback(),
    scrollFyn: vi.fn(),
  };

  return {
    cursor,
    vm,
    handle(event) {
      onboardingChat.methods.handleFynEvent.call(vm, cursor, event);
    },
  };
}

describe('/m Fyn stream event parity', () => {
  beforeEach(() => {
    store.user = null;
  });

  afterEach(() => {
    store.user = null;
    vi.unstubAllGlobals();
  });

  it('forwards every typed stream event, including frames after done', async () => {
    const events = [
      { type: 'entity_created', name: 'Cash ISA' },
      { type: 'capture_complete', summary: 'Your Cash ISA has been added.' },
      { type: 'done', message_id: 42 },
      { type: 'level_up', level: 3 },
    ];
    const payload = `${events.map((event) => `data: ${JSON.stringify(event)}`).join('\n\n')}\n\n`;
    const chunks = [new TextEncoder().encode(payload)];

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      body: {
        getReader: () => ({
          read: vi.fn(async () => (
            chunks.length > 0
              ? { done: false, value: chunks.shift() }
              : { done: true, value: undefined }
          )),
        }),
      },
    }));

    const received = [];
    const result = await apiStream('/api/ai-chat/conversations/7/messages', {
      message: 'Add my Cash ISA',
    }, 'test-token', null, (event) => received.push(event));

    expect(result).toMatchObject({ ok: true, status: 200 });
    expect(received).toEqual(events);
  });

  it('matches the server onboarding predicate including campaign re-entry and the null-step guard', () => {
    const isActive = () => onboardingChat.computed.onboardingActive.call({});

    store.user = { onboarding_completed: false, active_campaign: null, onboarding_fyn_step: 'path_choice' };
    expect(isActive()).toBe(true);

    store.user = { onboarding_completed: false, active_campaign: null, onboarding_fyn_step: null };
    expect(isActive()).toBe(false);

    store.user = { onboarding_completed: true, active_campaign: 'savetax', onboarding_fyn_step: 'campaign_income' };
    expect(isActive()).toBe(true);

    store.user = { onboarding_completed: true, active_campaign: 'savetax', onboarding_fyn_step: null };
    expect(isActive()).toBe(false);
  });

  it.each([
    [
      'token_limit',
      { type: 'token_limit', message: "You've reached your daily Fyn usage limit." },
      "You've reached your daily Fyn usage limit.",
    ],
    [
      'consent_required',
      { type: 'consent_required', required: 'ai_chat' },
      'Artificial intelligence chat consent has been withdrawn. Contact Fynla support to restore your artificial intelligence features.',
    ],
    [
      'handoff_error',
      { type: 'handoff_error', message: "I couldn't pick up that request — could you try again?" },
      "I couldn't pick up that request — could you try again?",
    ],
    [
      'typed error',
      { type: 'error', message: 'The pension could not be saved.' },
      'The pension could not be saved.',
    ],
    [
      'entity_created',
      { type: 'entity_created', entity_type: 'savings_account', entity_id: 7, name: 'Cash ISA' },
      'Saved Cash ISA.',
    ],
    [
      'capture_complete',
      { type: 'capture_complete', summary: 'Your Cash ISA has been added.', records_created: [] },
      'Your Cash ISA has been added.',
    ],
  ])('renders %s as specific plain text', (_name, event, expectedText) => {
    const harness = makeHarness();

    harness.handle(event);

    expect(harness.cursor.got).toBe(true);
    expect(harness.vm.messages.map((message) => message.text)).toContain(expectedText);
  });

  it('renders skip_link as a usable director action bubble', () => {
    const harness = makeHarness();

    harness.handle({
      type: 'skip_link',
      skip_link: { label: 'Skip this for now', color: 'raspberry' },
    });

    expect(harness.cursor.reply.bubbles).toEqual([
      { id: 'skip', label: 'Skip this for now' },
    ]);
    expect(harness.cursor.reply.actionBubbles).toBe(true);
    expect(harness.cursor.got).toBe(true);
  });

  it('collapses entity_created followed by capture_complete into one confirmation', () => {
    const harness = makeHarness();

    harness.handle({
      type: 'entity_created',
      entity_type: 'savings_account',
      entity_id: 7,
      name: 'Cash ISA',
    });
    onboardingChat.methods.appendFynText.call(
      harness.vm,
      harness.cursor,
      'A Cash ISA keeps the interest tax-free.',
    );
    harness.handle({
      type: 'capture_complete',
      summary: 'Saved to your records',
      records_created: [{ id: 7 }],
    });

    expect(harness.vm.messages.map((message) => message.text).filter(Boolean))
      .toEqual([
        'A Cash ISA keeps the interest tax-free.',
        'Saved to your records',
      ]);
  });

  it('queues a level-up received from an onboarding action stream', async () => {
    const queueCelebration = vi.spyOn(store, 'queueCelebration').mockImplementation(() => {});
    const vm = {
      conversationId: 7,
      sending: false,
      messages: [],
      $nextTick: (callback) => callback(),
      scrollFyn: vi.fn(),
      streamFynAction: vi.fn(async (_conversationId, _action, cursor) => {
        cursor.levelUp = { level: 3, level_name: 'Building', next_actions: [] };
      }),
      finalizeCaptureReply: vi.fn(),
      handleOnboardingNavigation: vi.fn(),
      pulseWheel: vi.fn(),
    };

    await onboardingChat.methods.runFynAction.call(vm, 'skip');

    expect(queueCelebration).toHaveBeenCalledWith({
      level: 3,
      level_name: 'Building',
      next_actions: [],
    });
    expect(vm.pulseWheel).toHaveBeenCalledOnce();
  });

  it('restores a persisted skip link as an action bubble', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: vi.fn().mockResolvedValue({
        data: {
          messages: [{
            role: 'assistant',
            content: 'Tell me about your spouse.',
            metadata: {
              bubbles: [{ id: 'yes', label: 'Yes' }],
              skip_link: { label: 'Skip this for now' },
            },
          }],
        },
      }),
    }));
    const vm = {
      messages: [],
      $nextTick: (callback) => callback(),
      scrollFyn: vi.fn(),
    };

    await onboardingChat.methods.loadTranscript.call(vm, 7);

    expect(vm.messages).toEqual([
      expect.objectContaining({
        bubbles: [
          { id: 'yes', label: 'Yes' },
          { id: 'skip', label: 'Skip this for now' },
        ],
        actionBubbles: false,
      }),
    ]);

    const message = vm.messages[0];
    const actionVm = {
      sending: false,
      send: vi.fn(),
      runFynAction: vi.fn(),
      handleOnboardingNavigation: vi.fn(),
    };
    onboardingChat.methods.chooseBubble.call(actionVm, message.bubbles[0], message);
    onboardingChat.methods.chooseBubble.call(actionVm, message.bubbles[1], message);

    expect(actionVm.send).toHaveBeenCalledWith('Yes');
    expect(actionVm.runFynAction).toHaveBeenCalledWith('skip');
  });
});
