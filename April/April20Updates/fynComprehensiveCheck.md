# Fyn Touch-Surface Audit — comprehensive check

**Scope:** every module Fyn can touch — investments, retirement, protection, estate, cash/savings, user profile, settings, will, power of attorney, goals, life events, trusts, family, property, chattels, business, navigation — audited against the four bug patterns that produced the 16 April chat issues. Plus the three items I deferred in the original report.

**Commit in scope:** `88018a5` (the four Fyn onboarding fixes) is LIVE on csjones.co/fynla and verified green. Everything below is issues that remain.

**Method:** 4 parallel subagent passes over `app/Agents/CoordinatingAgent.php`, `app/Services/Onboarding/*`, `app/Services/AI/*`, and the Vue router, each cross-checked against the source.

---

## Pattern legend

The 16 April test surfaced four bug families. Each is a distinct failure mode; fixing one does not fix the others.

| # | Pattern | Symptom on 16 Apr |
|---|---------|-------------------|
| §1 | **Selection / state not persisted** — a bubble pick changes user intent but `users.*` columns aren't updated, so downstream logic keeps seeing stale state | "Savings" click kept routing to the family asset_capture intro |
| §2 | **LLM text leak through a deterministic lane** — restricted-prompt delegated turns let chatty model text leak alongside the state machine's own output | Two stacked assistant messages per failed capture |
| §3 | **All-or-nothing capture** — handlers reject partial payloads, so every retry re-asks for the full set, losing data the user already typed | "Dentsu" turn wasted; employer discarded until third try |
| §4 | **Data destination mismatch** — Fyn writes to column A but the dashboard / service layer reads from column B | "£4,000 captured" per Fyn, but the dashboard showed nothing |

The legend above is the filter I apply to every tool, state, and handler below.

---

## Part 1 — Patterns §1–§4 beyond the savings case

### §1 — Selection persistence: other "bubble pick drops on the floor" cases

Audit result: **no other state has the `add_more` bug.** Every other bubble state either sets a `capture_field` that `persistCapture()` writes through the generic path, or has a specialised handler (`base_dependants`, `base_spouse`, `base_dependants_detail`).

Relevant file: `app/Services/Onboarding/OnboardingStateMachine.php:76–232`.

- `path_choice` → `capture_field: onboarding_fyn_path` ✅
- `journey_selection` / `focus_selection` → `onboarding_fyn_selection` ✅
- `base_employment` → `employment_status` ✅
- `base_dependants` → `onboarding_fyn_context.has_dependants` via specialised handler ✅
- `add_more` → fixed in commit `88018a5` ✅

**Verdict: no further §1 fixes needed in onboarding.**

Adjacent risk outside onboarding (not bubbles, but similar "state drift" shape):
- `handleUpdateProfile` (CoordinatingAgent.php:2946) has **no spouse-linked-user sync** when the user updates their profile. If user A changes `marital_status` to 'married' or updates spouse info, the linked spouse user record (User B, created via `SpouseLinkingService`) is never touched. When User B later logs in, their side shows stale data.
- `handleSetExpenditure` (CoordinatingAgent.php:2777) writes to user A only. If the household shares budgeting, spouse's `monthly_expenditure` is not kept in sync. Onboarding `SpouseLinkingService` does divide expenditure across spouses on creation (OnboardingService.php:574) but post-onboarding updates bypass that.

### §2 — LLM text leak: other delegated turns

Audit result: **only `grouped_extract` had the leak.**

- `grouped_extract` turns → director yields events from a delegated LLM call, restricted prompt says "do not emit text" → Grok/Claude sometimes emit text anyway → director was passing those `content` events through → fixed in `88018a5`.
- `delegated` turns (`asset_capture`) → the director explicitly wants conversational LLM output here; `content` events pass through on purpose. No leak.
- Post-onboarding chat → full `CoordinatingAgent::chat()` flow, no prompt restriction, text is the expected output. No leak.

**Verdict: pattern §2 is fully resolved.** No further action.

### §3 — All-or-nothing capture: where else this pattern lives

This one has the widest footprint. Every capture/create tool I audited falls into one of three categories:

#### 3a. Capture tools (onboarding grouped_extract) — 3 still affected

