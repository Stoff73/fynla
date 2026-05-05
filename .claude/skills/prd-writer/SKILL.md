---
name: prd-writer
description: Generate a production-ready PRD for a Fynla feature by first validating an existing spec and plan against the live codebase (finding conflicts, gaps, cross-purpose planning, and missing integrations), then running a rolling interview with the user to resolve every ambiguity, amending the spec and plan as needed, and only then writing the PRD in the canonical 9-section format. Use whenever the user says "write a PRD", "generate the PRD", "create PRD from spec", "turn this plan into a PRD", or hands over spec/plan paths and asks for product requirements documentation. Also trigger when the user mentions "requirements document", "product spec doc", "formalise a feature", or wants engineering-ready requirements before implementation starts. This skill ONLY works when a spec AND plan already exist — if either is missing, point the user at `superpowers:brainstorming` (spec) or `superpowers:writing-plans` (plan) first.
---

# PRD Writer

Produce a rigorous, codebase-validated PRD from an existing spec and plan. The skill refuses to accept the spec and plan at face value — it drives a subagent-led audit of the real codebase, surfaces every inconsistency, interviews the user to resolve them, and only writes the PRD once the spec, plan, and codebase are in mutual agreement.

## Why this skill exists

Specs and plans are written in isolation from the running code. They drift. They assume routes, services, tables, or components that have been renamed, removed, or refactored. They duplicate existing functionality, violate the design guide, or conflict with architectural patterns (Agents/Services/Controllers layering, PreviewWriteInterceptor, TaxConfigService, joint ownership trait, etc.). Shipping a PRD built on a stale spec means engineers build the wrong thing, and the user pays the cost in a cycle of rework.

The fix is: **validate before documenting**. This skill does that.

## Prerequisites

The skill refuses to proceed if either is missing:

- **Spec** — typically `/Users/CSJ/Desktop/fynla/docs/superpowers/specs/YYYY-MM-DD-{feature}-design.md` or in `/Users/CSJ/Desktop/fynla/{Month}/{Month}{D}Updates/`
- **Plan** — typically `/Users/CSJ/Desktop/fynla/docs/superpowers/plans/YYYY-MM-DD-{feature}.md` or in the same month updates folder

If only a spec exists → tell the user to run `superpowers:writing-plans` first. If only a plan exists → tell the user to run `superpowers:brainstorming` first. Do not synthesise missing inputs.

---

## Workflow

### Phase 1 — Locate and read inputs

1. Ask the user for the feature name (or accept explicit paths). Do not guess.
2. Search both known locations:
   ```
   /Users/CSJ/Desktop/fynla/docs/superpowers/specs/*{feature}*
   /Users/CSJ/Desktop/fynla/docs/superpowers/plans/*{feature}*
   /Users/CSJ/Desktop/fynla/*/*Updates/*{feature}*              (repo month folders, e.g. fynla/April/April17Updates/)
   /Users/CSJ/Desktop/fynlaBrain/*/*Updates/*{feature}*         (vault month folders — fallback only; primary is the repo)
   ```
3. If zero or multiple matches, show the candidates and ask the user to pick.
4. Read both documents in full. Extract into a working note (in your head, not a file):
   - Feature summary and stated scope
   - Entities/models the spec claims to create or modify
   - Routes and API endpoints mentioned
   - Vue components mentioned
   - Services, agents, observers mentioned
   - Database columns/migrations mentioned
   - External integrations (Revolut, Awin, Anthropic, etc.)
   - Stated success criteria or acceptance criteria (if any)
   - Files in the plan's change list

### Phase 2 — Assess scope and dispatch the codebase validation audit

Classify the feature's integration depth to pick the right validation approach:

