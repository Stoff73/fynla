---
name: prd-writer
description: Generate a production-ready PRD for a Fynla feature by first validating an existing spec and plan against the live codebase (finding conflicts, gaps, cross-purpose planning, and missing integrations), then running a rolling interview with the user to resolve every ambiguity, amending the spec and plan as needed, and only then writing the PRD in the canonical 9-section format. Use whenever the user says "write a PRD", "generate the PRD", "create PRD from spec", "turn this plan into a PRD", or hands over spec/plan paths and asks for product requirements documentation. Also trigger when the user mentions "requirements document", "product spec doc", "formalise a feature", or wants engineering-ready requirements before implementation starts. This skill ONLY works when a spec AND plan already exist — if either is missing, point the user at `superpowers:brainstorming` (spec) or `superpowers:writing-plans` (plan) first.
---

# PRD Writer (Fynla UK)

Produce a rigorous, codebase-validated PRD from an existing spec and plan. The skill refuses to accept the spec and plan at face value — it drives a subagent-led audit of the real codebase, surfaces every inconsistency, interviews the user to resolve them, and only writes the PRD once the spec, plan, and codebase are in mutual agreement.

**Project root:** `/Users/CSJ/Desktop/fynla`
**Vault:** `/Users/CSJ/Desktop/fynlaBrain` (Obsidian — design guide, deploy notes, session history, 693 docs)

This skill runs from the Fynla UK project — a single-country financial-planning app (Laravel 10 + Vue 3 + MySQL 8). Architecture is `Vue Component → API Service → Controller → Agent → Services → Models → DB`. Seven modules: Protection, Savings, Investment, Retirement, Estate Planning, Goals & Life Events, Coordination. Every tax rule lives in `TaxConfigService`; every UI value comes from the palette in `fynlaDesignGuide.md v1.4.0` (icons-functional-only rule applies).

## Why this skill exists

Specs and plans are written in isolation from the running code. They drift. They assume routes, services, tables, or components that have been renamed, removed, or refactored. They duplicate existing functionality, violate the design guide, or conflict with architectural patterns (Agents/Services/Controllers layering, PreviewWriteInterceptor, TaxConfigService, HasJointOwnership trait, canonical ownership enums, decimal:2 casts, etc.). Shipping a PRD built on a stale spec means engineers build the wrong thing, and the user pays the cost in a cycle of rework.

The fix is: **validate before documenting**. This skill does that.

## Prerequisites

The skill refuses to proceed if either is missing:

- **Spec** — typically in one of:
  - `/Users/CSJ/Desktop/fynla/docs/superpowers/specs/YYYY-MM-DD-{feature}-design.md`
  - `/Users/CSJ/Desktop/fynla/April/{Month}{D}Updates/` (day-stamped session outputs and handovers)
  - `/Users/CSJ/Desktop/fynlaBrain/April/{Month}{D}Updates/` (vault mirrors)
- **Plan** — typically in one of:
  - `/Users/CSJ/Desktop/fynla/docs/superpowers/plans/YYYY-MM-DD-{feature}.md`
  - `/Users/CSJ/Desktop/fynla/April/{Month}{D}Updates/`
  - `/Users/CSJ/.claude/plans/` (Manus-style planning output)

If only a spec exists → tell the user to run `superpowers:writing-plans` first. If only a plan exists → tell the user to run `superpowers:brainstorming` first. Do not synthesise missing inputs.

---

## Workflow

### Phase 1 — Locate and read inputs

1. Ask the user for the feature name (or accept explicit paths). Do not guess.
2. Search all known locations in order:
   ```
   /Users/CSJ/Desktop/fynla/docs/superpowers/specs/*{feature}*
   /Users/CSJ/Desktop/fynla/docs/superpowers/plans/*{feature}*
   /Users/CSJ/Desktop/fynla/April/*Updates/*{feature}*
   /Users/CSJ/Desktop/fynlaBrain/April/*Updates/*{feature}*
   /Users/CSJ/.claude/plans/*{feature}*
   ```
