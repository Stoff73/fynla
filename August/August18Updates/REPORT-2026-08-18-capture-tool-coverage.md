---
type: report
date: 2026-08-18
branch: fyn/crud-contract-followups
covers: the TestFlight consent lockout, the budgeting refusal loop, the spouse-link dead end
---

# Report — three live dead ends, and the class of bug behind two of them

Three tester-reported failures, all of them loops the user could not escape. Two
share one root cause: **a turn that asks the user for something it has no tool to
record**. The third is a lookup that ignored soft deletes.

---

## 1. "Fyn chat consent is required before you can continue"

**Reported:** TestFlight screenshot, a dead-end message with nothing to tap.

**Not a native bug.** `ai_chat` consent had exactly one grant path since
2026-05-05 (`0335ffd31`): `AuthController` completing a self-registration. Any
account created another way held **no `user_consents` row at all** — the two
spouse-account creators, and every account predating that commit.
`AiChatController` then answered 403 `consent_required` for the life of that
account, on web, `/m` and native alike, and no surface can grant it because there
is no consent toggle by design.

**Evidence:** csjones user 49 `isenbret@yahoo.co.uk`, registered 2026-05-01,
signed in 12:58, zero consent rows, zero conversations. Eight of sixty-seven
non-preview accounts were in the same state.