| Tool | Handler | Line | All-or-nothing fields | Fix status |
|------|---------|------|------------------------|------------|
| `capture_personal_details` | `handleCapturePersonalDetails` | 787 | `date_of_birth`, `marital_status` | **❌ not fixed** — rejects whole turn if either missing (line 805) |
| `capture_spouse_details` | `handleCaptureSpouseDetails` | 883 | `first_name`, `date_of_birth`, `email` | **❌ not fixed** — rejects whole turn if any missing (line 889) |
| `capture_dependants` | `handleCaptureDependants` | 947 | dependants array non-empty | acceptable — empty array means user said "none", director routes via a separate yes/no bubble |
| `capture_work_details` | `handleCaptureWorkDetails` | 1013 | — | ✅ fixed in `88018a5` |

**Personal and spouse have the same bug as work had.** For personal, the LLM has a higher hit rate on a single turn (only two fields), but edge cases still burn a turn — e.g. user types "12 January 1985 I think I'm married-ish", the LLM might extract DOB but hesitate on marital enum, hit the retry, forget the DOB it already parsed. For spouse, three fields × LLM sampling noise → intermittent retries with silent data loss.

#### 3b. Create tools (post-onboarding chat + asset_capture delegated turn)

These return `action: fill_form` — the LLM doesn't write to DB; the frontend opens a form pre-filled with whatever the tool returned. Partial payload is *technically fine* because the frontend form still has blank fields for the user to fill. So the §3 pattern mostly does not apply here.

EXCEPT two that still reject in the handler instead of forwarding partials:

| Tool | Handler | Line | Required fields |
|------|---------|------|------------------|
| `create_asset` (estate) | `handleCreateEstateAsset` | 2097 | `asset_name`, `asset_type`, `current_value` |
| `create_estate_gift` | `handleCreateEstateGift` | 2170 | `gift_date`, `recipient`, `gift_type`, `gift_value` |

Both call `validateToolInput` with all three/four fields as `required`. If the LLM extracts 2 of 3, the tool returns an error, the user sees a generic "that didn't work, please try again" — and the partial extraction is lost. Lower-severity than onboarding (post-onboarding chat is less structured, users usually describe items fully), but the pattern is present.

#### 3c. Pre-existing-state collisions (a §3 cousin — handler can't succeed even with a complete payload)

Same failure mode from Fyn's perspective (retry loop), different cause. Found via the stale `test2@phailanx.co.uk` that broke the 16 April test on csjones.co/fynla:

| Handler | Line | Collision | Current behaviour |
|---------|------|-----------|-------------------|
| `handleCaptureSpouseDetails` | 883 | Spouse email belongs to another user / household | `SpouseLinkingService` throws `InvalidArgumentException`, handler returns `error: true` with raw service message, no `onboarding_capture` key → director's `emitRetry` fires with a generic "please share again" → **loop** |
| `handleCreateFamilyMember` | 2472 | Family member with same name already exists | No dup check — form will silently accept duplicate rows |
| `handleCreateTrust` | 2571 | Trust with same name already exists | No dup check |
| `handleCreateBusinessInterest` | 2650 | Business with same name already exists | No dup check |
| `handleCreateEstateAsset`, `-Liability`, `-Gift`, `handleCreateChattel` | 2097–2705 | Same names | No dup check |

The spouse one is the most damaging — it manifests as an infinite retry loop that looks identical to a capture failure. The director can't tell "LLM didn't extract" apart from "handler rejected because of duplicate email".

### §4 — Data destination mismatch: same bug as expenditure, but elsewhere

Only one other handler has the exact pattern I fixed for onboarding expenditure.

| Handler | Line | Writes to | What reads from elsewhere |
|---------|------|-----------|----------------------------|
| `handleSetExpenditure` | 2748 | `users.monthly_expenditure`, `users.annual_expenditure`, and per-category columns on `users` | `ExpenditureProfile.total_monthly_expenditure` is what `IHTCalculationService.php:731` and several dashboard aggregators read. Category fields on the User table exist but are an older schema — the profile model is the canonical one. |

Verified at `app/Agents/CoordinatingAgent.php:2755–2780`. The fix I shipped for onboarding only covered the `STATE_BASE_EXPENDITURE` path inside `OnboardingChatDirector::persistCapture`. If a post-onboarding user tells Fyn "my rent is £1500", Fyn calls `handleSetExpenditure`, which writes to the user row only — **the same dashboard/IHT reading gap reappears**.

This is the most severe new finding in the audit: my fix solved the onboarding path but left the post-onboarding path broken in exactly the same way.

