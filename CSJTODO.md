# CSJTODO — Fynla

*Last updated: 20 April 2026 — session 1 (context-clear)*
*Previous session: 19 April 2026 — session 2 (end-of-day, PM skill run)*

---

## Session 1 (20 April, context-clear) — Fyn onboarding reconciliation + PRD

### Completed This Session

- [x] **Shipped commit `88018a5`** — 4 bug fixes from the 16 April Fyn onboarding test. Each traced in `April/April20Updates/fynChatAnalysis.md`:
  - Bug §1 — add_more Savings→family loop. `persistCapture` now handles `STATE_ADD_MORE`, writing `onboarding_fyn_selection` + `visited_focuses`.
  - Bug §2 — LLM text leak on grouped_extract turns. Director now swallows `content` events so chatty Grok/Claude text doesn't stack with retry_text.
  - Bug §3 — `handleCaptureWorkDetails` all-or-nothing. Now saves partial payloads, computes `missing[]`, director emits `emitPartialRetry` with targeted copy.
  - Bug §4 — onboarding expenditure now writes to `ExpenditureProfile.total_monthly_expenditure` in addition to `users.monthly_expenditure`.
- [x] **11 new unit tests** in `tests/Unit/Services/Onboarding/OnboardingChatDirectorFixesTest.php` (132/132 onboarding suite passing).
- [x] **Browser-tested end-to-end** on localhost:8000 AND https://csjones.co/fynla — fresh user registration → journey pick → family asset_capture → add_more Savings pick correctly fires the savings intro + terminal message "Your savings module is ready".
- [x] **Deployed 88018a5 to csjones.co/fynla** via SSH scp + artisan cache clear (sibling-dir layout at `~/www/csjones.co/fynla-app/`).
- [x] **Wrote `April/April20Updates/fynChatAnalysis.md`** — root-cause trace of the 4 bugs with file:line and fix plan.
- [x] **Wrote `April/April20Updates/fynComprehensiveCheck.md`** — broader audit via 4 parallel subagents covering remaining CoordinatingAgent handlers, asset_capture focus logic, SystemPromptBuilder gaps, navigation/router mismatches. Found 13 items (F1–F13) where the same bug patterns recur + 3 deferred items + 8 additional T-findings, prioritised P0/P1/P2 with file:line and effort estimates.
- [x] **Rewrote `.claude/skills/prd-writer/SKILL.md`** for Fynla UK (was targeting fynlaInternational with pack/core/contracts architecture — wholesale rewrite for single-country UK). Shipped as commit `b736d6e`.
- [x] **Invoked the prd-writer skill** against the onboarding spec+plan as a reconciliation exercise. Validation dispatched to `feature-dev:code-explorer` + `feature-dev:code-architect` in parallel. 6 conflicts (C1–C6), 2 gaps (G1–G2), 13 F-items verified still present.
- [x] **Amended `April/April15Updates/fynOnboardFix.md`** (9 targeted edits: status, §3.3 $fillable→$guarded, §4.1 preview precondition, §5.1 turn types, §5.2 canonical 14-state table, §5.3 hybrid skip, §6.2 SSE events, §10.3 cleanup, new §20 delta register).
- [x] **Amended `April/April15Updates/fynOnboarding.md`** (3 edits: status, implementation status table, resolved-20-April open questions).
- [x] **Produced `April/April20Updates/PRD-fyn-driven-onboarding.md`** — canonical contract for the rest of this release. 7 Must-have (C1, G1, G2, F1, F2, F3, F5), 4 Should-have (F4, F6, 2 cleanup items), 7 Nice-to-have (F7–F13).
- [x] **Committed 32 excalidraw canvases** (`docs/diagrams/*`) from prior skill runs — commit `8cf7e3d`. Cleanup of long-untracked files.
- [x] **Vault-synced** — April20Updates artefacts mirrored to fynlaBrain, Apr20.md daily log created, Apr2026 Commits.md updated (405→408, added Apr20 row), Home.md updated (2,605→2,608 commits, April 14→15 days), April Index updated with session 1 entry + April20Updates file list.

### Source of truth for next session

