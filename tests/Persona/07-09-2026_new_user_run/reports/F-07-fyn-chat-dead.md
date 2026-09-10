# F-07 · Fyn web chat silently swallows every message — CRITICAL

> **CONFIRMED ON PRODUCTION 2026-09-09** (after the dev -> main release, PR #798).
> Reproduced end to end on fynla.org with a brand-new account: registered,
> verified, opened Fyn, typed, pressed Send. Input cleared, panel stayed empty,
> and **zero ai-chat requests** across 106 captured requests.
>
> Root cause refined by reading live Vuex state: with the panel **visibly open
> and typed into**, the store held `aiChat.isOpen: false`,
> `currentConversation: null`, `conversations: 0`. AppLayout renders the docked
> panel on `chatCollapsed`, not on `isOpen`, so the panel is usable while the
> flag that gates `onOpen()` — the only place a conversation is created — never
> flips. `sendMessage` then hits its silent `return`.
>
> **Fixed** on `feature/icecube/fyn-chat-conversation-bootstrap`.

**Env:** csjones staging · **Surface:** desktop web
**Account:** new user (Alex Whitfield), zero prior conversations.

## Symptom

Fyn's chat panel opens, accepts typing, and the Send button is enabled.
On Send the textarea clears — and **nothing else happens**. No message bubble,
no response, no error, and **no network request whatsoever**.

To the user, Fyn is completely dead. Their message is destroyed silently.

## Evidence (clean experiment, fresh page load, real clicks only)

- Typed "Introduce yourself" (no navigation keywords).
- `canSend === true` (Send button not disabled) → so `inputMessage` was populated.
- Clicked Send → textarea cleared to `""`, proving `send()` executed.
- `read_network_requests` filtered on `ai-chat`: **no request from the UI at any
  point** — not on panel open, not on send. The only ai-chat requests in the log
  were ones I issued manually via fetch.
- Console: no error raised at send time.
- Repeated after a full page reload. Identical.

## The backend is fine — proven separately

Driving the API directly with the session token:

- `GET  /api/ai-chat/conversations` → **200** (`data: []` — user had none)
- `POST /api/ai-chat/conversations` → **201** (id 206)
- `POST /api/ai-chat/conversations/206/messages` → **200 text/event-stream**

The stream returned correct, well-grounded output:

> "Your **net worth** is **£436,500**, calculated from total assets of
> **£685,000** minus total liabilities of **£248,500**. Your mortgage balance on
> **42 Kingsway** is **£248,500**."

Figures match entered data exactly, arithmetic correct, names the real property,
plain text with no icons or emoji (Rule 15 clean), proper
`thinking → title → content → done` event sequence.

**So: Fyn's brain works. Fyn's web UI never talks to it.**

## Root cause

`resources/js/store/modules/aiChat.js:445-446`

```js
async sendMessage({ commit, dispatch, state, rootState }, message) {
    if (!state.currentConversation) return;   // silent bail, no error, no create
```

`resources/js/components/Shared/AiChatPanel.vue:1119-1152` — `send()` clears the
input (line 1124) then calls `sendMessage` **without ensuring a conversation
exists**. The panel also never fetches the conversation list on open (confirmed:
zero network activity on open).

So `currentConversation` is `null` for a new user and stays `null`. Every send
hits the guard and returns. The input has already been cleared, so the message
is unrecoverable.

The correct pattern already exists elsewhere in the same component
(`AiChatPanel.vue:1086-1088`):

```js
if (!this.currentConversation) {
    ...create...
    if (!this.currentConversation) return; // create failed — leave prefill for retry
}
```

`send()` simply lacks it.

## Why this escapes notice

An account that already has a conversation loads one and works normally. The
failure is specific to a user whose conversation list is empty — i.e. **every
brand-new user, on their first ever message.** That is the worst possible
population to break, and the hardest to notice in day-to-day use of an
established test account.

Confirmed the fault is not merely "no conversation exists": after I created
conversation 206 via the API, the panel *still* made no request on open or send.
It never loads a conversation at all.

## Fix direction

1. In `send()`, create-or-load a conversation before dispatching `sendMessage`,
   mirroring the guard at `AiChatPanel.vue:1086`.
2. Make `sendMessage`'s early return non-silent — it should surface an error, not
   discard input. A silent `return` on a user-initiated action is the defect
   behind the defect.
3. Do not clear `inputMessage` until the send is known to have been accepted, so
   a failure never destroys the user's text.
4. Verify on `/m` and native iOS — both use the same one endpoint
   (`POST /api/ai-chat/conversations/{id}/messages`), so the surface-specific
   question is whether their panels create a conversation. **NOT yet tested.**