| Scope | Signals | Agent strategy |
|-------|---------|----------------|
| **Small** | 1-3 files, one Vue component or one service method, UI-only tweak, no DB change | `Explore` (medium thoroughness) — one dispatch |
| **Medium** | 4-15 files, 1-2 modules touched, possibly a migration, CRUD against existing models | `feature-dev:code-explorer` — one dispatch |
| **Large** | 15+ files, cross-module, new agent/service, new table, new external integration, changes to shared patterns (auth, subscription, tax, estate, joint ownership) | `feature-dev:code-explorer` AND `feature-dev:code-architect` in parallel |

The validation agent must produce a **Validation Report** covering these areas. Pass this verbatim in the prompt so the agent knows what to look for:

1. **Entity conflicts** — do the models/tables/columns the spec claims to create or modify already exist? With different names or shapes?
2. **Route conflicts** — do the API endpoints already exist? Do they already return/accept different data?
3. **Component conflicts** — do the Vue components already exist? Are they in a different module directory? Do they already have a different responsibility?
4. **Pattern conflicts** — does the plan violate Fynla conventions? Specifically check:
   - All tax values via `TaxConfigService` / `taxConfig.js` (no hardcoded years, allowances, thresholds)
   - Joint ownership uses `HasJointOwnership` trait and single-record pattern
   - Form modals emit `save` not `submit`
   - No banned colours (amber, orange, primary-*, secondary-*, gray-* for general UI)
   - Preview user isolation (`is_preview_user = true`)
   - `PreviewWriteInterceptor` — new auth POST routes need `EXCLUDED_ROUTES` entry
   - User-facing text spells out acronyms (except ISA)
   - No scores in user-facing UI
5. **Cross-purpose planning** — does the plan solve one problem but create another? e.g. adding a column that duplicates data already computed elsewhere, building a second notification path when one exists, creating a new state store for data already in Vuex.
6. **Design system compliance** — do the UI changes align with `fynlaDesignGuide.md` v1.3.0 colours, typography, and component patterns? Any new CSS patterns that duplicate global classes?
7. **Test and seed impact** — does the plan require new seeders, factories, or test coverage? Does it risk breaking existing seeds (especially preview personas)?
8. **Missing integrations** — what does the plan fail to mention that the real codebase will require? Observers that will need updating? Audit log entries? Cache invalidation? Vuex store updates? Sidebar/router entries?
9. **Gaps between spec and plan** — where does the plan fail to implement something the spec promises, or where does the plan do something the spec didn't ask for?

Example dispatch (medium scope):

```
Agent(
  description: "Validate spec/plan against codebase",
  subagent_type: "feature-dev:code-explorer",
  prompt: """
  Validate the following spec and plan against the Fynla codebase. Report conflicts, gaps, and cross-purpose planning. This is NOT a code review of new code — the code doesn't exist yet. Instead, check whether what the spec and plan describe is consistent with what's already in the codebase.

  Spec: <full spec text>
  Plan: <full plan text>

  Produce a Validation Report with these sections (use exact headings):
  1. Entity conflicts
  2. Route conflicts
  3. Component conflicts
  4. Pattern conflicts (TaxConfigService, joint ownership trait, form save emit, banned colours, preview isolation, PreviewWriteInterceptor, acronyms, no-scores rule)
  5. Cross-purpose planning
  6. Design system compliance (fynlaDesignGuide.md v1.3.0)
  7. Test and seed impact
  8. Missing integrations (observers, audit log, cache invalidation, Vuex, sidebar/router)
  9. Gaps between spec and plan

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
  Review the attached plan against Fynla's existing architecture (Agents → Services → Controllers → Models). Does the plan's proposed structure fit? Are there existing services/agents that should be reused rather than duplicated? Is the layering correct? Does it respect module boundaries (Protection, Savings, Investment, Retirement, Estate, Goals, Coordination)?

  Spec: <full spec text>
  Plan: <full plan text>

  Output: a list of architectural concerns with specific remediations. If the architecture is sound, say so.
  """
)
```

### Phase 3 — Present findings and begin the rolling interview

Present the Validation Report to the user in a compact, readable form. Group findings by severity:

