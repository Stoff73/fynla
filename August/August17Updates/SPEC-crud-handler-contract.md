---
type: spec
status: DRAFT — needs CSJ review before implementation
date: 2026-08-17
author: session 2026-08-17
supersedes: nothing
covers: 19 create handlers, 4 update handlers, 1 delete handler in CoordinatingAgent
---

# SPEC — the CRUD handler contract for Fyn writes

Every record Fyn creates, edits or deletes must behave identically, whatever the
module and whatever the surface. Today they do not: one handler out of twenty-four
is correct, and the rest each fail differently.

This spec states the contract, records the measured current state, and defines one
shared mechanism. **It is a draft — the open questions in §7 need CSJ before anyone
writes code.**

Written after a full day debugging one pension capture (BUG-02, BUG-03). Every
"current state" line below was measured, not assumed.

---

## 1. Why this exists

A user said *"I have an aviva pension with a balance of 45000"*, then answered Fyn's
follow-up with *"Sip"*. The result: two pension records, one with a £0 balance, the
wrong scheme type on both, a £0 retirement projection, and no way to click through to
either record. Five distinct defects in one 12-word exchange.

Each was fixed individually for pensions. None of the fixes reached the other
twenty-three handlers, and copying them twenty-three times is precisely the Rule 20
failure that produced the mess.

---

## 2. The handlers

**Create (19)** — `handleCreate{BusinessInterest, Chattel, EstateAsset, EstateGift,
EstateLiability, FamilyMember, Goal, Holding, InvestmentAccount, LifeEvent, Mortgage,
Pension, PowerOfAttorney, Property, ProtectionPolicy, SavingsAccount, Trust,
WhatIfScenario, Will}`

**Update (4)** — `handleUpdate{PowerOfAttorney, Profile, Record, Will}`.
`handleUpdateRecord` is the generic one, gated by `UpdateRecordAllowlist`.

**Delete (1)** — `handleDeleteRecord`, generic.

All in `app/Agents/CoordinatingAgent.php`.

---

## 3. The contract

Every write handler MUST:

**C1 — Return a receipt that identifies the record.**
`entity_type` and `entity_id` on success, always. A client cannot link to a record it
cannot name.

**C2 — Never assume an edit.**
CSJ, 2026-08-17: *"merging a correction on an assumption or a simple comparison is
WRONG and will lead to errors. An edit, amendment or change must be explicit. If
there is any ambiguity Fyn must ask 'are we editing {plan}' before making any
changes."*

Concretely, when an incoming create matches an existing record:

| Case | Behaviour |
|---|---|
| The field is empty on the record | **Fill it.** Unambiguous. |
| The field holds a *different* value | **Write nothing. Ask** whether this is an edit or a separate record. |
| Everything already matches | **Write nothing. Ask** whether this is a separate record — do NOT assume a duplicate. |

**C3 — Except when the user is answering Fyn's own question.**
CSJ, 2026-08-17: answering an outstanding question about a specific record IS
explicit, so a conflicting value applies directly. Carried by
`CaptureContext::$isContinuation`, set ONLY by the deterministic continuation branch
in `AdviceFyn` — never by the model, which must not be able to grant itself edit
permission.

**C4 — Never write a value the user did not supply.**
A merge must apply only fields present and non-null in the tool input, never a
normaliser-derived default. `PensionNormaliser` falls back `provider = scheme_name`,
which would otherwise overwrite a real provider with the scheme name on any later turn.

**C5 — Never claim a write that did not happen.**
If a turn narrates a save and no tool call landed, the claim must not reach the user.
Currently unenforced anywhere — see §7.

**C6 — User-facing text must be user-facing.**
The failure path prints `message` verbatim in chat. Model instructions belong in a
separate key (`model_directive`). Shipped and caught live on 2026-08-17: users were
shown *"Ask the user to confirm whether they are editing that pension…"*.

**C7 — Rule 9 applies to every string a user can see**, including handler messages.
Enforced for tool schemas by `tests/Architecture/Rule9NoAcronymsInFynVocabularyTest.php`;
handler messages are NOT yet covered.

---

## 4. Current state, measured

### 4.1 Duplicate handling

Only **3 of 19** create handlers check for an existing record at all:

| Handler | Mechanism | Verdict |
|---|---|---|
| `handleCreatePension` | fill / ask / ask, plus explicit-edit override | **Correct** — the reference implementation |
| `handleCreateSavingsAccount:2879` | `checkForDuplicate` on `account_name` | Warns and discards — the answer lands on a no-op |
| `handleCreateInvestmentAccount:2951` | `checkForDuplicate` on `account_name` | Same |
| The other **16** | none | **Unverified.** May duplicate silently. Not measured — see §7. |