3. If zero or multiple matches, show the candidates and ask the user to pick.
4. Read both documents in full. Extract into a working note (in your head, not a file):
   - Feature summary and stated scope
   - Which of the 7 modules the feature targets (Protection / Savings / Investment / Retirement / Estate / Goals & Life Events / Coordination) or whether it's cross-module / frontend-only / AI / infrastructure
   - Entities/models the spec claims to create or modify
   - Routes and API endpoints mentioned (all Fynla API routes live under `/api/*`)
   - Vue components mentioned (paths under `resources/js/components/{Module}/` or `resources/js/views/`)
   - Services, agents, observers, traits mentioned
   - Database columns/migrations mentioned
   - Whether the feature touches tax-sensitive logic (anything in Protection, Retirement, Estate IHT, Savings ISA allowances, Investment CGT, Income tax bands)
   - External integrations (Revolut, Awin, Anthropic, Grok/xAI, Capacitor iOS)
   - Stated success criteria or acceptance criteria (if any)
   - Files in the plan's change list

### Phase 2 — Assess scope and dispatch the codebase validation audit

Classify the feature's integration depth to pick the right validation approach:

| Scope | Signals | Agent strategy |
|-------|---------|----------------|
| **Small** | 1–3 files, one Vue component or one service method, UI-only tweak, no DB change | `Explore` (medium thoroughness) — one dispatch |
| **Medium** | 4–15 files, 1–2 modules touched, possibly a migration, CRUD against existing models | `feature-dev:code-explorer` — one dispatch |
| **Large** | 15+ files, cross-module, new agent/service, new table, new external integration, changes to shared patterns (auth, subscription, tax, estate, joint ownership, AI chat, onboarding) | `feature-dev:code-explorer` AND `feature-dev:code-architect` in parallel |

The validation agent must produce a **Validation Report** covering these areas. Pass this verbatim in the prompt so the agent knows what to look for:

1. **Entity conflicts** — do the models/tables/columns the spec claims to create or modify already exist? With different names or shapes? Check `app/Models/` and `database/migrations/`. Flag duplicate fields that already exist elsewhere (e.g. an expenditure field being added to `users` when `ExpenditureProfile` already holds it).
2. **Route conflicts** — do the API endpoints in the plan already exist at `routes/api.php`? Do they already return/accept different data? Does the plan respect the Sanctum auth middleware layering?
3. **Component conflicts** — do the Vue components already exist? Are they in a different module directory? Do they already have a different responsibility? Are multi-word component names used (never single-word like `Dashboard.vue` for a leaf — Fynla enforces component naming at the arch-test level).
4. **Architecture conflicts** — does the plan violate Fynla's layering?
   - Vue component → API service → Controller → Agent → Services → Models → DB (never skip Agent for module work)
   - Controllers never use `DB::` facade directly — always go through services/models
   - Agents extend `BaseAgent` and implement `analyze() / generateRecommendations() / buildScenarios()`
   - Services are single-responsibility with `private readonly` constructor injection
   - Observers follow the `RiskRecalculationObserver` debounced-dispatch pattern
5. **Tax & financial value rules** — does the plan respect the no-hardcode rules?
   - All UK tax values via `TaxConfigService` (backend) and `getCurrentTaxYear() + taxConfig.js` (frontend). No hardcoded years, allowances, thresholds, or rates.
   - Financial casts on models must be `decimal:2` (per `tests/Architecture/MonetaryCastsArchitectureTest.php`). Do NOT introduce `'float'` casts on monetary columns.
   - Currency formatting via `currencyMixin` (frontend) or `FormatsCurrency` trait (backend) — never a local `formatCurrency()`.
