---
id: W-0010
title: Dead-end — a user whose only pension is Defined Benefit cannot add any further pension
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0001-batch-c-retirement-profile-gates.md
owner: build-lead
status: handoff
surfaces: [web, m, ios]
created: 2026-08-20T23:05:00Z
claimed: 2026-08-21T09:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-21T09:10:00Z
prior_art_found: ["resources/js/components/NetWorth/PensionList.vue pension-cta-row (existing control, wrongly scoped)", "resources/js/components/Retirement/UnifiedPensionForm.vue + DCPensionForm.vue pension-type dropdown already covers Defined Benefit and State Pension", "resources/mobile/views/modules/Retirement.vue buildContextualConversationRequest(action: add) — /m has no equivalent dead-end"]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **Pass A** (desktop web module UI forms, local
`localhost:8000`), account **Sarah Jones (spouse)**, user id 17.

**Surface:** desktop web, `/net-worth/retirement`.

Severity: **high**. It is a hard dead-end — the page tells the user data is missing
and simultaneously removes every control that would let them add it.

### Expected

Persona file `tests/Persona/peak_earners.md:382-404` gives Sarah both an NHS Defined
Benefit pension **and** a State Pension forecast (£221.20/week, £11,502/year, State
Pension age 67, 30 qualifying years). After entering the Defined Benefit pension it
must remain possible to add the State Pension, and any further pension.

### Actual

After saving Sarah's NHS Defined Benefit pension, `/net-worth/retirement` renders
only the "Guaranteed Retirement Income" card. **There is no "Add Pension" button
anywhere on the page** — verified by enumerating every `<button>` in the DOM:

```
Open navigation menu, Chat, Upgrade Now, Support, Chat with Fyn, Sarah Jones,
New, History, Collapse, Suggestions show, Send, Cash Management, Finances,
Personal Affairs, Planning, Tax Strategy, Sign Out
```

Nothing module-level. Scrolling to the bottom of the page confirms the card is
followed directly by the footer.

The completeness banner **on that same page** still reads:

```
5 of 7 items complete
OUTSTANDING: Your monthly spending
             Your money purchase pensions
             Your State Pension forecast
```

So the page asks for two things it gives the user no way to provide.

### Root cause

`resources/js/components/NetWorth/PensionList.vue`:

- **:39** — the empty state carrying "Add Your First Pension" (`:49`) renders only
  under `v-else-if="allPensions.length === 0"`. One pension exists, so it is gone.
- **:64** — with pensions present but **no DC pension**
  (`!projections || !projections.pension_pot_projection?.dc_pension_count`) the
  template takes the `guaranteed-income-summary` branch. Neither of its two arms —
  `hasOnlyGuaranteedPensions` (`:66`) nor the fallback (`:150`) — contains an add
  control.
- **:160** — the `v-else` "Projections Content" branch is the only one that renders
  the `pension-cta-row` holding the **"Add Pension"** button (`:315-326`), and it is
  reached only when `dc_pension_count > 0`.

That is a closed loop: you need a Defined Contribution pension to reveal the button
that adds a Defined Contribution pension.

The one other route to the State Pension form is
`RetirementIncomeTab`'s `@add-state-pension="openStatePensionForm"`
(`PensionList.vue:396-399`), but that tab is entered only via
`setActiveTab('income')` from the planner cards inside the same DC-only branch.
`activeTab` is plain Vuex state defaulting to `'current'`
(`resources/js/store/modules/retirement.js:44`) with no route or query binding, so
there is no deep link out of the dead-end either.

### Repro

1. Fresh account, no pensions.
2. `/net-worth/retirement` → "Add Your First Pension" → Pension Type
   "Final Salary (Defined Benefit)" → fill and save.
3. The page now shows only "Guaranteed Retirement Income".
4. There is no way to add a Defined Contribution pension or a State Pension. The
   completeness banner still lists both as outstanding.

A user who happens to enter a Defined Contribution pension first never sees this,
which is presumably why it has survived — the fault is specific to Defined
Benefit-first (and State-Pension-first) data entry order.

### Impact on this persona run

Sarah's State Pension (`peak_earners.md:396-404`) **could not be entered in Pass A**.
There is no UI path, and patching the row directly is not permitted. It stays
unentered until this is fixed, so every figure that depends on Sarah's State Pension
— her post-State-Pension-age income, the household guaranteed income, and the
retirement shortfall shown on this page — is unverifiable for her account.