**Fix** (`bf04bae49`, PR #700, merged):
- Both spouse-account creators (`FamilyMembersController`,
  `SpouseLinkingService`) record the consent registration would have — a spouse
  account is a real login with a password emailed to it.
- A backfill migration grants it to accounts holding no row. **A withdrawn row is
  left withdrawn** — that is the one state that must survive this.
- Gate, message and native dead end unchanged: correct for a consent someone
  actually withdrew.

**Verified:** csjones after deploy — `users still missing ai_chat: 0`, user 49
`YES`. Not seen in a browser: no password for any of the eight accounts.

---

## 2. The budgeting refusal loop — `fynRecurring.png`

**Reported:** Fyn asks for monthly spending, the user answers "£5000 per month",
and gets the prompt-injection refusal followed by "Sorry, I didn't catch that",
identically on every retry.

**Evidence:** user 80 `azlan.raj@phailanx.co.uk`, conversation 67, messages
20134–20139 at 17:50. Audit for those turns: `__episode__ persist` only — **zero
tool calls**. The episodic blob
(`episodic/2026/08/18/67/20135.md`) shows the prompt the model actually received:

> …NOT in scope for this **Cash & Savings** turn…
> Tools available to you in this turn:
> **create_savings_account, update_profile, update_record**

The user's state was `onboarding_fyn_selection = 'budgeting'`,
`onboarding_fyn_step = 'asset_capture'`.

**Root cause:** `budgeting` was aliased to `savings` in every focus map, so the
turn ran as a Cash & Savings capture. `set_expenditure` exists and has a working
handler; it was never offered. A monthly spending figure is not a savings
account, the model had nothing that fit, and the refusal is its only scripted
exit. The director, seeing an unusable result, appends the retry copy — and the
retry replays the identical mismatch.

The comment three lines below the alias describes the same failure for pensions:
*"Without a 'pensioncheck' arm the focus fell to the savings default, so these
states had no pension tool and the model security-refused."* Fixed there, left in
place for budgeting.

**Fixed:**

| File | Change |
|---|---|
| `OnboardingPromptBuilder::toolsForFocus()` | `'budgeting' => ['set_expenditure']`, split from savings |
| `OnboardingPromptBuilder::focusLabel()` | now the ONE label map, public; `FynContextAssembler`'s rival copy (which called budgeting "Cash & Savings") deleted and delegated |
| `OnboardingChatDirector` duplicate check | budgeting no longer treated as `savings_account` |
| `AssetCaptureEntityExtractor` (4 sites) | budgeting no longer gap-fills savings accounts — "housing £1500, food £600" could have been written as two savings accounts |
| `DuplicateAcknowledgement` | budgeting no longer describes savings duplicates |

---

## 3. The same disease, found by enumeration

Asked whether this was resolved everywhere, the honest answer was no, so every
focus was walked against the question it asks. Two more were broken, silently:

| Focus | Fyn asks for | Tool it had | Result |
|---|---|---|---|
| `estate` | "valuables, gifts, **business interests**" | no `create_business_interest` | answer silently dropped — the capture block orders the model to ignore anything outside its tool list |
| `goals` ("Goals & **Life Events**") | goals and life events | no `create_life_event` | life events silently dropped |

Both catalogues now carry the missing tool.

---

## 4. The spouse-link dead end

**Evidence:** user 49, conversation 197, 17:54. Audit:

```
17:54:22 capture_spouse_details failed  first_name, date_of_birth and email are required
17:54:55 capture_spouse_details failed  Could not link spouse account. Please try again.
```

The first failure is correct — no email was given. The second is not: the user
supplied all three ("Meg 8 Jan 75 isenbret@gmail.com") and the write threw
`SQLSTATE[23000] 1062 Duplicate entry 'isenbret@gmail.com' for key
users_email_unique`. The same violation appears twice more in the log, on
2026-07-23 for user 284.

**Root cause:** `SpouseLinkingService` looked the email up with the default
scope, which skips soft-deleted rows — but the unique index does not. It decided
the account did not exist and INSERTed into a guaranteed duplicate-key violation.
PR #697 closed exactly this in `FamilyMembersController` this morning; the path
Fyn's onboarding uses was left with the hole.

**Second defect in the same turn:** the handler returned a plain
`['error' => true]`, which the grouped-extract path cannot distinguish from "the
model called no tool", so the user saw the retry copy — *"I need a first name,
date of birth, and email address"* — asking again for what they had just sent,
with no exit, because the next attempt fails identically.

**Fixed:**
- `SpouseLinkingService` looks up `withTrashed()` and raises
  `SpouseCollisionException` for a closed account, which routes to the existing
  targeted-error path instead of a doomed INSERT.
- The generic failure now returns `onboarding_capture_error`, so a failed write
  says what happened and never pretends the user's answer was the problem.

---

## Tests

| Test | Guards |
|---|---|
| `Unit/Services/Onboarding/BudgetingCaptureFocusTest` | budgeting gets `set_expenditure` and the "Budgeting" label, never the savings catalogue |
| `Unit/Services/Onboarding/CaptureFocusToolCoverageTest` | every selectable focus can record its own question; nothing falls through to the savings default; every focus bubble in the state machine is accounted for |
| `Feature/Onboarding/CaptureStateToolCoverageTest` | all four capture mechanisms — grouped_extract tools exist for both providers, every delegated campaign state is armed, no delegated state is unaccounted for, the advice-side handoff carries the walk's write tools |
| `Unit/Services/Onboarding/SpouseCollisionTest` (+1) | a soft-deleted spouse email is rejected, not crashed into, and nothing is written |
| `Unit/Services/Onboarding/AssetCaptureEntityExtractorTest` | corrected: it asserted budgeting gap-fills savings accounts, which was the bug |

Earlier in the day, for the consent fix: `Feature/AI/AiChatConsentGrantTest`
(spouse accounts get consent; the backfill grants a missing row and leaves a
withdrawn one withdrawn).

---

## Deployment

- PR #700 (consent + onboarding front door), #697, #698 merged to `dev`; csjones
  at `b7ce090ed`, migration run, SPA bundle rebuilt and uploaded.
- This round is backend-only — **no frontend rebuild and no TestFlight build
  needed**; the native app reads these fixes from the server.

## Known gaps, stated rather than implied

- None of the three fixes has been seen working in a browser by a human. The
  consent accounts have no password I hold; the budgeting and spouse paths need a
  live walk on csjones.
- The advice-side handoff carries every `create_*` write tool but none of the
  `capture_*` grouped-extract tools, so "my wife is Meg, born 1975" in advice mode
  routes through `create_family_member`/`update_profile` rather than
  `capture_spouse_details`. Not obviously wrong, not investigated.
- The `lint` job never ran its design-policy checks on any pull request touching
  `resources/` — the runner had no ripgrep. Fixed on `dev`; expect the next pull
  request to surface palette or emoji violations that were never being caught.