6. **Pattern conflicts** — does the plan violate Fynla conventions?
   - Joint ownership uses `HasJointOwnership` trait and single-record pattern (`joint_owner_id` + `ownership_percentage`). Never duplicate records for joint owners.
   - Canonical ownership enum values: `individual`, `joint`, `tenants_in_common`, `trust`. Never `sole`.
   - Form modals emit `save` not `submit` (prevents double-submission; parent handles API + close).
   - Preview user isolation: any write-path touches must respect `is_preview_user` flag; `PreviewWriteInterceptor` blocks writes from preview users. New auth POST routes must be added to `EXCLUDED_ROUTES` in `app/Http/Middleware/PreviewWriteInterceptor.php`.
   - User-facing text spells out acronyms (except ISA). Watch for AA, S&S, DB, DC, MPAA, RNRB — spell them out.
   - No scores in user-facing UI (scores oversimplify and mislead). Use currency, percentages, or time periods.
   - No banned colours: `amber-*`, `orange-*`, `primary-*`, `secondary-*`, `gray-*` for general UI. Palette is raspberry / horizon / spring / violet / savannah / eggshell / neutral.
7. **Icon-surface rules** (design guide v1.4.0 §top)
   - **Banned surfaces — no icons at all:** Fyn chat window (messages, quick replies, chrome, streaming indicators, conversation history); dashboard cards (every module card, summary card, metric tile); detail views (every module page and its drill-downs).
   - **Allowed surface — functional need:** the side nav (collapses to icon-only mode — icons are load-bearing there).
   - **Ambiguous surfaces — ASK before adding/removing:** modals, top navbar, forms, alerts, tables, badges, toasts, tooltips, settings pages, admin pages, onboarding wizards, mobile app, public/marketing pages. Default answer is NO icon.
   - Always banned everywhere: emoji in labels/buttons/tooltips/AI responses/titles, Unicode glyphs as icons (★ ✓ → ⚠ ℹ), CSS `::before`/`::after` glyph injection, icon fonts.
8. **Cross-purpose planning** — does the plan solve one problem but create another? e.g. adding a column that duplicates data already computed elsewhere, building a second notification path when one exists, creating a new Vuex store for data already in an existing module, writing to `users.monthly_expenditure` while the dashboard reads `ExpenditureProfile.total_monthly_expenditure` (a real 16 April bug class).
9. **Design system compliance** — do the UI changes align with `fynlaDesignGuide.md` v1.4.0 at `/Users/CSJ/Desktop/fynlaBrain/Design/fynlaDesignGuide.md`? Specifically: Segoe UI typography (fallback Inter), font weights 900 (display/h1) and 700 (h2–h5), palette tokens from v1.4.0 only, global classes for scrollbars/animations/spinners/badges/cards, and the icon surface rules above.
10. **Test and seed impact** — does the plan require new seeders, factories, or test coverage? Does it risk breaking existing seeds (especially `PreviewUserSeeder`, `TaxConfigurationSeeder`, `ChrisUserSeeder`)? Does it need a new Pest test suite entry?
11. **Mobile (Capacitor iOS) implications** — if the feature touches frontend, does it have to work on the iOS Capacitor build too? Check whether the change adds `import("/images/…")` (known to cause `'image/png' is not a valid JavaScript MIME type'` blank-screen on WKWebView — see `memory/mobile_capacitor_patterns.md`). Does it affect the biometric/Face ID login path (`attemptBiometricLogin` in `app.js`, `auth/mobileLogout` vs `auth/logout`)?
12. **Missing integrations** — what does the plan fail to mention that the real codebase will require? Observers that will need updating? Audit log entries (`Auditable` trait)? Cache invalidation (`invalidateUserCache`)? Vuex store updates? Sidebar/router entries with correct `meta: {requiresAuth, public, previewMode}`? Risk recalculation triggers? Monte Carlo invalidation?
13. **Gaps between spec and plan** — where does the plan fail to implement something the spec promises, or where does the plan do something the spec didn't ask for?

Example dispatch (medium scope):