Related near-misses (checked, not actually broken):
- `buildUserProfile()` in `SystemPromptBuilder.php:180–279` reads from `users.*` columns. So it picks up `monthly_expenditure` from the user row and reports it back in Fyn's replies — which is *why* the chat said "£4,000 is captured" on 16 April. The numbers are truthful; the dashboard was empty. The prompt and dashboard disagreed.
- The breakdown fields (`rent`, `utilities`, etc.) on the User table are read by the expenditure form for display, but the dashboard aggregate components use `ExpenditureProfile`. The two stores drift whenever one is touched without the other.

---

## Part 2 — Findings outside the four patterns

Issues the audit surfaced that don't fit §1–§4 but can still break Fyn interactions.

### T1 — Trust auto-CLT writes to DB without a transaction (data integrity)

File: `app/Agents/CoordinatingAgent.php:2617–2638`.

`handleCreateTrust` returns a `fill_form` response for the trust itself (the frontend opens the trust form pre-filled). But BEFORE returning, if `initial_value > 0`, it directly creates an `Estate\Gift` row marking the settlement as a Chargeable Lifetime Transfer. If the user then cancels the trust form on the frontend (or the trust save fails server-side later), the CLT gift persists orphaned — IHT calculations will double-count a trust settlement that never happened.

No transaction wraps the two writes. The `catch` on line 2633 only logs the CLT creation failure, doesn't propagate it.

### T2 — `handleUpdateRecord` allows the LLM to update any fillable field

File: `app/Agents/CoordinatingAgent.php:2802–2858`, specifically line 2838.

```php
$fillable = $model->getFillable();
$safeFields = array_intersect_key($fields, array_flip($fillable));
unset($safeFields['user_id'], $safeFields['id']);
```

The only blocklist is `user_id` and `id`. Every other fillable field is open. Examples of what this means in practice:
- `Trust` model has `settlor` as fillable — the LLM can change who the settlor is, which changes IHT exposure.
- `Mortgage` has `start_date` and `term_years` as fillable — changing either re-amortises the loan.
- `FamilyMember` has `relationship` as fillable — an LLM could promote a dependant to a spouse relationship, which triggers `SpouseLinkingService` and other household logic.

This is a latent security/data-integrity issue. The LLM is generally well-behaved, but an adversarial or confused user prompt could exploit it. The mitigation is a per-entity whitelist of fields Fyn is allowed to change (the complement of the current "blocklist of 2" approach).

### T3 — `handleUpdateProfile` has no spouse-linked-user sync

File: `app/Agents/CoordinatingAgent.php:2946`.

When the user updates personal details (name, DOB, marital_status, address, etc.) via Fyn, only their own User row is touched. The linked spouse record (User B, created by `SpouseLinkingService`) is never updated. After divorce or address change, the two sides of the household diverge silently.

Onboarding has a different path via `SpouseLinkingService::linkOrCreateSpouse` that does sync; the post-onboarding profile update path bypasses that.

### T4 — LLM system prompt is missing `employer` and `occupation` — root of the hedging reply

File: `app/Services/AI/SystemPromptBuilder.php:180–279` (`buildUserProfile`).

Confirmed: the user-profile block surfaces name, age, employment_status, marital_status, income (multiple types), expenditure, spouse info, retirement date, and family members. It does **not** include `users.employer` or `users.occupation`.

This is why on 16 April Fyn said "if the employer (Dentsu) or role (Chief Marketing Officer) details aren't showing fully, you can add or edit them right there on screen" — the fields were saved, but the prompt couldn't see them, so Fyn hedged.

This is the original report's §5 item; the audit confirms the exact line where the fix needs to land.

### T5 — Expenditure read path in `SystemPromptBuilder` doesn't consult `ExpenditureProfile`

File: `app/Services/AI/SystemPromptBuilder.php:963–974` (`calculateTotalExpenditure`).

The function checks `users.monthly_expenditure` → `users.annual_expenditure` → returns 0. It never looks at `ExpenditureProfile.total_monthly_expenditure`. `KycGateChecker.php:120–127` checks the same user fields but THEN falls back to the profile — different fallback order.

If a future user entered expenditure via the old form path that writes to `ExpenditureProfile` but not `users.*` (possible with the post-`88018a5` onboarding fix that writes to both), the prompt will report £0 while the dashboard shows £4,000. Another contradiction hotspot.

### T6 — Navigation tool has gaps vs the actual router

File: `app/Services/AI/AiToolDefinitions.php:60` (the `navigate_to_page` allow-list) vs `resources/js/router/index.js`.