### Evidence

**No screenshot** — entry-phase finding. The DOM button enumeration is quoted verbatim above and is the stronger evidence: it lists every `<button>` on the page and shows no add control.
Report: `reports/R-01-pass-a-entry.md`.

## Acceptance

- [ ] An "Add Pension" control is present on `/net-worth/retirement` whenever the
      user is not in the zero-pension empty state, regardless of pension mix.
- [ ] A user whose only pension is Defined Benefit can add a Defined Contribution
      pension and a State Pension.
- [ ] The completeness banner cannot list an outstanding item that has no reachable
      control on the page it is shown on.
- [ ] Verified for all four entry orders: DC-first, DB-first, State-first, and
      mixed.
- [ ] `/m` and iOS retirement screens checked for the same branch (Rule 19).
- [ ] Re-verified live in the browser by the persona run, and Sarah's State Pension
      then entered.

## Working notes

(append-only)

- 2026-08-20 persona-tester: raised from Pass A. Root cause diagnosed to file:line
  above; not fixed by me — routed to build-lead.

- 2026-08-21 build-lead: FIXED on web. Root cause confirmed exactly as diagnosed —
  the `pension-cta-row` holding **Add Pension** / **Upload Statement** sat inside
  `.pension-cards-column`, which lives in the `v-else` "Projections Content" arm
  reached only when `projections.pension_pot_projection.dc_pension_count > 0`.

  **Fix (one control, one place):** the CTA row moved OUT of every projections
  branch to the end of the `activeTab === 'current'` container —
  `resources/js/components/NetWorth/PensionList.vue:355-388`. It is not duplicated;
  the DC branch no longer carries its own copy. It now renders for every non-empty
  pension mix (Defined Benefit only, State Pension only, Defined Contribution,
  mixed), and the zero-pension empty state keeps its own
  "Add Your First Pension" button (`:45-50`) as before.

  **Second dead-end found and closed in the same branch.** The
  `guaranteed-income-summary` arm rendered the Defined Benefit and State Pension
  rows as inert `<div>`s, so a Defined Benefit-only user could not open, edit or
  delete the pension they had just entered either — the projections arm is the
  only place `selectPension()` was ever wired. Both rows are now clickable
  (`PensionList.vue:88-96` and `:122-127`, `.guaranteed-item-clickable`
  at `:1413-1421`), matching the `pension-card-standalone` pattern already used
  in the other arm. Without this, W-0017 could not be verified against Sarah's
  existing row at all.

  **State Pension route confirmed reachable:** "Add Pension" opens
  `UnifiedPensionForm` with `initialPensionType: null` → `DCPensionForm`, whose
  Pension Type dropdown carries `state_pension` (`DCPensionForm.vue:46`) and
  `final_salary` (`:43-45`). No deep link into `activeTab='income'` is needed.

  **`/m` — no equivalent dead-end (checked, not assumed).** `/m` has no pension
  form; every pension is entered through Fyn. `resources/mobile/views/modules/
  Retirement.vue:190-197` builds the `action: 'add'` contextual request
  unconditionally (suppressed only at the tier cap) and hands it to
  `MobileChrome`, so the add affordance does not depend on pension mix. Same for
  edit: `RetirementPensionDetail.vue:190-205`, `action: 'edit'`. iOS uses the same
  contextual-conversation mechanism.

  **Tests:** `resources/js/components/__tests__/NetWorth/PensionListAddControl.spec.js`
  — 5 specs, all four entry orders plus the empty state plus the newly clickable
  Defined Benefit row. `npx vitest run` → 92 files / 949 tests passed.

  **NOT done by me:** the completeness banner itself was not touched. It was
  correct — it asked for things that genuinely were missing; the fault was the
  missing control, which is now present.

- 2026-08-21 build-lead: batch branch document (also the Rule 22 context handover)
  written to `workforce/branches/fixes/F-0001-batch-c-retirement-profile-gates.md`.
  It carries the dispatch verbatim plus both amendments, per-item file:line
  evidence, test output, decisions taken with reasoning, dead ends ruled out,
  environment state (no throwaway user was created — nothing to tear down), and
  the full W-0018 argument. Every Pest run re-verified under
  `DB_DATABASE=laravel_testing_c` after the shared-database deadlocks.