**`/Users/CSJ/Desktop/fynla/April/April20Updates/PRD-fyn-driven-onboarding.md`** — this is the canonical contract. Read it first.

Also relevant:
- `April/April15Updates/fynOnboardFix.md` §20 — delta register (what's shipped vs what's in scope)
- `April/April20Updates/fynComprehensiveCheck.md` — detailed F1–F13 ledger with file:line

### NOT Done — Outstanding (from PRD)

**Must-have (P0, in scope for this release):**
- [ ] **FR-M9 (C1)** — add `'api/ai-chat/onboarding'` to `PreviewWriteInterceptor::EXCLUDED_ROUTES` so the controller-level 403 check actually runs for preview users on `/onboarding/start`.
- [ ] **FR-M10 (G2)** — hybrid `base_personal` skip rule. If only DOB or only marital is already set, adapt the prompt to ask for only the missing field.
- [ ] **FR-M11 (G1)** — feature tests in `tests/Feature/Onboarding/` covering `POST /ai-chat/onboarding/start` (200/409/403/503), state-machine walkthrough, multi-entity asset_capture.
- [ ] **FR-M12 (F1)** — `handleSetExpenditure` must sync to `ExpenditureProfile.total_monthly_expenditure` alongside the existing `users.*` write (same bug pattern as onboarding fix §4, different layer).
- [ ] **FR-M13 (F2)** — new `SpouseCollisionException`, caught by `handleCaptureSpouseDetails`, surfaced to user via new `emitTerminalError` with copy: *"That email's already registered with another Fynla household. Want to use a different address for your partner, or ask them to link their own account?"*
- [ ] **FR-M14 (F3)** — tighten `OnboardingPromptBuilder::assetCaptureInstructions` with explicit "do not ask about property, mortgages, or anything outside the listed tools" + selective content-event filter in `handleAssetCaptureTurn` (swallow if `?` in content OR zero tool calls; preserve single-sentence confirmations). Prompt-only, `tool_choice='auto'` retained.
- [ ] **FR-M15 (F5)** — move CLT auto-creation from `handleCreateTrust` to a new `TrustObserver` listening on `created`. Eliminates the orphan-CLT risk when user cancels the trust form.

**Should-have (P1, in scope, next iteration):**
- [ ] **FR-S1 (F4)** — `handleUpdateRecord` per-entity field allowlist. `private const ALLOWED_UPDATE_FIELDS` on `CoordinatingAgent` keyed by the 12 entity types in `resolveModel()`.
- [ ] **FR-S2 (F6)** — apply `handleCaptureWorkDetails` partial-capture template to `handleCapturePersonalDetails` and `handleCaptureSpouseDetails`. Director's `composePartialRetryText` already has friendly-map entries for both.
- [ ] **FR-S3** — extract `educationStatusForAge` to `OnboardingValueInterpreter::educationStatusForAge` (duplicated between `CoordinatingAgent:1075` and `OnboardingChatDirector:582`).
- [ ] **FR-S4** — selective content-event filter in `handleAssetCaptureTurn` (refinement of FR-M14).

**Nice-to-have (P2, if time permits):**
- [ ] **FR-N1 (F7)** — surface `users.employer` + `users.occupation` in `SystemPromptBuilder::buildUserProfile`.
- [ ] **FR-N2 (F8)** — `SystemPromptBuilder::calculateTotalExpenditure` fallback to `ExpenditureProfile.total_monthly_expenditure`.
- [ ] **FR-N3 (F9)** — duplicate-name checks on 7 create handlers (`create_trust`, `create_family_member`, `create_business_interest`, `create_asset`, `create_liability`, `create_estate_gift`, `create_chattel`).
- [ ] **FR-N4 (F10)** — `handleUpdateProfile` spouse-linked-user sync.
- [ ] **FR-N5 (F11)** — `handleSetExpenditure` spouse sync for household budget.
- [ ] **FR-N6 (F12)** — add missing routes to `navigate_to_page` allow-list (`/estate/inheritance-tax`, `/settings/privacy`, risk sub-routes, etc).
- [ ] **FR-N7 (F13)** — `handleCreateEstateAsset` + `handleCreateEstateGift` partial-payload tolerance.

