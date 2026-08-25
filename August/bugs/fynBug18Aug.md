---
id: fynBug18Aug
raised: 2026-08-17 (evening) — found live by CSJ
closed: 2026-08-18 (early hours)
surface: Fyn write path — web verified live; /m and native by test only
branch: fix/widow-persona-cleanup
severity: blocker (one of them destroyed user data)
status: fixed and verified live; two follow-ups listed at the end
---

# Fyn capture bugs — 17/18 August

Five defects, all reachable from one ordinary conversation. CSJ hit four of them
in a row while checking the evening's work; the fifth was mine, introduced that
same evening and caught by the same run.

Everything below was measured — conversation rows, audit rows, tool calls. No
inference.

---

## What was broken

### 1. A goal request was answered with the prompt-injection refusal

*"I want to save 20000 for a house deposit by June 2030"* →
**"I can only help with financial planning questions. How can I assist with your
finances?"**

`WriteIntentClassifier` matched the incidental word **house** and routed the
message to a *property* capture. The capture turn, told to record a property the
user had never mentioned, fell back to the refusal line in the system prompt. The
goal-precedence guard only looked for the literal nouns "goal" / "savings goal" /
"target", none of which appear in the sentence people actually type.

Evidence: conversation 157 msg #662, `turn_intent: capture_clarification`; log
`[AdviceFyn] Deterministic write-intent routed {"entity_type":"property",
"matched_verb":"save","matched_entity_keyword":"house"}`.

### 2. One missing field was reported as a failure

*"I have a Chase easy access savings account with 5000 in it"* →
**"I couldn't save that — I need you to confirm whether you own it individually
or with someone else. Give me the missing detail and I will try again."**

The model DID call `create_savings_account` with `ownership_type: individual`;
`CaptureAccuracyGate` rejected it because the user's own words never named an
owner (the anti-hallucination rule, working as designed). The wording was the
problem: an apology, a system instruction, and no question mark — for what is
simply one more thing to ask.

Evidence: conversation 157 msg #664, tool result
`error_type: clarification_required`.

### 3. "Recorded." over a write that never happened

*"I own it individually"* → **"Recorded. Your Chase easy access savings account is
now saved as an individual account."** Nothing was written. No savings row, no
audit row.

`AdviceFyn::captureContinuationIntent` decided whether a capture was still pending
from two proxies: the presence of tool calls, and a question mark in the previous
turn's text. A capture that CALLED a write tool and had it rejected looks
identical to one that succeeded, and our own failure copy (defect 2) has no
question mark — so both proxies said "concluded", the answer routed to read-only
advice, and the model narrated a save on a turn that made no write.

Evidence: conversation 157 msg #666 — tool calls were `get_module_analysis`,
`get_tax_information`, `get_tax_information`. No write tool. `savings_accounts`
unchanged.

### 4. An existing goal was silently overwritten — MY REGRESSION

*"high priority, 300 a month"*, answering Fyn's question about a NEW house-deposit
goal, edited a **different, pre-existing goal**:

```
old: target_amount 25000.00, target_date 2030-12-31, monthly_contribution 0.00
new: target_amount 20000,    target_date 2030-06-30, monthly_contribution 300
```

CSJ's rule — answering Fyn's own outstanding question about a record IS an
explicit edit — was implemented scoped to the ENTITY TYPE. Harmless while only
`handleCreatePension` used it. Extending the recapture guard to the other
eighteen handlers earlier that evening turned it into data loss: the permission
granted for "a goal" applied to any goal whose name matched.

Evidence: `audit_logs` id 55775, `action: updated`, `model_type: App\Models\Goal`,
`model_id: 2240`. Restored from that row.

### 5. The same question asked twice, and the new request dropped

After Fyn asked *"is this the same House Deposit goal, or a separate one?"*, the
next message — *"I have a Chase savings account…"* — produced the identical
`create_goal` call again, the identical block, and the identical question. The
savings account was never captured.

Evidence: conversation 160 msg #678, `tools: create_goal`, tool result
`confirm_edit_required` on entity_id 2240.

### Also caught live, before it reached CSJ

`AiChatPanel.vue` crashed the whole chat panel to a white box —
`isEntityWriteRole is not defined`, a missing `this.` in `messageClass()` from
that evening's work. Found in the console during the first live run, fixed, panel
verified rendering again.

---

## What we fixed