- **🔴 Conflict** — spec/plan is wrong about the codebase; must be corrected
- **🟡 Ambiguity** — spec/plan is silent on something the codebase requires a decision for
- **🟢 Gap** — spec and plan don't agree, or plan missed something the spec requires

Then **interview the user in rolling batches of 2-3 questions**. Not 15 at once — the user will skim and miss things. Rolling interview:

1. Ask 2-3 questions targeted at the highest-severity items
2. Wait for answers
3. Use the answers to narrow the next batch (some questions may no longer apply)
4. Repeat until every 🔴 and 🟡 is resolved
5. For 🟢 items, confirm the intended behaviour with the user

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

- **Grounded in the real codebase.** Reference specific models, routes, components, and services by name. Generic PRDs are useless.
- **British spelling in user-facing text, American in code.** (Optimisation / optimize, Customise / customize.)
- **Tax values are symbolic, not numeric.** Write "the current ISA annual allowance (from `TaxConfigService`)" not "£20,000". The PRD should outlive a tax year rollover.
- **Acronyms spelled out** (except ISA).
- **No scores** in user-facing metrics — use currency, percentages, or time periods.
- **Design decisions reference `fynlaDesignGuide.md` v1.3.0** colours and patterns rather than restating them.
- **Prioritise functional requirements** using `Must-have` / `Should-have` / `Nice-to-have`. Be ruthless — if everything is must-have, the PRD provides no guidance.

### Phase 6 — Save the PRD

1. Compute today's updates folder in the **fynla repo** (not the vault): `/Users/CSJ/Desktop/fynla/{MonthName}/{MonthName}{D}Updates/` where `D` is the day without a leading zero (e.g. `April1Updates`, `April17Updates`). This parallels where deploy notes, CSJTODO, and session handovers live. Do NOT write to `/Users/CSJ/Desktop/fynlaBrain/{Month}/...` — that's the Obsidian vault, a separate location.
2. If the folder doesn't exist, create it.
3. Name the file `PRD-{feature-kebab-case}.md` (match the spec/plan kebab case).
4. Write the file.
5. Report to the user:
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
{Which of the 7 Fynla modules (Protection, Savings, Investment, Retirement, Estate, Goals & Life Events, Coordination) does this touch? How does it relate to recently shipped or upcoming work? Reference prior deploys or CSJTODO items if relevant.}

---

## 2. Target Persona

{Pick from Fynla's seeded personas where applicable — young_family, peak_earners, widow, entrepreneur, young_saver, retired_couple — and explain which persona(s) feel this pain most acutely. If the feature is for advisors or admins, say so explicitly. If it's infrastructure (no user-facing change), write "Infrastructure — indirectly benefits all personas" and explain how.}

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
- **Design system:** Uses `fynlaDesignGuide.md` v1.3.0 — {call out specific colour tokens, typography choices, or component patterns being applied}
- **Reusable components:** {list existing components being reused, e.g. `FormModal.vue`, `AccountForm.vue`}
- **New components (if any):** {list with purpose}
- **Responsive behaviour:** {mobile/tablet/desktop expectations, or "standard responsive — no special treatment"}
- **Accessibility:** {keyboard nav, ARIA, focus management considerations — especially for modals and forms}
- **Reference artefacts:** {paths to screenshots, whiteboard images, sample data, or prior art in fynlaBrain}

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
- {Existing service, trait, or pattern this relies on, e.g. `TaxConfigService`, `HasJointOwnership`}
- {External service, e.g. Revolut API, Anthropic API}

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

---

## What NOT to do

- Don't write a PRD that could apply to any Laravel/Vue app. Fynla-specific or it's useless.
- Don't paste the Validation Report into the PRD — summarise residual concerns only.
- Don't invent metrics. If no baseline exists, say "unknown — requires measurement."
- Don't gold-plate. The PRD is a working document, not a pitch deck.
- Don't skip the rolling interview to save time. The interview is the skill's whole point.