```
Agent(
  description: "Validate spec/plan against Fynla codebase",
  subagent_type: "feature-dev:code-explorer",
  prompt: """
  Project root: /Users/CSJ/Desktop/fynla
  This is Fynla UK — a single-country financial-planning app. Architecture: Vue → API service → Controller → Agent → Services → Models. Seven modules. Tax via TaxConfigService (never hardcode). Read `/Users/CSJ/Desktop/fynla/CLAUDE.md` + the module-level CLAUDE.md files (app/Http, app/Services, database, resources/js, tests) before starting.

  Validate the following spec and plan against the Fynla codebase. Report conflicts, gaps, and cross-purpose planning. This is NOT a code review of new code — the code doesn't exist yet. Instead, check whether what the spec and plan describe is consistent with what's already in the codebase.

  Spec: <full spec text>
  Plan: <full plan text>

  Produce a Validation Report with these sections (use exact headings):
  1. Entity conflicts
  2. Route conflicts
  3. Component conflicts
  4. Architecture conflicts (Agent → Services → Controllers layering, BaseAgent, no DB facade in controllers, private readonly injection)
  5. Tax & financial value rules (TaxConfigService, decimal:2 casts on money, currencyMixin, FormatsCurrency)
  6. Pattern conflicts (HasJointOwnership trait, canonical ownership enums, form save emit, preview isolation, PreviewWriteInterceptor EXCLUDED_ROUTES, acronyms, no-scores, palette, banned colours)
  7. Icon-surface rules (chat/cards/detail views banned; side nav allowed; ambiguous surfaces require ASK; emoji/glyphs/icon fonts banned everywhere)
  8. Cross-purpose planning (duplicate fields, write-to-A-read-from-B patterns, duplicate notification/state paths)
  9. Design system compliance (fynlaDesignGuide.md v1.4.0 typography / palette / global classes / icon rules)
  10. Test and seed impact (Pest, RefreshDatabase, PreviewUserSeeder, TaxConfigurationSeeder, architecture tests)
  11. Mobile (iOS Capacitor) implications (WKWebView MIME, biometric flow, cache invalidation)
  12. Missing integrations (observers, Auditable, Vuex, sidebar/router meta, risk recalc, Monte Carlo)
  13. Gaps between spec and plan

  For each finding: cite the exact file:line in the codebase, quote the relevant spec/plan passage, and state the specific conflict or gap. Do not speculate. If a claim checks out, say "no issue found" — do not pad.

  Also identify ambiguities — places where the spec or plan is unclear, contradictory, or leaves a decision unmade. The user will be interviewed about these.
  """
)
```

For large scope, dispatch `feature-dev:code-architect` in parallel with a prompt focused on architectural fit:

```
Agent(
  description: "Architectural review of proposed plan",
  subagent_type: "feature-dev:code-architect",
  prompt: """
  Project root: /Users/CSJ/Desktop/fynla
  Context: Fynla's layered architecture — Vue Component → API Service → Controller → Agent → Services → Models → DB. Seven modules (Protection, Savings, Investment, Retirement, Estate, Goals & Life Events, Coordination) each with a BaseAgent subclass in app/Agents/ orchestrating the module's domain services in app/Services/{Module}/. Shared behaviour via traits (Auditable, HasJointOwnership, CalculatesOwnershipShare, FormatsCurrency, StructuredLogging, ResolvesExpenditure, ResolvesIncome, TracksGoalContributions, HasAiChat, HasAiGuardrails).

  Read /Users/CSJ/Desktop/fynla/CLAUDE.md + /Users/CSJ/Desktop/fynla/app/Services/CLAUDE.md + /Users/CSJ/Desktop/fynla/app/Http/CLAUDE.md before starting.

  Review the attached plan against this architecture. Does the plan's proposed structure fit? Are there existing services/agents that should be reused rather than duplicated? Is the layering correct? Does it respect module boundaries? Does it correctly place shared logic in a trait vs creating a new one-off service?

  For anything touching AI chat / onboarding, check against the existing CoordinatingAgent + HasAiChat + OnboardingChatDirector + OnboardingStateMachine. These are already structured — new chat logic should extend that scaffolding, not duplicate it.

  For anything touching tax logic, check the plan uses TaxConfigService rather than introducing new hardcoded rates.

  Spec: <full spec text>
  Plan: <full plan text>

  Output: a list of architectural concerns with specific remediations. If the architecture is sound, say so.
  """
)
```

### Phase 3 — Present findings and begin the rolling interview

Present the Validation Report to the user in a compact, readable form. Group findings by severity:

- **Conflict** — spec/plan is wrong about the codebase; must be corrected
- **Ambiguity** — spec/plan is silent on something the codebase requires a decision for
- **Gap** — spec and plan don't agree, or plan missed something the spec requires

Then **interview the user in rolling batches of 2-3 questions**. Not 15 at once — the user will skim and miss things. Rolling interview:

1. Ask 2-3 questions targeted at the highest-severity items
2. Wait for answers
3. Use the answers to narrow the next batch (some questions may no longer apply)
4. Repeat until every Conflict and Ambiguity is resolved
5. For Gap items, confirm the intended behaviour with the user

Each question must be specific and actionable. Good: *"The spec says 'Fyn will create a savings account' but the plan has no Vuex action for `savings/createAccount` — did you intend the existing `savings/addAccount` action, or is this a new path?"* Bad: *"Any thoughts on the savings module?"*

**If the user's answer contradicts the spec or plan, say so explicitly and flag it for amendment in Phase 4.** Do not silently reconcile.

### Phase 4 — Amend the spec and the plan

Once every open question is answered, update both source documents in place:

1. Show the user the exact diffs you intend to apply (as patches, not descriptions)
2. Ask for explicit approval to apply them
3. On approval, use the `Edit` tool to apply changes. Never rewrite the files wholesale — targeted edits preserve authorial voice.
4. Add a `Status` line or update the existing one: `**Status:** Amended — {today's date} — conflicts resolved against codebase audit`
5. Confirm to the user what was changed and where

If the user wants to push back on any amendment, accept it — they may know something about upcoming work the audit didn't capture. Update your understanding accordingly, but do not write the PRD until the user explicitly confirms the spec and plan are final.

### Phase 5 — Write the PRD

Use the template in the next section. Every section must be populated from the (now-validated) spec, plan, interview answers, and codebase context. Do not invent content to fill a section — if a section legitimately has nothing to say, write `_Not applicable — {one-line reason}_`.

Rules for PRD content:

- **Grounded in the real codebase.** Reference specific models, routes, components, and services by name, with file paths relative to `/Users/CSJ/Desktop/fynla/`. Generic PRDs are useless.
- **British spelling in user-facing text, American in code.** (Optimisation / optimize, Customise / customize.)
- **Tax values are symbolic, not numeric.** Write "the current ISA annual allowance (from `TaxConfigService`)" not "£20,000". The PRD should outlive a tax year rollover.
- **Financial casts are decimal:2.** If the PRD introduces new monetary columns, state the cast.
- **Acronyms spelled out** (except ISA). Watch for AA, S&S, DB, DC, MPAA, RNRB.
- **No scores** in user-facing metrics — use currency, percentages, or time periods.
- **Design decisions reference `fynlaDesignGuide.md` v1.4.0** (current version in `/Users/CSJ/Desktop/fynlaBrain/Design/`) colours, typography, patterns, and icon rules rather than restating them.
- **Prioritise functional requirements** using `Must-have` / `Should-have` / `Nice-to-have`. Be ruthless — if everything is must-have, the PRD provides no guidance.
- **Preview-user behaviour is implicit.** If a feature touches write paths, state whether preview users see blocked responses or a sandboxed equivalent.

### Phase 6 — Save the PRD

1. **Primary location** — save inside the project's month-updates folder:
   `/Users/CSJ/Desktop/fynla/April/{MonthName}{D}Updates/` where `D` is the day without a leading zero (e.g. `April1Updates`, `April17Updates`).
2. If the folder doesn't exist, create it.
3. Name the file `PRD-{feature-kebab-case}.md` (match the spec/plan kebab case).
4. Write the file.
5. **Vault mirror (optional, ask first)** — if the user wants it available in the Obsidian vault, also copy to `/Users/CSJ/Desktop/fynlaBrain/April/{MonthName}{D}Updates/`. Don't mirror without being asked — the vault is for cross-session reference, not every artefact.
6. Report to the user:
   - Path to the saved PRD
   - Paths to the amended spec and plan
   - One-line summary of the most material changes

---

## PRD Template

