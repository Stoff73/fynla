# CSJTODO — Fynla

*Last updated: 26 May 2026 — end-of-day wrap — SP1 Pass 3 (Pensions) shipped + Pass 4 (Properties) PRs 1-3 landed + CoALA implementation plan v0.4 with six engineering-ready PRDs delivered*

---

## NEW workstream: CoALA — Fyn cognitive-architecture refactor

Tech spec, stakeholder brief, and six implementation PRDs all delivered today. **No code changes yet** — this is the planning layer.

### Stakeholder gates
- [ ] **Stakeholder review of `May/May26Updates/fynla-coala-stakeholder-brief.html`** — non-technical brief for leadership. Three decision points: phase ordering, cutover approach (Option A vs B), when to revisit canonical contract.
- [ ] **Endorse direction + fund Phase 1 + Phase 5 as paired investment** (per stakeholder brief's "What we're asking for").

### Decisions blocking Phase 1 sub-plan
- [ ] **Embeddings provider** — Anthropic, xAI, or OpenAI for semantic memory + episodic similarity recall. Recommendation: same provider as chat LLM (Anthropic) to minimise operational complexity.
- [ ] **Option A vs Option B** for class structure in Phase 5. PRDs default to Option B (shared `FynLoop` with `AdviceFyn` + `OnboardingChatDirector` thin shells; preserves canonical contract; defence-in-depth on write safety; smaller eval rebaseline). Option A = full class merge, revises canonical contract.

### Phase order (recommended)
1. **Pre-Phase-1** — Phase 5 cache-telemetry-only PR (establishes prefix-cache hit-rate baseline; gates Phase 1 cutover).
2. **Phase 1** — Semantic memory `.md` corpus + embeddings + effective-date retrieval. Foundation.
3. **Phase 2** — Episodic SQL + `.md` hybrid (resolves the deferred forensic-purge debt at MEMORY.md `project_ai_messages_forensic_columns_need_purge.md`).
4. **Phase 3** — Consolidated `WorkingMemory` VO (deprecates orphan `persona_state`).
5. **Phase 4** — Procedural `.md` corpus (overlays, workflows, tool schemas; static `FynSystemPrompt::text()` stays for prefix-cache).
6. **Phase 5** — Decision loop + `ground` gate + concurrent-turn queue + per-action cost telemetry. **`ground` gate ships as its own PR FIRST** before any planner work.
7. **Re-review gate** — checkpoint with stakeholders before Phase 6.
8. **Phase 6** — Gated learning (human-reviewed for semantic, engineering-reviewed for procedural).

### Source artifacts
- Tech spec: `fynla-coala-implementation-plan.md` (v0.4) at repo root.
- Stakeholder brief: `fynla-coala-stakeholder-brief.md` at repo root.
- HTML renders: `May/May26Updates/fynla-coala-implementation-plan.html` + `fynla-coala-stakeholder-brief.html`.
- Six PRDs: `May/May27Updates/PRD-coala-phase-{1..6}-*.md`.

---

---

## Outstanding right now

### Merge decisions (CSJ's call)
- [ ] **PR #375** — `feat(mobile): scaffold → real placeholder UI with wired drill-downs`. 2 commits. Self-tested via Playwright headless chromium iPhone 15 Pro: 6 drill-downs work, 0 errors. Awaiting CSJ skim before merge to `dev`.
- [ ] **PR #376** — `docs(audit): SP1 pass-3 pensions pre-pass code-state audit (PR 0)`. Pure docs. 3 plan-adjustment findings (20 mutation sites vs 17; `StaticTierGate::LIMITS` dead; `TierGate` is an interface). Verdict READY for PR 1.

### csjones deploy
- [ ] **csjones is 12 commits behind `dev`.** Mobile scaffold runtime fix + drill-downs + B2 admin-edit fix + B2 audit memo + Pass 3 plan + Store.md docs + seeder fix — all on `dev`, none on csjones.
- [ ] **Tax Settings round-trip smoke** (deferred from yesterday) — `https://csjones.co/fynla/admin` → Tax Settings → edit + save + reload + confirm persist. Gated on csjones deploy above.

### Pass 3 (Pensions) execution — **SHIPPED 2026-05-26**
- [x] **PR 1** — PensionStore facade + arch boundary (#378). Merged.
- [x] **PR 2** — HTTP form requests → PensionStore (#379). Merged.
- [x] **PR 3** — Fyn AI write tools → PensionStore (#380). Merged.
- [x] **PR 4** — Upload + seeders → PensionStore (#381). Merged.
- [x] **PR 5** — 32 read consumers → PensionStore (PRs 5a–5g, plus #382 + #383 follow-ups). All merged.
- [x] **PR 6** — Derived columns + snapshot tables (#383). Merged.
- [x] **PR 7** — Tier cap test for pension_account (#384). Merged.
- [x] **PR 8** — Lock-down: allowlist LOCKED + ingest_source on audit rows (#384). Merged.
- [x] **§16 close-out** — PensionStore.md + three-ingest parity test (#385). Merged.

### Pass 4 (Properties) execution — **IN FLIGHT**
- [x] **Plan written** — `docs(plan): SP1 Pass 4 — Properties canonical store implementation plan` (#386). Merged.
- [x] **PR 1** — PropertyStore facade + arch boundary (#387). Merged.
- [x] **PR 2** — HTTP form requests → PropertyStore (#388). Merged.
- [x] **PR 3** — Fyn AI write tools → PropertyStore. Committed (`19fe052`) on `feat/property-store-pr3`. **Not yet PR'd / merged.**
- [ ] **PR 4–N** — remaining Pass 4 PRs per plan. Not started.

### SP1 spec backlog (Pass 5+)
- [ ] **Pass 5** — Liabilities (incl. mortgages). No plan.
- [ ] **Pass 6** — Investments (multi-table: `investment_accounts` + `holdings` + `investment_transactions`). No plan.
- [ ] **Pass 7** — Income + Expenditure. No plan.
- [ ] **Pass 8** — Protection. No plan.
- [ ] **Pass 9** — Family members. No plan.
- [ ] **Pass 10** — Goals + life events. No plan.
- [ ] **Pass 11** — Chattels. No plan.
- [ ] **Pass 12** — Business interests. No plan.
- [ ] **Pass 13** — Trusts. No plan.
- [ ] **Pass 14** — Wills + LPAs (repurposed from builders). No plan.

### SP1 Pass 1 (Savings) — straggling acceptance items
- [ ] **`app/Services/Stores/SavingsStore.md`** — missing. Pass 1 didn't include it; spec §16.2.5 requires per-entity docs. Pass 2's 4 docs landed today; Savings still needs one.
- [ ] **`SavingsAccountRestored` event** — spec §11.1 requires 4 events per entity; Pass 1 shipped 3 (`Created`, `Updated`, `Deleted` only).

### Mobile (SP3 deferred redesign — wholly unstarted)
- [ ] No plan, no spec, no sub-project number yet. Today's PR #375 only converted the SP3 scaffold from JSON dump to placeholder graphic + drill-downs — it is still scaffold-grade. The full redesign (mobile design system, real navigation, settings, profile, Fyn chat surface, biometric, native iOS polish, gestures, transitions, empty states) is not in flight.

### Deferred / parked
- [ ] **PR #353** — automated-marketing static pages. Older, parked.
- [ ] **PR #249** — Python Agent SDK sidecar. PARKED per memory `reference_pr249_python_sidecar_parked.md`. Do NOT merge.
- [ ] **Cassette C1** — deferred post-Fyn-refactor per memory `project_cassette_provenance_deferred_post_refactor.md`.
- [ ] **Migration `2026_02_27_200003_add_ai_chat_enabled_to_users_table`** — dead on disk after PR #374's seeder fix. Decide: delete the migration file OR regenerate schema dump with the column. Not blocking.

## Tech debt deferred from this session

From `tech-debt-report.md` (regenerated 2026-05-25):

- [ ] **S1** — duplicate `formatCurrency`/`formatPercent` across `resources/mobile/views/Dashboard.vue` + `ModuleDetail.vue`. Extract to `resources/mobile/utils/format.js` when there's a 3rd consumer.
- [ ] **S2** — `formatFieldValue()` long if-chain in `ModuleDetail.vue:138-167`. Scaffold-grade; redesign replaces it.
- [ ] **S3** — dead `ai_chat_enabled` migration (see "Deferred" above).
- [ ] **S4** — hardcoded hex in `resources/mobile/style.css`. Intentional per SP3 isolation; revisit during redesign with CSS custom properties.

## Known issues

- **No automated mobile scaffold tests.** Only `/tmp/mobile-smoke-2026-05-25.mjs` + `/tmp/mobile-drilldown-smoke.mjs` (throwaway). Real Pest browser tests don't exist for the scaffold; redesign should bring them.
- **`schema:dump` regression risk.** PR #374's bug came from running `schema:dump` against a DB where the `ai_chat_enabled` column had been dropped manually; the dump captured the dropped state and the migration silently became dead. No process-level fix.
- **Mobile scaffold dashboard JSON dump issue (FIXED today)** — was caused by `VITE_API_BASE_URL=https://fynla.org` baked into the bundle at build time, blocked by local CSP. Fixed in PR #375 via runtime `window.Capacitor.isNativePlatform()` detection.

## Deploy status

- **`main` (fynla.org):** last release 22 May. **Behind dev by ~125 commits.** No production deploy in flight.
- **`dev` ↔ csjones:** csjones is 12 commits behind `dev` HEAD `72e6e5e`. **Csjones deploy is the next pre-merge gate for PR #375 + the 4 wrap-up PRs from today** if CSJ wants real-browser confirmation before main.

---

## Session log (newest first)

### Session — 26 May 2026 (end-of-day) — CoALA planning + handover

**Branch:** `feat/property-store-pr3` (carrying SP1 Pass 3 + Pass 4 + today's CoALA docs) · **Tree:** clean · **2 commits this session** (b613426 plan+brief; f5a2412 PRDs)

#### Done — CoALA workstream (this session, docs-only)
- [x] **CoALA implementation plan v0.4** at `fynla-coala-implementation-plan.md` — 734 lines with 6 mermaid diagrams. Re-framed against canonical Two-Fyn contract; six phases each independently shippable; storage layer decision (semantic + procedural = git-tracked .md, episodic = SQL + .md hybrid resolving forensic-purge debt); decision-loop spec with concurrent-turn queue + half-finished-session resumption check; Option A vs Option B for class-structure with trade-off table; recommends Option B.
- [x] **Stakeholder brief** at `fynla-coala-stakeholder-brief.md` — non-technical companion for leadership with 6 mermaid diagrams and three explicit decision asks.
- [x] **Self-contained HTML render artifacts** at `May/May26Updates/` — marked + DOMPurify + mermaid via CDN, Fynla palette, print-friendly. Build scripts kept alongside.
- [x] **Six implementation PRDs** at `May/May27Updates/PRD-coala-phase-{1..6}-*.md` — 1,525 lines total. Each follows the prd-writer canonical 9-section template.
- [x] **Pushed both commits** to `origin/feat/property-store-pr3`. Branch carries CoALA docs alongside the property-store refactor — additive only, no conflicts.

#### Decisions blocking next steps (logged at top of file)
- [ ] **Embeddings provider** (Phase 1 + 6).
- [ ] **Option A vs Option B** for class structure (Phase 5).
- [ ] **Stakeholder review** of the brief before any phase implementation kicks off.

#### Earlier today (prior sessions, not mine)
- All SP1 Pass 3 (Pensions) PRs 1–8 + §16 close-out landed (#378–#385). See section above for ticks.
- SP1 Pass 4 (Properties) plan written (#386) + PR 1 + PR 2 merged (#387, #388). PR 3 committed but not yet PR'd.

#### Done — session-end wrap (this session)
- [x] Vault-sync invoked (Phase 7) — completed via Haiku 4.5 subagent. 10 files synced, May26.md git history written (42 commits), May Index session entry added.
- [x] Handover written to `May/May27Updates/handover-2026-05-27-session-1.md` (repo + vault mirror).
- [x] `progress.md` appended (session 4 entry).
- [x] CSJTODO refreshed (this file).
- [ ] Tech-debt-session run **SKIPPED** — all session output was docs (.md, .html) and disposable build tooling (.py); the two build scripts have ~80% template duplication but are deferred deliberately as over-engineering for one-off generators.

---

### Session 1 (25 May 2026) — end-of-day after SP1 Pass 2 fully landed + mobile scaffold rebuilt + Pass 3 PR 0 audit

**Branch:** `dev` (at `72e6e5e`) · **Tree:** clean · **6 PRs this session** (#373/#374/#369/#371/#372/#370 all merged + #375/#376 opened) · **Pass 2 = 26/26 PRs DONE**

#### Done — Pass 2 wrap-up merges
- [x] **#373** — `Store.md` docs × 4 (TaxConfigStore, ActuarialLifeTableStore, CurrencyRateStore, SavingsMarketRateStore). Closes Pass-2 spec line 2298.
- [x] **#374** — drop dead `ai_chat_enabled` from ChrisUserSeeder. One-line fix. `db:seed` runs to completion again.
- [x] **#369** — full tech-debt remediation batch (33 of 178 items). Closes 3 Pest failures (FynMetering, MobileScaffold, plus 1 other).
- [x] **#371** — R1.0 B2 audit memo (TaxSettings round-trip).
- [x] **#372** — R1.5 B2 admin-edit gap + W1 hardcoded `getCalculations()` fix. **Had a `progress.md` rebase conflict; resolved chronologically, force-pushed with `--lease`** (CSJ-approved single-use destructive command).
- [x] **#370** — SP1 Pass 3 pensions implementation plan (4200 lines). Docs-only.

#### Done — Mobile scaffold work (PR #375 OPEN, 2 commits)
- [x] **api.js runtime detection** — `window.Capacitor.isNativePlatform()` picks base URL. Native = `VITE_API_BASE_URL` baked at build time. Web = relative (empty BASE). Fixes CSP-block bug that made the scaffold un-testable in any browser.
- [x] **Dashboard.vue rewrite** — JSON dump replaced with welcome card + horizon-blue net-worth headline + 6 module cards (Protection / Savings / Investment / Retirement / Estate / Goals) with live metrics + Fyn insight + scaffold tag.
- [x] **ModuleDetail.vue (new)** — generic `/m/app/module/:slug` view. Fetches `/api/v1/mobile/modules/{slug}`, renders curated hero metric + key/value detail rows. Back button.
- [x] **router.js + style.css** — added `module-detail` route, module-link interactive states, hero card, detail row styles.
- [x] **Verified via Playwright** — headless chromium iPhone 15 Pro emulation. Login → MFA (code fetched from DB) → dashboard → tap each of 6 cards → detail loads → back. 0 console errors, 0 page errors, 7 screenshots captured.
- [x] **Visible browser run** for CSJ — slow-motion headed run with 90s hold so the iPhone-sized window stays visible.
- [x] **Rule #16 compliance** — caught my own Unicode-chevron addition mid-session and reverted before committing. New code is strictly compliant; grandfather clause applies to pre-existing violations.

#### Done — SP1 Pass 3 PR 0 audit (PR #376 OPEN)
- [x] Re-survey mutation sites: **20** (not plan's 17 — instance-method calls missed by static grep, all in already-listed files).
- [x] Re-survey read consumers: **32** (slightly more than plan's 28 estimate, all in listed PR-5 files).
- [x] Dependency check: all 5 Pass-1/Pass-2 dependencies live. `TierGate` is an interface (plan's `class_exists` predicate is wrong).
- [x] Plan adjustment surfaced: **`StaticTierGate::LIMITS` is dead** — plan §117 (PR 7) needs rewriting to seed `pension_account` into `tier_configurations` (DB-backed `DbTierGate`).
- [x] Memo at `May/May25Updates/sp1-pass-3-pre-pass-audit-2026-05-25.md`. **Verdict: READY for PR 1.**

#### Done — session-end wrap
- [x] tech-debt-session run. 0 critical, 0 warnings, 4 suggestions (all scaffold-grade or deferred).
- [x] Vault-sync invoked (Phase 7).
- [x] Handover written to `May/May26Updates/handover-2026-05-26-session-1.md` (repo + vault mirror).
- [x] CSJTODO refreshed (this file).

#### Pass-wide status (SP1 = 19 stores; spec 2026-05-14)
- **5 of 19 stores done** (Savings + 4 ref-data).
- Pass 1 (Savings) — shipped (with 2 missing items: `.md` doc + `Restored` event).
- Pass 2 (Reference data R1-R4) — **DONE today**, 26 of 26 PRs landed.
- Pass 3 (Pensions) — plan written, PR 0 audit done, PR 1 ready to start.
- Passes 4-14 — not started, no plans written.

---

*Previous sessions: see handover files in monthly `*Updates/` folders.*
