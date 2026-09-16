### Task 9: `/m` transport and mixin — the form event, the submission, history

**Files:**
- Modify: `resources/mobile/api.js` — `apiStream` headers (:101-110)
- Modify: `resources/mobile/mixins/onboardingChat.js` — `handleFynEvent` (:360-556), `send` (:583-637), `loadConversationTranscript` (:260-308)
- Test: `resources/mobile/mixins/__tests__/onboardingChat.spec.js` (append)

**Interfaces:**
- Consumes: the same three events as Task 7; header `X-Fynla-Forms: 1`
- Produces:
  - `/m` message rows gain an optional `form` field: `{ role: 'fyn', text, bubbles, form: { schema, errors: null, answers: null, locked: false } }`
  - mixin method `submitCaptureForm(form)` — the same `send` path with `{ form }` instead of `{ message }`

- [ ] **Step 1: Write the failing tests** (append to the spec, reusing its `Host` and mocked `apiStream`)

```js
describe('capture forms', () => {
  const schema = { name: 'property', submit_label: 'Save', kinds: [{ key: 'main_residence', label: 'Home', fields: ['current_value'] }], fields: { current_value: { type: 'money', label: 'Value', required: true } } };

  it('renders a capture_form event as a form on the Fyn row and marks the reply as received', () => {
    const w = mount(Host);
    const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false };
    w.vm.messages = [cursor.reply];
    w.vm.handleFynEvent(cursor, { type: 'capture_form', prompt_text: 'Now your property.', form: schema });
    expect(cursor.got).toBe(true);
    expect(cursor.reply.text).toBe('Now your property.');
    expect(cursor.reply.form.schema).toEqual(schema);
    expect(cursor.reply.form.locked).toBe(false);
  });

  it('posts a form answer with the forms header and no message, and replaces the placeholder user row', async () => {
    const { apiStream } = await import('../../api.js');
    apiStream.mockImplementation(async (path, body, token, onDelta, onEvent) => {
      onEvent({ type: 'form_received', text: 'Home worth £750,000, no mortgage, individual.' });
      onEvent({ type: 'done' });
      return { ok: true, status: 200, text: '' };
    });
    const w = mount(Host);
    w.vm.conversationId = 7;
    const form = { name: 'property', answers: { main_residence: { current_value: 750000 } } };

    await w.vm.submitCaptureForm(form);

    const body = apiStream.mock.calls.at(-1)[1];
    expect(body.form).toEqual(form);
    expect(body.message).toBeUndefined();
    const user = w.vm.messages.find((m) => m.role === 'user');
    expect(user.text).toBe('Home worth £750,000, no mortgage, individual.');
  });

  it('locks every earlier form and attaches errors to the latest', () => {
    const w = mount(Host);
    const row = { role: 'fyn', text: 'x', bubbles: [], form: { schema, errors: null, answers: null, locked: false } };
    w.vm.messages = [row];
    w.vm.handleFynEvent({ reply: row, got: true }, { type: 'capture_form_errors', form: 'property', errors: { main_residence: { message: 'Too many', fields: {} } } });
    expect(row.form.errors.main_residence.message).toBe('Too many');
  });

  it('re-renders a persisted form from history as a locked-or-open form row', async () => {
    const { apiGet } = await import('../../api.js');
    apiGet.mockResolvedValueOnce({ ok: true, status: 200, data: { data: { messages: [
      { role: 'assistant', content: 'Now your property.', metadata: { capture_form: schema } },
    ] } } });
    const w = mount(Host);
    w.vm.conversationId = 7;
    await w.vm.loadConversationTranscript();
    expect(w.vm.messages.at(-1).form.schema).toEqual(schema);
    expect(w.vm.messages.at(-1).form.locked).toBe(false);
  });
});
```

Copy the exact `apiGet` response envelope the existing `loadConversationTranscript` test in that spec uses.

- [ ] **Step 2: Run to verify they fail**

Run: `npx vitest run resources/mobile/mixins`
Expected: the four new cases fail.

- [ ] **Step 3: Implement**

`api.js` `apiStream` headers — add `'X-Fynla-Forms': '1',` after `'Accept': 'text/event-stream',`. (One line; the GET/POST helpers do not need it.)

`onboardingChat.js` — in `handleFynEvent`, before the `quick_replies` branch:

```js
if (ev.type === 'form_received') {
  const placeholder = [...this.messages].reverse().find((m) => m.role === 'user' && m.formPlaceholder);
  if (placeholder) { placeholder.text = ev.text || placeholder.text; delete placeholder.formPlaceholder; }
  return;
}
if (ev.type === 'capture_form') {
  // A form turn. Like quick_replies: a fresh row if the cursor already
  // carries streamed text; cursor.got so the empty-response trap is quiet.
  if (cursor.reply.text) {
    cursor.reply = { role: 'fyn', text: '', bubbles: [] };
    this.messages.push(cursor.reply);
  }
  cursor.got = true;
  if (ev.prompt_text) cursor.reply.text = ev.prompt_text;
  cursor.reply.form = { schema: ev.form || null, errors: null, answers: null, locked: false };
  this.$nextTick(this.scrollFyn);
  return;
}
if (ev.type === 'capture_form_errors') {
  const latest = [...this.messages].reverse().find((m) => m.form);
  if (latest) latest.form = { ...latest.form, errors: ev.errors || {} };
  return;
}
```

`send` — split its body so a form can reuse it. Change the signature and the two places that read the text:

```js
async send(text = null, form = null) {
  const draft = form ? '' : (text ?? this.draft).trim();
  if (!form && !draft) return;
  if (this.sending) return;
  this.draft = '';
  this.messages.forEach((m) => { m.bubbles = []; if (m.form) m.form = { ...m.form, locked: true }; });
  this.messages.push(form
    ? { role: 'user', text: 'Saving your property details…', bubbles: [], formPlaceholder: true }
    : { role: 'user', text: draft, bubbles: [] });
  // …unchanged down to the apiStream call, whose body becomes:
  const body = { current_route: (this.$route && this.$route.path) || '/dashboard' };
  if (form) body.form = form; else body.message = draft;
  const result = await apiStream(`/api/ai-chat/conversations/${cid}/messages`, body, store.token, (piece) => { this.appendFynText(cursor, piece); }, (ev) => this.handleFynEvent(cursor, ev));
```

Add the method:

```js
submitCaptureForm(form) {
  return this.send(null, form);
},
```

`loadConversationTranscript` — in the `map`, read the persisted schema and lock every form but the last row's:

```js
const captureForm = metadata.capture_form && typeof metadata.capture_form === 'object' ? metadata.capture_form : null;
return {
  role: m.role === 'user' ? 'user' : 'fyn',
  text: m.content || '',
  bubbles,
  actionBubbles: Boolean(metadata.action_bubbles),
  ...(captureForm ? { form: { schema: captureForm, errors: null, answers: metadata.form_answers || null, locked: false } } : {}),
};
```

and after `mapped.forEach((m, i) => { if (i < mapped.length - 1) m.bubbles = []; });` add:

```js
mapped.forEach((m, i) => { if (m.form && i < mapped.length - 1) m.form = { ...m.form, locked: true }; });
```

- [ ] **Step 4: Run the tests**

Run: `npx vitest run resources/mobile/mixins`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add resources/mobile/api.js resources/mobile/mixins/onboardingChat.js resources/mobile/mixins/__tests__/onboardingChat.spec.js
git commit -m "feat(m): the onboarding chat mixin renders capture_form turns and posts form answers through the one send path"
```

---