Live routes not in Fyn's allow-list (cannot be navigated to):
- `/estate/inheritance-tax` (router line 745–756)
- `/settings/privacy` (router line 474–484)
- `/risk-profile/levels` and `/risk-profile/factor/:factor`
- `/planning/what-if/:id` (scenario detail)
- `/actions/:planType/:actionId`
- `/plans/goal/:goalId`

No broken entries — every route Fyn CAN use still exists. But a user asking "take me to the IHT planner" or "show me my privacy settings" will get a generic "I can't take you there" instead of the actual route.

### T7 — Family asset_capture goes off-script (the original report's §5.other)

Files: `app/Services/Onboarding/OnboardingPromptBuilder.php:82–115`, `app/Services/AI/Prompts/CoreIdentity.php`.

Root cause confirmed: `CoreIdentity::get()` establishes a "qualified financial planner" persona, and `tool_choice='auto'` in `HasAiChat.php:191,297` lets the LLM reply freely with text *and optionally call tools*. The restricted asset_capture prompt tells it "don't analyse, don't reference figures the user didn't provide" but doesn't forbid **inferential follow-ups** — so when the user is mid-family-capture, the LLM's "financial planner" instinct adds "do you own your home?" because that's what a general planner would ask next.

The narrow tool list (`[create_family_member]` only) prevents the LLM from *acting* on that inference, but the text still ships to the user as a visible off-script question.

### T8 — Stale spouse-email lookups return a dead-end error

File: `app/Services/Onboarding/SpouseLinkingService.php` (throws `InvalidArgumentException`) + `app/Agents/CoordinatingAgent.php:914–915` (handler returns plain `error: true`).

When the spouse email already belongs to someone else, the service throws, the handler returns `error: true` with the service message, but **omits `onboarding_capture`**. `handleGroupedExtractTurn` treats it the same as "LLM didn't call the tool" → emits the generic retry. User is stuck in a loop with no idea why.

Needs either: (a) treat collision as a permanent-failure signal and surface a distinct user-facing message ("That email's already registered — use a different address or ask them to link their own account"), or (b) have `SpouseLinkingService` fall back to linking an existing unclaimed user.

---

## Part 3 — Status of the original report's deferred items

From `fynChatAnalysis.md`:

1. **Family asset_capture off-script** → diagnosed as T7 above. Root cause in `OnboardingPromptBuilder::assetCaptureInstructions`.
2. **Post-onboarding retrieval hedging** → diagnosed as T4 above. Root cause in `SystemPromptBuilder::buildUserProfile`.
3. **Stale `test2@phailanx.co.uk`** → diagnosed as T8 above. Root cause is the combination of `SpouseLinkingService` exception shape + director's retry contract.

All three now have specific file:line. No longer deferred — ready for a fix pass if you want them done.

---

## Part 4 — Prioritised fix ledger

Ordered by user-visible severity × blast radius. Every item is one scoped PR-worth of work.

### P0 — user-facing breakage today

| # | Item | Severity | Effort |
|---|------|----------|--------|
| F1 | `handleSetExpenditure` sync to `ExpenditureProfile` (§4 pattern, post-onboarding) | **High** — any user who says "my rent is £X" to Fyn post-onboarding loses it from dashboards | ~5 lines, mirror of the onboarding fix |
| F2 | Spouse-email collision needs a distinct user-facing path (T8) | **High** — loops users infinitely with no diagnostic | ~20 lines across handler + director |
| F3 | Family asset_capture off-script text (T7) | **Medium** — cosmetic but confusing, caught on 16 Apr | ~5 lines in `OnboardingPromptBuilder::assetCaptureInstructions` + consider `tool_choice=required` for asset_capture |

### P1 — correctness/security

| # | Item | Severity | Effort |
|---|------|----------|--------|
| F4 | `handleUpdateRecord` per-entity field whitelist (T2) | **Medium-High** — opens data integrity holes, but no active exploit known | ~40 lines, one map per entity type |
| F5 | `handleCreateTrust` transactional CLT write (T1) | **Medium** — orphaned CLT rows overstate IHT exposure if user cancels trust | ~10 lines, wrap in `DB::transaction` + return CLT result correctly |
| F6 | `handleCapturePersonalDetails` + `handleCaptureSpouseDetails` partial-capture (§3, same pattern as work fix) | **Medium** — lower rate of LLM partial extraction on 2-3 field tools, but same data loss when it happens | ~30 lines per handler, following the work-capture template |