Use this exact structure. Embed the template in the output verbatim, filling every section. Maintain the heading levels.

```markdown
# PRD — {Feature Title}

**Project:** {Feature Title}
**Owner:** {User's name or "CSJ" if unknown}
**Status:** Draft
**Date:** {today, DD Month YYYY}
**Spec:** `{path to amended spec}`
**Plan:** `{path to amended plan}`
**Codebase audit:** Completed {today} — see Risks & Dependencies for residual concerns

---

## 1. Context & Why

### Problem
{What's broken, missing, or painful in Fynla today. Be specific — cite the module and the user experience. Avoid "users want X" framing; explain the friction.}

### Business case
{Why now? What strategic goal does this serve? Tie to revenue, retention, compliance, trust, or unlock-for-future-work. If the connection is weak, say so — don't manufacture importance.}

### Strategic fit
{Which of the 7 Fynla modules (Protection, Savings, Investment, Retirement, Estate, Goals & Life Events, Coordination) does this touch? How does it relate to recently shipped or upcoming work? Reference prior deploys, CSJTODO items, or recent April{D}Updates handovers if relevant.}

---

## 2. Target Persona

{Pick from Fynla's seeded preview personas where applicable:

- **young_family** — James & Emily Carter — mortgage, workplace pensions, early savings
- **peak_earners** — David & Sarah Mitchell — multiple properties, SIPP + NHS pension, ISA maxed
- **entrepreneur** — Alex Chen — Ltd Co director, SIPP, business interests
- **young_saver** — John Morgan — emergency fund, first-time savings, student loan
- **retired_couple** — Patricia & Harold Bennett — decumulation, estate planning, RNRB
- **student** — Janice Taylor — LISA, student loan, early-career planning

Explain which persona(s) feel this pain most acutely. If the feature is for advisors or admins, say so explicitly. If it's infrastructure (no user-facing change), write "Infrastructure — indirectly benefits all personas" and explain how.}

**Primary:** {persona + why}
**Secondary:** {persona + why, or "None"}

---

## 3. Success Metrics (KPIs)

{Concrete, measurable, with a target and a measurement window. Prefer metrics Fynla can actually measure — database counts, API response times, user action rates, error rates — over metrics requiring new analytics infrastructure. If new measurement is needed, flag it as a dependency in section 9.}

| Metric | Baseline | Target | Measurement |
|--------|----------|--------|-------------|
| {e.g. % of users completing X flow} | {current or "unknown"} | {target %} | {how measured, when} |

---

## 4. User Stories & Scenarios

### User stories
{Use "As a [persona], I want to [action] so that [benefit]" format. Group by persona if multiple. Cover the primary journey and the main variations.}

- As a **{persona}**, I want to **{action}** so that **{benefit}**.
- ...

### Key scenarios
{Narrative walkthroughs of 2-4 representative journeys. Include the unhappy path — what happens if validation fails, if the user aborts, if they're in preview mode.}

**Scenario 1 — {name}:**
1. {Step}
2. {Step}
3. {Expected outcome}

---

## 5. Functional Requirements

Prioritised using MoSCoW. Each requirement references the module and the specific backend/frontend touchpoints from the plan.

### Must-have
- **FR-M1:** {Requirement}. _Touches: `{component or service}`._
- **FR-M2:** ...

### Should-have
- **FR-S1:** {Requirement}. _Touches: ..._

### Nice-to-have
- **FR-N1:** {Requirement}. _Touches: ..._

---

## 6. User Flow & UX/Design

### Flow
{Either a numbered flow or an ASCII/mermaid diagram. Reference actual route paths and component names from the plan. Show the happy path and call out where the unhappy path branches.}

### UX/Design notes
- **Design system:** Uses `fynlaDesignGuide.md v1.4.0` at `/Users/CSJ/Desktop/fynlaBrain/Design/fynlaDesignGuide.md` — {call out specific colour tokens, typography choices, or component patterns being applied}
- **Icon surface:** {state which surface the feature lives on — banned (chat/cards/detail) → no icons; side nav → allowed; ambiguous → decision and why}
- **Reusable components:** {list existing components being reused, e.g. `FormModal.vue`, `AccountForm.vue`}
- **New components (if any):** {list with purpose and file path — e.g. `resources/js/components/Fyn/FynQuickReplies.vue`}
- **Responsive behaviour:** {mobile/tablet/desktop expectations, or "standard responsive — no special treatment"}
- **iOS Capacitor:** {state whether mobile build is in scope, and flag any WKWebView risks like image imports in JS bundles}
- **Accessibility:** {keyboard nav, ARIA, focus management considerations — especially for modals and forms}
- **Reference artefacts:** {paths to screenshots, whiteboard images, sample data, or prior art in the fynlaBrain vault}

---

## 7. Out of Scope

{Explicit list of things this feature is NOT doing. Each item should be something a reasonable reader might otherwise assume is in scope. Don't list obviously unrelated things — that's noise.}

- {Thing 1}
- {Thing 2}
- ...

---

## 8. Risks & Dependencies

### Risks
| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| {e.g. Migration on large table locks production} | {Low / Med / High} | {Low / Med / High} | {Specific plan} |

### Technical dependencies
- {Existing service, trait, or pattern this relies on — e.g. `TaxConfigService`, `HasJointOwnership`, `Auditable`, `CoordinatingAgent`, `OnboardingChatDirector`, `ExpenditureProfile`}
- {External service, e.g. Revolut API, Anthropic API, Grok/xAI, Awin}
- {Observer or job dependencies, e.g. `RecalculateRiskProfileJob`, `JurisdictionDetectionObserver` equivalents}

### Sequencing dependencies
- {Other work that must ship first, or that this blocks}

### Residual concerns from codebase audit
{Anything from Phase 2's Validation Report that wasn't fully resolved in Phase 4. If fully resolved, write "None — all audit findings addressed in amended spec/plan."}

---

## 9. Document History

| Date | Change | By |
|------|--------|-----|
| {today} | Initial draft | prd-writer skill |
```