### Carried from earlier sessions

- [ ] **Open PR `onboardingFyn` → `dev`** — branch is 77 commits behind origin/dev; merge-back needs cross-reference check per `feedback_merge_branch_conflicts`. Do this AFTER the Must-have items land.
- [ ] **Deploy dev → production (`main`)** — PR #220 tech-debt is in `dev`, not in `main` yet.
- [ ] **Test Fyn chat fixes on dev (csjones.co/fynla)** — carried from session 58, partially addressed by this session's browser test but deeper scenarios still open.
- [ ] **Re-enable branch protection on `dev`** — carried from session 57.
- [ ] **Add `Current State/Insights.md`** to the vault — flagged session 62.
- [ ] **`AutoRiskCalculatorTest` enum truncation** — pre-existing.

### Context for Next Session

Branch: `onboardingFyn` at HEAD `8cf7e3d`, clean working tree, pushed to origin. 77 commits behind origin/dev.

**Start here:**
1. Read `April/April20Updates/PRD-fyn-driven-onboarding.md` in full (it's the contract).
2. Then read the amended `April/April15Updates/fynOnboardFix.md §20` for the delta register.
3. Implementation order (from PRD §Sequencing):
   - **FR-M9 (C1 preview) + FR-M11 (G1 feature tests)** as one PR — the feature tests catch C1 properly.
   - **FR-M12 (F1 expenditure sync)** standalone — small, blocks post-onboarding UX parity.
   - **FR-M10 (G2) + FR-M13 (F2) + FR-M14 (F3)** as a UX-quality batch.
   - **FR-M15 (F5 trust observer)** standalone — independent module, small diff.
   - Should-have batch after.
   - Nice-to-have batch last.

**Branch queue awareness:** still 77 commits behind origin/dev. The `dev` branch moved forward significantly with PR #220 (tech-debt: decimal:2 casts, strict_types, component renames, exception factories). When merging onboardingFyn back, cross-reference `CoordinatingAgent.php` and `AiToolDefinitions.php` — both were touched on both branches.

**Dev server state:** csjones.co/fynla is running the onboardingFyn build post-88018a5. Next deploy to dev should be AFTER onboardingFyn is merged to `dev`, NOT before (per `feedback_dev_server_is_separate`).

---

## Outstanding — Tech Debt Deferred

- [ ] `handleSetExpenditure` spouse sync (F11 — in release scope as Nice-to-have)
- [ ] `handleUpdateProfile` spouse sync (F10 — in release scope as Nice-to-have)
- [ ] 7 entity types missing duplicate-name checks (F9 — in release scope as Nice-to-have)
- [ ] NPM `--force` audit (vite 8 + @capacitor/cli 8 major bumps) — deferred pending iOS regression window
- [ ] `AutoRiskCalculatorTest` enum truncation — pre-existing since 16 April, not related to this work

## Known Issues

- **Spouse email collision loops user with no diagnostic** — tracked as F2/FR-M13 in PRD, Must-have.
- **Post-onboarding Fyn "my rent is £X" doesn't surface on dashboard** — tracked as F1/FR-M12 in PRD, Must-have. Same bug pattern as the onboarding fix in 88018a5, different handler.
- **Family asset_capture occasionally emits off-script property/mortgage questions** — tracked as F3/FR-M14 in PRD, Must-have.
- **`handleUpdateRecord` allows LLM to update any fillable field** — tracked as F4/FR-S1 in PRD. Includes `Trust.settlor`, `Mortgage.start_date`, `FamilyMember.relationship`. Security-adjacent.

## Deploy Status

**Production (fynla.org):** Running commit `a14f17a` (PR #219 Admin Insights CMS) + `062c7c7` (tooling audit). Full Admin Insights CMS live.

**Dev (csjones.co/fynla):** Running onboardingFyn + `88018a5` post-deploy. The 4 bug fixes are live on dev; the remaining P0/P1/P2 work from the PRD is not yet deployed.

**Pending deploy path:** `onboardingFyn → dev` (after all Must-have P0 items land and pass browser verification) → `dev → main` (after dev stability for ≥ 48 hours).