### P2 — quality-of-life

| # | Item | Severity | Effort |
|---|------|----------|--------|
| F7 | `SystemPromptBuilder` — surface `employer` + `occupation` in `buildUserProfile` (T4) | **Low** — removes post-onboarding hedging | ~3 lines |
| F8 | `SystemPromptBuilder::calculateTotalExpenditure` — consult `ExpenditureProfile` as a fallback (T5) | **Low** — prevents future £0 hallucinations after F1 lands | ~5 lines |
| F9 | Duplicate-name checks on `create_trust`, `create_family_member`, `create_business_interest`, `create_asset`, `create_liability`, `create_estate_gift`, `create_chattel` (§3 cousin) | **Low** — same template as `SavingsAccount` duplicate check already used in `handleCreateSavingsAccount`:1531 | ~6 lines each = ~40 lines total |
| F10 | `handleUpdateProfile` — spouse-linked-user sync (T3) | **Low** — affects household accuracy after profile edits, rarely hit | ~20 lines |
| F11 | `handleSetExpenditure` — spouse sync (§1 cousin) | **Low** — same rationale as F10 | ~10 lines |
| F12 | Add missing routes to `navigate_to_page` allow-list (T6): `/estate/inheritance-tax`, `/settings/privacy`, risk sub-routes, etc. | **Low** — just adds capabilities Fyn already has targets for | ~8 lines in the tool schema |
| F13 | `handleCreateEstateAsset` + `handleCreateEstateGift` partial-payload tolerance (§3) | **Low** — post-onboarding chat; users usually provide all fields | ~10 lines each |

### P3 — non-bugs worth noting

- `capture_dependants` all-or-nothing is fine — empty means "no dependants" and routes separately.
- Legacy `/savings` and `/investment` routes (router line 653–692) redirect correctly; Fyn's "never use these" instruction is advisory only, no active bug.
- Router guards correctly block `/admin` and `/debug-env` from Fyn navigation.

---

## Part 5 — Acceptance checks to run after F1–F13 land

Keep the 13-step browser test from `fynChatAnalysis.md` and add:

14. Post-onboarding, tell Fyn "my rent is £1,500 and utilities are £300." Confirm both the dashboard and Fyn's follow-up reply agree on the total (F1).
15. Trigger a spouse-email collision (register as a new user, claim a spouse email that already exists on another household). Confirm Fyn emits a diagnostic message, not a loop (F2).
16. Pick family journey → asset_capture. Confirm Fyn's reply contains zero property/mortgage/savings questions (F3).
17. Ask Fyn to update a mortgage's `start_date`. Confirm it refuses unless that field is on the entity whitelist (F4).
18. Create a trust via Fyn with `initial_value=100000`, then cancel the trust form on the frontend. Confirm no orphaned `Estate\Gift` row exists (F5).
19. Start onboarding as a new user, give only a DOB on the base_personal turn (no marital status). Confirm Fyn saves the DOB and asks only for marital (F6).
20. Post-F7, ask Fyn "where's my employment data?" — confirm it names the employer and role without hedging (F4/F7).
21. Ask Fyn to take you to inheritance tax planning and privacy settings. Confirm both navigate (F12).

Every [x] on this list must be a Playwright interaction, per `critical_browser_testing_law`.

---

## Part 6 — What is NOT in scope

These surfaced during the audit but are out of bounds for this ledger:

- Frontend form-level bugs after `fill_form` returns (e.g. a form field not rendering correctly). The audit only covered the server-side handoff.
- Tool-definition schema changes in `AiToolDefinitions.php` beyond the navigation allow-list (T6). Other tool schemas are working as designed.
- Preview-user behaviour. Every handler I audited correctly checks `$isPreview` and returns a blocked response. No preview bugs found.
- `QueryClassifier` / `KycGateChecker` logic. These are upstream of the handlers and weren't implicated in any 16 April issue.
- The Stoic mode / routing changes on `dev` — this branch is `onboardingFyn`, the changes on `dev` are out of my scope to audit here.

---

**Bottom line:** `88018a5` fixed the four specific bugs the 16 April test exposed. The audit surfaces 13 more items (F1–F13) where the same patterns recur elsewhere in Fyn's touch surface. Three are P0 (user-visible today), three are P1 (correctness/security), the rest are P2 quality improvements. Every item has a file:line and an effort estimate so any of them can be worked as a standalone change.