---

## Edge cases and judgement calls

- **The user already has a PRD draft.** Read it first. Treat it as another input to validate, not as output. Amend it the same way you amend the spec and plan.
- **The validation agent returns "no issues."** Verify by spot-checking 2-3 of the plan's file paths yourself before trusting it. Agents sometimes skim.
- **The user resists amending the spec.** That's their call — they may know context the audit missed. Respect it, but log the unresolved item in the PRD's "Residual concerns" section.
- **The spec and plan are massive (50+ pages combined).** Don't try to fit everything into the context window at once. Read summaries + first/last sections, dispatch the validation agent with full paths and let it read the files directly, then pull only the findings into your working memory.
- **The user asks to skip the codebase audit.** Push back once, explain why the audit exists (it's the reason this skill exists in the first place). If the user still says skip, proceed without it but explicitly mark the PRD status as `Draft — codebase audit skipped at user request` so a future reader knows.
- **Feature is live / partially shipped.** If the plan has already been implemented in whole or in part (onboardingFyn branch is a good example — see `88018a5`), include a dedicated "Status against live code" subsection in section 1 (Context) listing what's already shipped, what's in flight, and what's still planned. The PRD becomes a reconciliation document, not a forward-looking one.

---

## What NOT to do

- Don't write a PRD that could apply to any Laravel/Vue app. Fynla-specific or it's useless.
- Don't paste the Validation Report into the PRD — summarise residual concerns only.
- Don't invent metrics. If no baseline exists, say "unknown — requires measurement."
- Don't gold-plate. The PRD is a working document, not a pitch deck.
- Don't skip the rolling interview to save time. The interview is the skill's whole point.
- Don't hardcode tax values. Every number comes from `TaxConfigService` or is symbolic.
- Don't propose float casts on monetary fields. `decimal:2`, per the architecture test.
- Don't propose silent writes in preview mode. Preview users are isolated — either block writes or return a sandboxed shape.
- Don't reference the Fynla International project — that's a separate codebase at `/Users/CSJ/Desktop/fynlaInternational` and not in scope for this skill.
