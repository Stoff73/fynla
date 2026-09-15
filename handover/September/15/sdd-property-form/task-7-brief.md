### Task 7: Web store and API — the form event, the form submission, history

**Files:**
- Modify: `resources/js/services/aiChatService.js` — `sendMessageStream` (lines 84-113) and the three sibling SSE fetches (`streamQueuedMessage` :149, `startOnboardingStream` :231, `postActionStream` :292)
- Modify: `resources/js/store/modules/aiChat.js` — mutations near `ADD_MESSAGE` (:116); `loadConversation` normalisation (:414-436); `sendMessage` (:463, its switch at :559); the other three switches (:946, :1191, :1464)
- Test: `tests/frontend/store/aiChatCaptureForm.test.js` (create), modelled on `tests/frontend/store/aiChatEvents.test.js`

**Interfaces:**
- Consumes: SSE events `capture_form {prompt_text, form}`, `capture_form_errors {form, errors}`, `form_received {text}` (Tasks 4-5); header `X-Fynla-Forms: 1` (Task 6)
- Produces:
  - `aiChatService.sendMessageStream(conversationId, message, currentRoute, { signal, form })` — body `{ message, current_route, form }` (`message` omitted when `form` is given)
  - store action `sendMessage(ctx, arg)` where `arg` is a string (today) **or** `{ form }` — one path for both
  - message rows: `{ role: 'capture_form', content: prompt_text, metadata: { capture_form: schema, errors: null } }`
  - mutation `SET_CAPTURE_FORM_ERRORS(state, errors)` — sets `metadata.errors` on the latest `capture_form` row
  - mutation `SET_TEMP_USER_CONTENT(state, { id, content })`

- [ ] **Step 1: Write the failing test**

```js
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
  const state = { ...aiChat.state(), currentConversation: { id: 7 }, messages: [], ...overrides };
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

  it('attaches capture_form_errors to the latest form row', async () => {
    aiChatService.sendMessageStream.mockResolvedValue(streamReader([
      { type: 'capture_form_errors', form: 'property', errors: { buy_to_let: { message: 'You have reached your plan\'s property limit.', fields: {} } } },
      { type: 'done', message_id: 11 },
    ]));
    const ctx = makeCtx({ messages: [{ id: 'cf_1', role: 'capture_form', content: '', metadata: { capture_form: schema, errors: null } }] });

    await aiChat.actions.sendMessage(ctx, { form: { name: 'property', answers: {} } });

    expect(ctx.state.messages[0].metadata.errors.buy_to_let.message).toContain('property limit');
  });

  it('re-renders a persisted form on history load as text plus a form row', async () => {
    aiChatService.getConversation.mockResolvedValue({ data: { data: { id: 7, messages: [
      { id: 40, role: 'assistant', content: 'Now your property.', metadata: { capture_form: schema, onboarding_step: 'campaign_property' }, created_at: 'x' },
    ] } } });
    const ctx = makeCtx();

    await aiChat.actions.loadConversation(ctx, 7);

    const roles = ctx.state.messages.map((m) => m.role);
    expect(roles).toEqual(['assistant', 'capture_form']);
    expect(ctx.state.messages[1].metadata.capture_form).toEqual(schema);
  });
});
```

Read `tests/frontend/store/aiChatEvents.test.js` first and copy its exact `makeCtx`/`getConversation` response shape if it differs from the above — the assertions are what matter.

- [ ] **Step 2: Run to verify it fails**

Run: `npx vitest run tests/frontend/store/aiChatCaptureForm.test.js`
Expected: FAIL — no `capture_form` row; `sendMessageStream` called with a string.

- [ ] **Step 3: Implement**

`aiChatService.js` — add one constant after the imports and use it in all four SSE fetches:

```js
// Declares this client renders Fyn's structured capture forms (the /m
// bundle sends the same header; native does not yet, and gets the typed
// prompt for a form turn instead).
const FORMS_HEADER = { 'X-Fynla-Forms': '1' };
```

In each of the four `fetch(...)` calls add `...FORMS_HEADER,` inside `headers`. Change `sendMessageStream`:

```js
async sendMessageStream(conversationId, message, currentRoute = null, { signal, form = null } = {}) {
    const token = await getToken();
    const body = { current_route: currentRoute };
    if (form) {
        body.form = form;
    } else {
        body.message = message;
    }
    const response = await fetch(`${apiBaseURL}/api/ai-chat/conversations/${conversationId}/messages`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'Authorization': `Bearer ${token}`, ...FORMS_HEADER },
        body: JSON.stringify(body),
        credentials: 'same-origin',
        signal,
    });
```

`aiChat.js` — mutations next to `ADD_MESSAGE`:

```js
SET_TEMP_USER_CONTENT(state, { id, content }) {
    const row = state.messages.find((m) => m.id === id);
    if (row) row.content = content;
},
SET_CAPTURE_FORM_ERRORS(state, errors) {
    for (let i = state.messages.length - 1; i >= 0; i -= 1) {
        if (state.messages[i].role === 'capture_form') {
            state.messages[i].metadata = { ...state.messages[i].metadata, errors };
            return;
        }
    }
},
```

A builder next to `entityWriteMessage`:

```js
function captureFormMessage(event) {
    return {
        id: 'cf_' + Date.now(),
        role: 'capture_form',
        content: event.prompt_text || '',
        metadata: { capture_form: event.form || null, errors: null },
        created_at: new Date().toISOString(),
    };
}
```

`sendMessage` head — accept a string or `{ form }`:

```js
async sendMessage({ commit, dispatch, state, rootState }, arg) {
    if (!state.currentConversation) return;
    const form = arg && typeof arg === 'object' ? (arg.form || null) : null;
    const message = form ? null : arg;
    const displayMessage = form ? 'Saving your property details…' : stripTags(message);
    const tempId = 'temp_' + Date.now();
    commit('ADD_MESSAGE', { id: tempId, role: 'user', content: displayMessage, created_at: new Date().toISOString() });
```

and pass `form` into the service call: `aiChatService.sendMessageStream(state.currentConversation.id, message, currentRoute, { signal: abortController.signal, form })`.

In the `sendMessage` switch (:559) add, next to `case 'quick_replies'`:

```js
case 'form_received':
    commit('SET_TEMP_USER_CONTENT', { id: tempId, content: event.text || '' });
    break;
case 'capture_form':
    if (state.streamingText) {
        commit('ADD_MESSAGE', { id: 'cf_text_' + Date.now(), role: 'assistant', content: state.streamingText, created_at: new Date().toISOString() });
        commit('SET_STREAMING_TEXT', '');
    }
    commit('ADD_MESSAGE', captureFormMessage(event));
    break;
case 'capture_form_errors':
    commit('SET_CAPTURE_FORM_ERRORS', event.errors || {});
    break;
```

Add the same three cases to the switches at :946 (`streamNextQueued`), :1191 (`postAction`) and :1464 (`startOnboardingConversation`). In those three there is no `tempId`; for `form_received` there they do nothing (`break;`) — only a direct form submission has a placeholder row.

History normalisation (:414-436) — before the `if (m.role === 'assistant' && hasBubbles)` branch add:

```js
const captureForm = m?.metadata?.capture_form;
if (m.role === 'assistant' && captureForm && typeof captureForm === 'object') {
    if (m.content) {
        normalised.push({ ...m, metadata: { ...m.metadata, capture_form: undefined } });
    }
    normalised.push({ id: `cf_${m.id}`, role: 'capture_form', content: '', metadata: { capture_form: captureForm, errors: null }, created_at: m.created_at });
    return; // inside the forEach callback; use `continue` if it is a for-loop
}
```

- [ ] **Step 4: Run the tests**

Run: `npx vitest run tests/frontend/store`
Expected: the new file passes and the existing store tests stay green.

- [ ] **Step 5: Commit**

```bash
git add resources/js/services/aiChatService.js resources/js/store/modules/aiChat.js tests/frontend/store/aiChatCaptureForm.test.js
git commit -m "feat(web): the chat store renders capture_form turns and posts form answers through the one send path"
```

---

