---
id: W-0541
title: Fyn's web chat silently destroys every message a new user sends — no conversation is ever created, and nothing is requested
mission: new-user-run-2026-09-07
branch: feature/icecube/fyn-chat-conversation-bootstrap
owner: null
reviewers: [quality-lead, build-lead]
status: review
severity: critical
surfaces: [web]
created: 2026-09-09
source: new-user run, reproduced on production fynla.org 2026-09-09
prior_art_checked: 2026-09-09
prior_art_found: [W-0113, W-0202]
prior_art_outcome: none — both prior items concern Fyn tools writing data, not the panel failing to open a conversation
constitution_refs: [07-quality-bar]
---

## Intent

**Confirmed on production**, after the dev -> main release (PR #798), with a
brand-new account registered and verified through the live funnel.

Open Fyn, type, press Send. The input clears, the panel stays empty, and **no
network request is made at all** — zero `ai-chat` requests across 106 captured
requests. The message is gone. To the user Fyn is simply dead.

The backend is fine. Driving the API directly on the same account returned a
correct, well-grounded stream:

> "Your net worth is £436,500, calculated from total assets of £685,000 minus
> total liabilities of £248,500."

### Root cause

Live Vuex state, with the panel **visibly open and typed into**:

    aiChat.isOpen:       false
    currentConversation: null
    conversations:       0

`AppLayout` renders the docked desktop panel on `chatCollapsed`, not on
`aiChat.isOpen`, so the panel is visible, focusable and usable while `isOpen`
stays false. `onOpen()` — the only place a conversation is created — runs off
the `isOpen` watcher, so it never fires. `aiChat/sendMessage` then hits
`if (!state.currentConversation) return;` and returns silently, after `send()`
has already cleared the text.

Only bites an account whose conversation list is empty — **every brand-new
user, on their first ever message.** An established account has a conversation
and works normally, which is why it reached production.

## Acceptance

- [ ] A brand-new account can send its first Fyn message on web and get a reply.
- [ ] No send path assumes `onOpen` ran; each establishes a conversation first.
- [ ] A send that cannot proceed leaves the user's text in the box.
- [ ] `aiChat/sendMessage`'s early return is not silent — a user-initiated action
      that cannot proceed surfaces something.
- [ ] Verified on `/m` (already correct — `onboardingChat.js` has
      `ensureConversation`) and on native, which is **not yet verified**.
- [ ] A regression test fails against the unfixed component.

## Working notes

2026-09-09 — Fixed on `feature/icecube/fyn-chat-conversation-bootstrap`, PR #799
to `dev`. Adds `ensureConversation()` called from `send()`, `sendSuggested()`
and both branches of `handleQuickReplySelect()`; input no longer cleared until
the send is going out. Helper deliberately shares the `/m` name (Rule 20).
Browser-verified locally against the exact production state: POST
/api/ai-chat/conversations -> 201, POST .../messages -> 200, reaches
CoordinatingAgent. 4 tests added; 3 fail against the unfixed component. Full
frontend suite identical to the dev baseline. **Native surface not verified.**