| # | Fix | Where |
|---|---|---|
| 1 | The classifier reads saving TOWARDS something as a goal, including with the amount in between ("save 20000 for …"), which a keyword list cannot express | `WriteIntentClassifier` |
| 2 | A reason already phrased as a question is asked plainly, with no apology wrapper; the ownership prompt is now "Is this in your name only, or do you share it with someone else?" | `CaptureAccuracyGate`, `OnboardingChatDirector::captureFailureText` |
| 3 | The turn records whether a write actually landed (`capture_write_landed`); the continuation reads that fact instead of guessing from punctuation | `HasAiChat`, `AdviceFyn::captureContinuationIntent` |
| 4 | The explicit-edit permission carries the record id it was granted for and applies to nothing else, end to end: `CaptureContext` → `FynLoop` → agent → guard | `CaptureContext`, `FynLoop`, `HasAiChat`, `RecaptureGuard`, `CoordinatingAgent` |
| 5 | A repeat of an already-asked question carries no user-facing text and tells the model to deal with the message actually sent — only when the user has genuinely changed subject | `CoordinatingAgent::suppressRepeatedAsk` |

Fix 5 needed a second pass. The first cut suppressed on any repeat, which broke
the legitimate case: answering the model's own follow-up re-runs the same blocked
call, and there the question has NOT been put to the user yet — they got a bare
"the information could not be saved" instead. It now fires only when the latest
user message classifies to a different entity type. Both directions are pinned by
tests.

The overwritten goal (defect 4) was restored to £25,000 / 2030-12-31 from the
audit row.

---

## How we tested

**Live, in the browser** — the point of the exercise; every one of these was found
live and closed live.

One conversation, three turns, as john@example.com on localhost:8000:

| Sent | Before | After |
|---|---|---|
| "I want to save 20000 for a house deposit by June 2030" | "I can only help with financial planning questions." | "I can help you add that goal. What's the monthly amount you plan to contribute?" (`create_goal`) |
| "300 a month, high priority" | silently overwrote a £25,000 goal | "You already have a goal recorded as 'House Deposit', with different details. Is this the same goal you'd like me to update, or a separate one?" — £25,000 untouched |
| "I have a Chase savings account… I own individually" | repeated the goal question, savings ignored | handled as savings (`create_savings_account`); free-tier cap reached, Compare plans offered |

Also verified live: a pension capture writes (`DCPension` 969, Shepherds Friendly,
£12,000, type `personal`), the retirement page shows it, and projections compute
(£20,703) rather than returning £0 or a 500.

**Automated**
- 1,287 tests green across `tests/Feature/AI`, `tests/Feature/Fyn`,
  `tests/Feature/Onboarding`, `tests/Unit/Agents`, `tests/Architecture`
- 149 in the direct-write suite, including a per-entity fill/ask/ask matrix and
  both directions of the repeat rule
- 54 frontend (vitest) — note this needs **node 20**; the repo's default node 18
  cannot run the suite at all
- 6 native reducer tests on the iPhone 16 simulator, covering the widened decoder

**Each new architecture test was proven to bite** by temporarily breaking the
thing it guards and watching it name the offender.

---

## Where we are

Branch `fix/widow-persona-cleanup`, 47 commits ahead of `origin/dev`, tree clean,
nothing deployed anywhere.

The six commits from this session:

```
b604fdd3b  one recapture guard for all 19 create handlers, not one per handler
fbcc403c0  every write handler now names the record it wrote
0de80b11b  handler messages are Fyn's vocabulary too, so scan them
647a609dd  the post-save link, resolved once on the server, used by all three clients
4ffe24d56  an edit permission names a record, not a type — and three live capture defects
1b80b429c  a question Fyn has already asked is not asked again when the user moves on
```

**Verified on web. NOT yet exercised on `/m` or native** — both consume the new
event shape and both have tests, but neither has been clicked. Rule 19 says that
is not done until it has been.

---

## Follow-ups, not fixed here

- **C5 has no mechanical guard.** Defect 3 is fixed at its source, but nothing
  structurally prevents a turn from narrating a save it did not make. CSJ's call
  on 2026-08-17 was to leave it as a prompt rule for now.
- **The update path has no recapture guard.** The guard covers the 19 create
  handlers; `update_record` writes what it is told.
- **The level-up celebration would not dismiss** on "Keep going" during testing —
  only navigating away cleared it. Mouse events were not reaching the page at that
  point, so this may be an artefact of the automation rather than a real defect.
  Worth one manual check.
- **John (`john@example.com`) is at his free-tier caps** — 2 pensions, 2 savings
  accounts — so those captures now return the upgrade message. Use a paid persona
  for capture journeys.