`checkForDuplicate` is a case-insensitive EXACT name match, so "Aviva Pension" and
"Aviva Personal Pension" are different records. Its warning text claims to catch
"a similar record", which overstates what it does.

### 4.2 Confirmation events — the missing link

**`entity_created` is the only entity SSE event emitted anywhere in the application.**
Verified: `grep "'type' => 'entity_' " app/` returns exactly one match.

- **Create** emits `entity_created` with `entity_type`, `entity_id`, `name`
  (`HasAiChat:878-885`, gated on `$toolResult['created'] === true`).
- **Update** emits nothing.
- **Delete** emits nothing.

So CSJ's assumption is confirmed and is worse than a missing link: for edits and
deletes there is no event to carry one.

And no client builds a link even from `entity_created` — all three use only `name`:

| Surface | Code | Renders |
|---|---|---|
| web | `AiMessageContent.vue:14-22` | text card, no link |
| `/m` | `onboardingChat.js:454-457` | pushes `name` into an array |
| native | `FynEventReducer.swift:63-72` | appends `name` to a pending chip |

Native cannot build one at all: `FynEvent.swift:21` is
`case entityCreated(name: String?)` — `entity_type` and `entity_id` are discarded at
decode time.

---

## 5. The shared mechanism

One implementation, not twenty-four. Sketch only; §7 must be answered first.

**5.1 `RecordIdentity`** — per entity type, declares what "the same record" means
(§7.1) and which fields a re-capture may fill.

**5.2 `RecapturePolicy`** — the single fill/ask/ask decision from C2, plus the C3
explicit-edit override. Takes the existing model, the canonical payload, the raw tool
input and the explicit-edit flag; returns `fill` / `confirm_edit_required` /
`confirm_duplicate_required`. `handleCreatePension`'s current body is the extract-from.

**5.3 A write receipt** — every handler returns `{created|updated|deleted}`,
`entity_type`, `entity_id`, `name`. Emitted as `entity_created` / `entity_updated` /
`entity_deleted`.

**5.4 One route resolver** — entity type → the page showing that record, in ONE place
consumed by all three clients. Precedents already in-tree: `WebHandoffDestination`,
`SemanticDestination`, and the three-way-duplicated `ONBOARDING_NAV_ROUTES` (BUG-02
§Rule 20 finding) which is what NOT to do.

**5.5 Native decoder widened** to carry `entity_type` and `entity_id`.

---

## 6. Acceptance

1. An architecture test asserting every `handleCreate*` / `handleUpdate*` /
   `handleDelete*` returns `entity_type` + `entity_id` on success. It must FAIL on a
   handler that does not, proven by temporarily breaking one.
2. A per-entity test matrix for C2: fill applies, conflict asks and writes nothing,
   identical asks and writes nothing.
3. A test that C3 applies a conflicting value ONLY with the explicit-edit flag set,
   and that the flag does not leak to a later turn.
4. Rule 9 coverage extended to handler `message` strings.
5. Link verified rendering on web, `/m` AND native — Rule 19 means all three.

---

## 7. Open questions — CSJ

**7.1 What is "the same record" per entity?**
Pensions matched on `scheme_name`; savings and investments on `account_name`. Exact
name matching is what produced the original duplicate ("Aviva Pension" vs "Aviva
Personal Pension"), so a name-only key will keep failing. CSJ's stated rule is "same
name, same product, same balance, same owner" — that needs turning into a concrete
field list per entity type. **Blocking 5.1.**

**7.2 What do the other 16 create handlers currently do on a re-capture?**
Not measured. A probe on 2026-08-17 failed because the test payloads were incomplete
(`clarification_required` on the first call), so nothing was learned. Needs a real
payload per handler before the scope is known. **Blocking the estimate, not the design.**

**7.3 Where does a link point for entities with no dedicated page?**
A chattel or an estate gift may have no per-record route. Module page with the record
highlighted, or no link for those types?

**7.4 Should C5 be mechanical?**
A guard that refuses to let a turn narrate a save when no write landed. This is the
"no fabricated success" rule Fyn currently breaks. Design is not obvious — it needs to
compare narration against tool results without blocking legitimate prose.

---

## 8. Related

- `August/August17Updates/iOSBugs/BUG-02-pension-capture-and-projections.md` — the
  three original root causes, with the measured HTTP boundary table
- `August/August17Updates/iOSBugs/BUG-03-capture-confirmation-link-and-kyc-gate.md` —
  the link gap and the KYC bypass ruling (CSJ: leave the bypass as is)
- `April/April24Updates/spec/00-canonical.md:21` — the stream event contract listing
  `entity_created` as a public confirmation event
- Reference implementation: `handleCreatePension` and `mergePensionRecapture` in
  `app/Agents/CoordinatingAgent.php`
