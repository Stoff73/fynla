---
type: spec
status: APPROVED — §7 answered by CSJ 2026-08-17 (evening); implementation open
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
shared mechanism. **The four open questions in §7 were answered by CSJ on the evening
of 2026-08-17 and the answers are recorded there — implementation may proceed.**

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
| The other **16** | none | **Measured 2026-08-17 evening: they duplicate silently.** |

§7.2 is now answered. Every `handleCreate*` body was scanned for an existence check on
the entity it creates. Sixteen have none: `WhatIfScenario, Goal, LifeEvent, Holding,
Property, Mortgage, ProtectionPolicy, EstateAsset, EstateLiability, EstateGift, Will,
PowerOfAttorney, FamilyMember, Trust, BusinessInterest, Chattel`. Several *do* query
with `user_id`, but for a **parent** record — `handleCreateHolding` resolves the
investment account, `handleCreateMortgage` the property. None looks for an existing
record of its own type, so a second call creates a second row.

Receipts (C1) are also incomplete: six handlers return no `entity_id` —
`handleCreateWhatIfScenario`, `handleCreateWill`, `handleUpdateWill`,
`handleCreatePowerOfAttorney`, `handleUpdatePowerOfAttorney`, `handleUpdateProfile`.
The first three of those also omit `created`/`updated`, so `HasAiChat` emits no
`entity_created` for them at all.

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

One implementation, not twenty-four.

**5.0 It hangs off ONE call site.** `CoordinatingAgent::executeTool` dispatches every
tool through a single `match` (`CoordinatingAgent.php:1079`). The guard runs immediately
before that `match`, so no `handleCreate*` body needs a copy of it — including
`handleCreatePension`, whose bespoke merge is deleted and replaced by the shared one.
Nineteen edits become one. A new create handler still needs a registry entry, which an
architecture test asserts.

The guard returns `null` — proceed to the handler untouched — for a preview user, for
input with no usable name, and for any tool not in the registry.

**5.1 `RecordIdentity`** — per entity type, declares what "the same record" means
(§7.1: normalised name + provider/product + owner) and which fields a re-capture may
fill.

**5.2 `RecapturePolicy`** — the single fill/ask/ask decision from C2, plus the C3
explicit-edit override. Takes the existing model, the canonical payload, the raw tool
input and the explicit-edit flag; returns `fill` / `confirm_edit_required` /
`confirm_duplicate_required`. `handleCreatePension`'s current body is the extract-from.

**5.3 A write receipt** — every handler returns `{created|updated|deleted}`,
`entity_type`, `entity_id`, `name`. Emitted as `entity_created` / `entity_updated` /
`entity_deleted`.

**5.4 One route resolver** — entity type → the page showing that record, in ONE place
consumed by all three clients. Per §7.3 the target is the module page, with no
per-record deep link. Precedents already in-tree: `WebHandoffDestination`,
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

## 7. Answers — CSJ, 2026-08-17 evening

**7.1 "The same record" is the stable fields only: normalised name + provider/product
+ owner.** Balance and every other value field are explicitly NOT identity — they are
compared afterwards and drive the C2 fill / ask / ask decision. CSJ's original wording
("same name, same product, same balance, same owner") described an identical duplicate,
not the identity key; putting balance in the key would make a corrected balance read as
a brand-new record, which is the original bug.

Name matching is normalised, not exact: lower-cased, punctuation stripped, and generic
product nouns dropped, so "Aviva Pension" and "Aviva Personal Pension" resolve to the
same record. A normalisation that is too eager is *safe by construction* — a false
match cannot lose data, because both the conflict and identical branches of C2 write
nothing and ask. The failure mode is one extra question, never an overwrite.

**7.2 Answered by measurement — see §4.1.** Sixteen of nineteen create handlers do no
existence check and duplicate silently.

**7.3 Module page, no highlight.** Entities with no per-record route (chattel, estate
gift) link to the module page that lists them. Deep-link highlighting was rejected as
three surfaces' worth of machinery for little gain; every entity gets a working link.

**7.4 Deferred — prompt rule for now.** C5 stays a contract rule without a mechanical
guard. Comparing narration against tool results without blocking legitimate prose is a
hard problem, and the C1 receipt work shrinks the surface on which fabrication is
possible. Revisit once receipts are universal.

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
