# Track 2 — CoALA Integration of the Reconciled Recommendation Catalogue: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the dev→coala merge (step 0) and the five Track 2 workstreams (§6a–§6f of the spec) on the `coala` branch: house_view corpus, composed-plan fetch-skill, planner heuristics + A1/A2 overlays, lean-prompt conformance tests, and strategy-id episodic provenance.

**Architecture:** All work lands on `coala` via PRs targeting `coala` (never dev). The main working dir stays on `dev`; everything executes in a dedicated worktree. Step 0 (merge + §6f tool_schema mirror) is one PR and is a hard gate for everything else. The corpus PR (§6a) is CSJ's compliance review. Write-safety contract, `FynSystemPrompt::text()`, and all feature flags are untouched.

**Tech Stack:** Laravel 10 / PHP 8.2, Pest, the CoALA substrate on coala (SemanticCorpusLoader/SemanticRetriever, ProceduralCorpusLoader, PointerRegistry/FetchDispatcher, FynLoop/Planner, FetchProvenanceCollector/EpisodeBlobWriter), Track 1's `ComposedTaxPlanService`/`StrategyPlanComposer` (arrive via the merge).

**Spec:** `docs/superpowers/specs/2026-06-11-track2-coala-integration-design.md` (v4, approved 2026-06-11).

---

## Pre-flight: facts verified 2026-06-11 that correct or sharpen the spec

These were measured against `origin/coala` (`bd4e3ef`) and a real `git merge origin/dev` dry run. The executor must NOT re-litigate these; they are ground truth as of plan-writing.

1. **The merge has 12 conflicted files, not zero.** The spec's "zero textual conflicts measured" predates dev sessions 1–4 of 2026-06-11 (PRs #527–#531 + Track 1). Real conflict list (from `git merge origin/dev` on coala): `CLAUDE.md`, `CSJTODO.md`, `app/Services/AI/AiToolDefinitions.php`, `app/Services/AI/Fyn/FynContextAssembler.php`, `app/Services/AI/XaiToolDefinitions.php`, `app/Services/Onboarding/OnboardingChatDirector.php`, `app/Services/Onboarding/OnboardingStateMachine.php`, `findings.md`, `progress.md`, `tests/Architecture/Phase03ArchitectureTest.php`, `tests/Feature/Api/Public/InsightControllerTest.php`, `tests/Feature/Mobile/MobileScaffoldTest.php`. Task 1 resolves each with a named strategy.
2. **coala's tool catalogue is fully corpus-driven.** Every tool group in both `AiToolDefinitions` and `XaiToolDefinitions` assembles from `fyn-memory/procedural/tool_schema/` via `toolsFromCorpus(self::ORDER[...])`. Dev's static-array edits (Track 1's `get_recommendations` description, PR #531's `create_investment_account.annual_dividend_income`, and anything else dev changed since the merge-base) must be ported INTO the corpus `.md` pairs — the PHP conflict is resolved by keeping coala's side. Task 2 does the port with a catalogue-diff harness so nothing dev changed is silently lost.
3. **`house_view` is already fully wired.** `SemanticCorpusLoader::CATEGORIES` includes it; `source` and `valid_from` are optional for it; the retriever and `FynContextAssembler` consume it with zero code. §6a is pure content + tests.
4. **The no-£ guard is test-time, not loader-time.** `tests/Unit/Services/AI/SemanticCorpusContentTest.php` fails CI on `/£\s?\d/` in any fact. House_view prose must spell out money words ("one pound for every two pounds"), never `£` followed by a digit.
5. **FLAGGED DECISION (a) — overlay category.** The spec says create a new `fyn-memory/procedural/overlay/` kind "(only pointers/tool_schema/workflow exist)". That premise is wrong: `ProceduralCorpusLoader::KINDS` already includes `system_prompt_overlay` (and `fca_block`), with consumption wiring live in `FynContextAssembler::selectProcedures`. This plan reuses the existing `system_prompt_overlay` kind with `active: false` (validated + reindexed, never injected — honouring "no consumption wiring") instead of inventing a parallel kind. **CSJ approves or redirects at plan review.**
6. **FLAGGED DECISION (b) — planner heuristics placement.** `FynLoop::plannerSystemPrompt()` already layers `FynMemoryStore::proceduralContext()` — root-level `fyn-memory/procedural/*.md` files (template: `_TEMPLATE.md` with `id/title/applies_when/version/owner`) load into every planner call. This plan authors the routing heuristics as one such procedure file (zero code, canonical procedural memory) instead of editing the `PLANNER_SYSTEM_PROMPT` const. **CSJ approves or redirects at plan review.**
7. **There is no planner boolean feature flag.** The planner runs unconditionally inside `FynLoop::run()` (advice turns); the only knobs are `fyn.cycle_cap.*`. "Flag unchanged" = touch no config.
8. **Provenance today carries no strategy ids.** `FetchResult::provenance()` returns exactly `{pointer_id, handler, source_label, source_version, digest}` — §6e is code + tests, not just tests.
9. **Azlan eval mechanics:** live gate = `php artisan eval:record azlan_savetax_journey --providers=xai` (drives the HTTP loop against a running server; runs `eval:setup-azlan`; persists forensic record + JSONL fixture). Pre-check = the same command with `--dry-run` (validates setup without invoking providers).
10. **The 20 strategy ids** (from `TaxActionDefinitionSeeder::strategyMetadata()`, `source='strategy'`): `pa_taper_rescue`, `additional_rate_avoidance`, `salary_sacrifice_ni`, `isa_topup_vs_psa`, `bed_and_isa`, `dividend_allowance_harvest`, `pension_aa_carry_forward`, `gift_aid_higher_rate_relief`, `marriage_allowance_transfer`, `savings_to_spouse`, `isa_topup_spouse`, `gia_to_spouse`, `gia_rebalance`, `isa_coordination`, `non_earner_spouse_pension`, `joint_savings_psa_split`, `tapered_annual_allowance`, `lifetime_isa`, `junior_isa`, `junior_pension`.

**PR map (all target `coala`):**

| PR | Tasks | Content |
|----|-------|---------|
| PR-A `track2/step0-dev-merge` | 1–3 | dev→coala merge + §6f corpus mirror + golden masters + full post-merge gate incl. live Azlan |
| PR-B `track2/house-view-corpus` | 4–5 | 20 house_view files + retrieval tests — **this PR is the compliance review (locked decision §4.4)** |
| PR-C `track2/fetch-skill-provenance` | 6–7, 10 | composed-plan fetch-skill + shape parity + strategy-id provenance + lean-prompt conformance tests |
| PR-D `track2/planner-overlays` | 8–9 | planner heuristics procedure + A1/A2 overlay files |

PR-A merges first (hard gate). PR-B is independent of C/D and can run in parallel after PR-A. PR-C before PR-D (the planner heuristic references the fetch-skill's composed payload).

---

## File structure

**Created:**
- `fyn-memory/semantic/house_view/<20 files>.md` — one per strategy id, named `<strategy_id with _ → ->.md` (e.g. `pa-taper-rescue.md`)
- `fyn-memory/procedural/recommendation-routing.md` — planner heuristics (root-level procedure, FynMemoryStore store)
- `fyn-memory/procedural/system_prompt_overlay/general/a1-answer-first.md` + `a2-ack-hygiene.md` — inactive overlays
- `tests/Unit/Services/AI/Memory/HouseViewRetrievalTest.php` — §6a acceptance
- `tests/Unit/Services/AI/Pointers/Handlers/RecommendationHandlerParityTest.php` — §6b shape parity
- `tests/Feature/AI/RecommendationProvenanceTest.php` — §6e acceptance
- `tests/Feature/Fyn/PlannerHeuristicsTest.php` — §6c planner acceptance
- `tests/Feature/AI/LeanPromptConformanceTest.php` — §6d acceptance

**Modified:**
- `app/Services/AI/Pointers/Handlers/RecommendationHandler.php` — composed-plan payload (§6b)
- `app/Services/AI/Pointers/FetchResult.php` — optional `extra` provenance fields (§6e)
- `app/Agents/CoordinatingAgent.php` — `handleRecommendations` records provenance (§6e)
- `fyn-memory/procedural/tool_schema/analysis/get_recommendations.md` + `.xai.md` — Track 1 description + re-pitch guidance, version 2 (§6f)
- `fyn-memory/procedural/pointers/recommendations.md` — body describes the composed plan, version 2 (§6b)
- Any further tool_schema pairs the Task 2 catalogue diff surfaces (at minimum `data/create_investment_account.md` + `.xai.md` for `annual_dividend_income`)
- `tests/fixtures/ToolSchema/*.json` + `tests/fixtures/XaiToolSchema/*.json` — regenerated golden masters
- The 12 conflicted files (Task 1 resolutions)

---

### Task 0: Execution worktree + environment

The main dir (`/Users/CSJ/Desktop/fynla`) stays on `dev` (CSJ law — never switch branches there). All Track 2 work happens in a dedicated worktree.

**Files:** none (environment only)

- [ ] **Step 0.1: Create the worktree on a local `coala` branch**

```bash
cd /Users/CSJ/Desktop/fynla
git fetch origin coala
git worktree add /Users/CSJ/Desktop/fynla-coala coala 2>/dev/null \
  || git worktree add /Users/CSJ/Desktop/fynla-coala -b coala origin/coala
cd /Users/CSJ/Desktop/fynla-coala
git status
```

Expected: `On branch coala`, clean tree, tip `bd4e3ef` (or later if CSJ has pushed).

- [ ] **Step 0.2: Environment — .env + vendor**

```bash
cp /Users/CSJ/Desktop/fynla/.env /Users/CSJ/Desktop/fynla-coala/.env
cd /Users/CSJ/Desktop/fynla-coala
composer install --no-interaction 2>&1 | tail -3
php artisan config:clear
```

Expected: composer completes against coala's `composer.lock`. No npm needed — no Track 2 task builds frontend assets.

- [ ] **Step 0.3: Sanity — pest boots and the test DB is isolated**

```bash
grep -n "DB_DATABASE" phpunit.xml
./vendor/bin/pest tests/Unit/Services/AI/Memory/SemanticCorpusLoaderTest.php 2>&1 | tail -3
```

Expected: phpunit.xml pins the test database (`laravel_testing` per the eval driver's docblock) — confirm it does NOT point at `laravel`. Loader tests PASS. **Rule: never run pest simultaneously in both trees (shared test DB).** If phpunit.xml does not pin a separate DB, STOP and ask CSJ before running anything.

---

### Task 1: Step-0 merge — `origin/dev` into `coala` (12 conflicts, named resolutions)

**Files:** the 12 conflicted files listed in Pre-flight #1.

- [ ] **Step 1.1: Branch + merge**

```bash
cd /Users/CSJ/Desktop/fynla-coala
git checkout -b track2/step0-dev-merge
git fetch origin dev
git merge origin/dev
git diff --name-only --diff-filter=U
```

Expected: merge stops with exactly the 12 files from Pre-flight #1 (dev may have moved — if NEW conflicted files appear, resolve them by the same per-category logic below and note them in the PR body).

- [ ] **Step 1.2: Resolve the take-dev files wholesale**

`CSJTODO.md`, `findings.md`, `progress.md` are session/planning logs where dev is strictly newer. `InsightControllerTest.php` (dev's 1dba112 fallback-contract realignment is the current contract) and `MobileScaffoldTest.php` (dev's /m work is the current contract) — dev side wins:

```bash
git checkout --theirs CSJTODO.md findings.md progress.md \
  tests/Feature/Api/Public/InsightControllerTest.php \
  tests/Feature/Mobile/MobileScaffoldTest.php
git add CSJTODO.md findings.md progress.md \
  tests/Feature/Api/Public/InsightControllerTest.php \
  tests/Feature/Mobile/MobileScaffoldTest.php
```

- [ ] **Step 1.3: Resolve the take-coala files wholesale**

`AiToolDefinitions.php` and `XaiToolDefinitions.php`: coala's corpus-driven architecture is canonical; dev's static-array content is ported into the corpus in Task 2 (the catalogue diff guarantees nothing is lost):

```bash
git checkout --ours app/Services/AI/AiToolDefinitions.php app/Services/AI/XaiToolDefinitions.php
git add app/Services/AI/AiToolDefinitions.php app/Services/AI/XaiToolDefinitions.php
```

Then verify no resurrected static arrays remain (the dry run showed the auto-merge can splice dev's static `return [` blocks into corpus-driven methods):

```bash
grep -c "toolsFromCorpus" app/Services/AI/AiToolDefinitions.php   # expect ~19 (every group)
grep -n "'name' => 'get_recommendations'" app/Services/AI/AiToolDefinitions.php app/Services/AI/XaiToolDefinitions.php
```

Expected: the `'name' =>` grep returns NOTHING (no static entry — corpus only). If it returns hits, the `--ours` checkout fixed it; if hits persist, inspect and remove the spliced block manually.

- [ ] **Step 1.4: Resolve `CLAUDE.md` hunk-by-hunk, dev side**

Open `CLAUDE.md`, find each `<<<<<<<` block. All three measured conflict regions (project metrics table; build-scripts section vs DEPLOY.md pointer; first-time-setup vs deploy essentials) are regions where dev is strictly newer — keep the `origin/dev` side of each hunk, delete the `HEAD` side and markers. Do NOT take the whole file with `--theirs`: coala-only additions outside the conflict hunks (CoALA-specific notes) must survive the auto-merge untouched.

```bash
grep -c "<<<<<<<\|>>>>>>>" CLAUDE.md   # expect 0 after editing
git add CLAUDE.md
```

- [ ] **Step 1.5: Resolve `Phase03ArchitectureTest.php` by union**

Both sides extended class lists/exclusions. Keep BOTH sides' additions inside each conflict hunk (union), delete markers. Verify:

```bash
./vendor/bin/pest tests/Architecture/Phase03ArchitectureTest.php 2>&1 | tail -3
```

Run AFTER step 1.8 (needs a complete tree) — at this point just `git add` the marker-free file.

- [ ] **Step 1.6: Resolve `FynContextAssembler.php` by union (hardest file)**

Dev (Track 1) added: module-scoped context, restored knowledge layer + voicing rules. Coala added: semantic retriever + `SemanticSnapshotHolder` stamping + procedural overlay/fca_block selection (`selectProcedures`). BOTH must survive. Resolve each hunk keeping both sides' layers in build-order; where both sides edit the same method, integrate line-by-line. Anchors that must all exist in the resolved file: `semantic->retrieve(` (coala), `SemanticSnapshotHolder` (coala), `selectProcedures` (coala), plus dev's Track-1 layer methods (locate them first with `git show origin/dev:app/Services/AI/Fyn/FynContextAssembler.php | grep -n "function "` and confirm each appears in the resolution).

```bash
grep -c "<<<<<<<" app/Services/AI/Fyn/FynContextAssembler.php   # 0
git add app/Services/AI/Fyn/FynContextAssembler.php
```

- [ ] **Step 1.7: Resolve `OnboardingChatDirector.php` + `OnboardingStateMachine.php` by union**

Dev: savetax campaign machinery (PRs #529–#531 — funnel skip rules, capture acks, dividend capture). Coala: Phase 4d workflow-corpus + item-4 FynLoop capture routing. Union resolution keeping both. Anchors post-resolution: dev's `campaign_charitable_giving` ack entry and `STATE_CAMPAIGN_SPOUSE_WORK` skip rule; coala's FynLoop dispatch references.

```bash
grep -c "<<<<<<<" app/Services/Onboarding/OnboardingChatDirector.php app/Services/Onboarding/OnboardingStateMachine.php  # 0 + 0
git add app/Services/Onboarding/OnboardingChatDirector.php app/Services/Onboarding/OnboardingStateMachine.php
```

- [ ] **Step 1.8: Commit the merge**

```bash
git status            # nothing unmerged
git commit --no-edit  # keeps the default merge message
```

- [ ] **Step 1.9: Post-merge corpus + plan-file integrity**

```bash
php artisan fyn:procedural:validate
php artisan fyn:pointers:reindex
php artisan fyn:semantic:reindex
head -5 fynla-coala-implementation-plan.md | grep "v0.5"
```

Expected: all three commands SUCCESS; plan file resolves to **v0.5** (coala's copy — dev's stale v0.4 must not have won; if it did, `git checkout origin/coala -- fynla-coala-implementation-plan.md` and commit).

- [ ] **Step 1.10: Targeted smoke of the union-resolved files**

```bash
./vendor/bin/pest tests/Unit/Services/AI/Fyn/ tests/Feature/Fyn/ 2>&1 | tail -5
./vendor/bin/pest --filter="CampaignStateMachineBranch|CampaignSectionFlow|FunnelAnswersCapture" 2>&1 | tail -5
./vendor/bin/pest tests/Architecture/Phase03ArchitectureTest.php 2>&1 | tail -3
```

Expected: failures HERE are expected for the golden masters (Task 2 fixes them) — anything else failing is a resolution error: fix the resolution, do not proceed. Loop until only golden-master failures remain.

---

### Task 2: §6f — port dev's catalogue changes into the corpus, regenerate golden masters

**Files:**
- Modify: `fyn-memory/procedural/tool_schema/analysis/get_recommendations.md`, `get_recommendations.xai.md`
- Modify: `fyn-memory/procedural/tool_schema/data/create_investment_account.md`, `.xai.md` (+ any others the diff surfaces)
- Modify: `tests/fixtures/ToolSchema/*.json`, `tests/fixtures/XaiToolSchema/*.json`

- [ ] **Step 2.1: Build the catalogue diff — what did dev change that the corpus lacks?**

In the **main dir** (dev), dump dev's assembled catalogue; in the **worktree**, dump the merged corpus-driven catalogue; diff:

```bash
cd /Users/CSJ/Desktop/fynla
php artisan tinker --execute="file_put_contents('/tmp/dev-catalogue.json', json_encode(app(\App\Services\AI\AiToolDefinitions::class)->getTools(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));"
cd /Users/CSJ/Desktop/fynla-coala
php artisan tinker --execute="file_put_contents('/tmp/coala-catalogue.json', json_encode(app(\App\Services\AI\AiToolDefinitions::class)->getTools(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));"
diff /tmp/dev-catalogue.json /tmp/coala-catalogue.json | head -100
```

(If `getTools()` is non-static on one side, adapt the call — check the signature first.) Repeat for `XaiToolDefinitions`. Every dev-side difference in a tool's description/schema = a corpus `.md` pair to update. Expected at minimum: `get_recommendations` description, `create_investment_account.annual_dividend_income`. List every diff in the PR body. Coala-only differences (`fetch_*` pointer tools) are expected — ignore those.

- [ ] **Step 2.2: Update the `get_recommendations` corpus pair — Track 1 description + re-pitch guidance**

Replace the JSON `description` in BOTH files with Track 1's text plus the §6e re-pitch sentence, and bump frontmatter to `version: 2`, `effective_from: 2026-06-11`. The full description string (one line, single-quoted JSON):

> Get the user's personalised, ranked financial recommendations across all modules, plus a composed tax plan (composed_tax_plan) ordered by what to do first with conflicts resolved and a combined annual saving. Call this whenever the user asks what they should do, wants strategies, or asks about saving tax. Present the top 3 to 5 items in sequence order: state each title with its pound saving, quote the working for mechanical-tier items directly, hedge judgement-tier items ("you may want to consider"). If composed_tax_plan.locked is non-empty, tell the user how many further strategies unlock and what single data point each needs. Offer to go through the remaining items rather than dumping the full list. Before presenting, check this conversation for strategies you have already surfaced this session; when re-surfacing one, acknowledge the earlier discussion and build on it rather than pitching it as new.

`fyn-memory/procedural/tool_schema/analysis/get_recommendations.md` keeps its anthropic shape (no `provider`, no `strict`); the `.xai.md` keeps `provider: xai`, `"required": []`, `"strict": true`. Both get `version: 2`.

- [ ] **Step 2.3: Update every other corpus pair the diff surfaced**

For each (at minimum `create_investment_account` with the `annual_dividend_income` property from dev's schema): copy dev's exact JSON for the changed fields into both provider files, bump `version`, set `effective_from: 2026-06-11`. Re-run the Step 2.1 diff after editing — loop until the only remaining differences are the expected coala-only `fetch_*` entries and formatting-neutral ordering.

- [ ] **Step 2.4: Validate + regenerate golden masters**

```bash
php artisan fyn:procedural:validate
CAPTURE_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php
CAPTURE_XAI_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php
./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php 2>&1 | tail -3
```

Expected: validate SUCCESS; capture runs write fixtures; the plain runs PASS (byte-identical).

- [ ] **Step 2.5: Commit**

```bash
git add fyn-memory/procedural/tool_schema/ tests/fixtures/ToolSchema/ tests/fixtures/XaiToolSchema/
git commit -m "feat(track2): mirror Track 1 catalogue changes into the tool_schema corpus (§6f) + re-pitch guidance, regenerate golden masters"
```

---

### Task 3: Post-merge gate (spec §5) — suite, invariance, parity, interrupts, Azlan

**Files:** none (verification only). This gate must be fully GREEN before PR-A is opened. Rule #14 applies: loop until green.

- [ ] **Step 3.1: Full suite**

```bash
cd /Users/CSJ/Desktop/fynla-coala && ./vendor/bin/pest 2>&1 | tail -10
```

Expected: 0 failures. Any failure → systematic-debugging → fix → re-run. (Known skips: `CassetteModelProvenanceTest` is intentionally skipped — do not "fix" it; see the parked memory.)

- [ ] **Step 3.2: The named invariants, individually**

```bash
./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynSystemPromptTest.php          # byte-invariance snapshot
./vendor/bin/pest tests/Unit/Services/AI/Ground/GroundGateTest.php            # gate
./vendor/bin/pest tests/Unit/Services/AI/Actions/SurfaceAllowlistTest.php     # gate parity
./vendor/bin/pest tests/Feature/Fyn/FynStreamHarnessTest.php                  # FynLoop proofs incl. write-strip e2e
./vendor/bin/pest tests/Feature/Fyn/ConcurrentTurnQueueTest.php tests/Feature/Fyn/ConcurrentTurnQueueGateTest.php tests/Feature/Fyn/ResumptionTest.php   # interrupts
```

Expected: all PASS — proves no write surface widened and interrupts survived the merge.

- [ ] **Step 3.3: Azlan fixture-mode pre-check**

```bash
php artisan eval:record azlan_savetax_journey --providers=xai --dry-run
```

Expected: setup validates (scenario JSON found, `eval:setup-azlan` runs, no provider invoked).

- [ ] **Step 3.4: Azlan live grok-4.3 gate**

The eval drives HTTP against a running server. Verify the base URL first (`grep -rn "base_url\|localhost:8000" app/Services/Eval/ config/fyn_eval.php`). Then serve the WORKTREE's code — stop the main dir's `./dev.sh` first if it holds :8000:

```bash
# in a second shell, from /Users/CSJ/Desktop/fynla-coala:
php artisan serve --port=8000
# then:
php artisan eval:record azlan_savetax_journey --providers=xai
```

Expected: GREEN per the scenario's assertions (A1 salary-sacrifice answer in turn 4, no standalone capture acks, the turn-6 carry-forward clarifying question is marked KNOWN-RED on grok-4.3 2026-06-11 in the scenario — a turn-6-only failure matching that note is acceptable; anything else is not). Restart the main dir's `./dev.sh` afterwards. This is live LLM spend — run once per gate attempt, fix-and-rerun only on failure.

- [ ] **Step 3.5: Open PR-A**

```bash
git push -u origin track2/step0-dev-merge
gh pr create --base coala --title "Track 2 step 0: dev → coala merge + §6f tool_schema mirror" --body "<conflict list + resolutions, catalogue diff list, gate evidence incl. live Azlan result>"
```

CSJ reviews + admin-merges. **Tasks 4+ branch off coala AFTER this merges.**

---

### Task 4: §6a — author the 20 house_view files

**Files:** Create `fyn-memory/semantic/house_view/<id>.md` × 20 (ids in Pre-flight #10, underscores → hyphens in filenames).

Frontmatter template (per `SemanticCorpusLoader` rules — `source`/`valid_from` optional for house_view):

```yaml
---
fact_id: hv-<strategy-id-hyphenated>
category: house_view
title: <catalogue row title — the strategy's human name>
version: 1
valid_to: null
---
```

Body = four sections matching the spec: **narrative** (what/when/who), **methodology rationale** (why Fynla quantifies it this way), **sequencing reasoning** (`do_before`/`conflicts_with` in prose), **claim tier + voicing**. Rules: ZERO `£<digit>` anywhere (test-enforced); British English; no acronyms except ISA (Rule 9); no scores (Rule 12); no icons/emoji (Rule 15); plain prose, no headings other than the four section headings (sparse retrieval matches title + body tokens — write naturally with the strategy's vocabulary).

Authoring sources per file (read all three before writing each): the strategy's row in `database/seeders/TaxActionDefinitionSeeder.php` (title, copy, claim tier, `required_data`, `do_before`/`conflicts_with`); the evaluator class in `app/Services/Tax/Strategies/`; the relevant section of `app/Constants/FinancialPlanningKnowledge.php`.

- [ ] **Step 4.1: Branch**

```bash
cd /Users/CSJ/Desktop/fynla-coala && git checkout coala && git pull origin coala && git checkout -b track2/house-view-corpus
```

- [ ] **Step 4.2: Write the exemplar — `fyn-memory/semantic/house_view/pa-taper-rescue.md`**

This file is the quality bar; the other 19 follow its shape:

```markdown
---
fact_id: hv-pa-taper-rescue
category: house_view
title: Personal Allowance taper rescue — pension contributions in the taper band
version: 1
valid_to: null
---

## What this is, when it applies, who it is for

When someone's adjusted net income sits inside the Personal Allowance taper
band, every extra pound of income costs more than the headline higher rate,
because the allowance is withdrawn at a rate of one pound for every two pounds
of income above the taper threshold. A personal pension contribution reduces
adjusted net income, so contributing enough to bring income back to the
threshold restores the allowance in full. It applies to anyone whose relevant
earnings put them inside the taper band in the current tax year; it is most
valuable to employees who can also use salary sacrifice, and it is irrelevant
below the threshold or once the allowance is fully gone and other reliefs
dominate.

## Why Fynla quantifies it this way

Fynla works from the user's recorded income and the current year's thresholds
held in the live tax configuration, never from assumed figures. The saving is
computed as the tax relief on the contribution plus the value of the restored
allowance, which together produce the effective relief rate in the band — the
familiar "sixty per cent" effect. Fynla states the computed effective rate from
the user's own numbers rather than quoting the folk figure, because the exact
rate depends on how far into the band the user sits.

## Where it sits in sequence

This strategy comes before discretionary ISA moves for users in the band,
because the effective relief rate inside the taper band beats the long-run
benefit of wrapping the same money. It interacts with the pension annual
allowance and any carry-forward headroom: the contribution that rescues the
allowance must fit inside available annual allowance, so Fynla checks
carry-forward first and surfaces the tapered annual allowance rules for very
high earners, which can shrink the room this strategy needs.

## Claim tier and voicing

Mechanical tier: the arithmetic follows from recorded income and published
thresholds, so Fyn states the working directly and plainly. Voicing quotes the
user's own numbers, names the restored allowance, and avoids folk shorthand
unless the user introduces it first.
```

- [ ] **Step 4.3: Validate the exemplar**

```bash
php artisan fyn:semantic:reindex
./vendor/bin/pest tests/Unit/Services/AI/SemanticCorpusContentTest.php 2>&1 | tail -3
```

Expected: reindex SUCCESS (loader accepts frontmatter), no-£ test PASS.

- [ ] **Step 4.4: Author the remaining 19 in four batches of ~5, validating + committing per batch**

Batch order (groups with shared sources): (1) income-band set: `additional_rate_avoidance`, `salary_sacrifice_ni`, `tapered_annual_allowance`, `pension_aa_carry_forward`, `gift_aid_higher_rate_relief`; (2) wrapper set: `isa_topup_vs_psa`, `bed_and_isa`, `dividend_allowance_harvest`, `gia_rebalance`, `isa_coordination`; (3) spouse set: `marriage_allowance_transfer`, `savings_to_spouse`, `isa_topup_spouse`, `gia_to_spouse`, `non_earner_spouse_pension`, `joint_savings_psa_split`; (4) lifecycle set: `lifetime_isa`, `junior_isa`, `junior_pension`. After each batch:

```bash
php artisan fyn:semantic:reindex && ./vendor/bin/pest tests/Unit/Services/AI/SemanticCorpusContentTest.php 2>&1 | tail -2
git add fyn-memory/semantic/house_view/ && git commit -m "feat(track2): house_view corpus batch N — <ids>"
```

Reminder traps: joint ISAs do not exist (never imply one in `isa_topup_spouse`/`isa_coordination` prose); `marriage_allowance_transfer` must reflect the recipient-band gate Track 1 added (not available to higher/additional-rate recipients); the salary-sacrifice cap nuance is upcoming law (see `project_salary_sacrifice_2k_upcoming_law.md`) — describe the mechanism, not the cap.

---

### Task 5: §6a acceptance — retrieval tests against the real corpus

**Files:** Create `tests/Unit/Services/AI/Memory/HouseViewRetrievalTest.php`

- [ ] **Step 5.1: Write the failing-then-passing test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\SemanticRetriever;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    // Point the loader at the REAL repo corpus — this test pins the shipped
    // house_view files, not a synthetic fixture.
    config(['fyn.memory.semantic_path' => base_path('fyn-memory/semantic')]);
});

it('loads house_view rationale on a recommendation-intent turn', function (): void {
    $facts = app(SemanticRetriever::class)->retrieve(
        'What should I do to save tax — any strategies or recommendations for my pension and ISA?',
        Carbon::now(),
    );

    $categories = array_map(static fn ($f) => $f->category, $facts);

    expect($facts)->not->toBeEmpty()
        ->and($categories)->toContain('house_view');
});

it('does not load house_view rationale on an unrelated turn (lean-prompt law)', function (): void {
    $facts = app(SemanticRetriever::class)->retrieve(
        'How do I change the email address on my login?',
        Carbon::now(),
    );

    $categories = array_map(static fn ($f) => $f->category, $facts);

    expect($categories)->not->toContain('house_view');
});
```

- [ ] **Step 5.2: Run, tune prose if needed, commit**

```bash
./vendor/bin/pest tests/Unit/Services/AI/Memory/HouseViewRetrievalTest.php
```

Expected: PASS. If the first test fails, the sparse scorer (token frequency over title+body) isn't matching — enrich the weakest files' natural vocabulary ("recommend", "strategy", "save tax"), never keyword-stuff. If the second fails, an unrelated file is over-matching generic tokens — tighten its prose. Loop until both pass, then:

```bash
git add tests/Unit/Services/AI/Memory/HouseViewRetrievalTest.php
git commit -m "test(track2): house_view retrieval acceptance — loads on recommendation intent, absent otherwise"
git push -u origin track2/house-view-corpus
gh pr create --base coala --title "Track 2 §6a: house_view corpus (20 strategies)" --body "<file list + CSJ compliance-review note>"
```

PR-B is CSJ's compliance review of the corpus (locked decision §4.4).

---

### Task 6: §6b — Recommendation fetch-skill returns the composed plan

**Files:**
- Modify: `app/Services/AI/Pointers/Handlers/RecommendationHandler.php`
- Modify: `fyn-memory/procedural/pointers/recommendations.md`
- Test: extend `tests/Unit/Services/AI/Pointers/Handlers/RecommendationHandlerTest.php`; create `tests/Unit/Services/AI/Pointers/Handlers/RecommendationHandlerParityTest.php`

- [ ] **Step 6.1: Branch + verify-first**

```bash
git checkout coala && git pull origin coala && git checkout -b track2/fetch-skill-provenance
```

Re-read `app/Services/AI/Pointers/Handlers/RecommendationHandler.php` on the merged tree (the description in this plan is from the pre-merge tree: it calls `coordinator->orchestrateAnalysis()` and bullet-lists `ranked_recommendations`). Also confirm the composed-plan item id key by reading `app/Services/Coordination/StrategyPlanComposer.php` + the `StrategyRecommendation` DTO's `toArray()` — this plan assumes `strategy_type`; if the key differs, substitute it throughout Tasks 6–7.

- [ ] **Step 6.2: Write the failing shape test (extend the existing handler test)**

Append to `tests/Unit/Services/AI/Pointers/Handlers/RecommendationHandlerTest.php`:

```php
it('returns the composed tax plan as its payload', function (): void {
    $user = User::factory()->create();

    $res = app(RecommendationHandler::class)->fetch(new FetchContext($user, 'what should i do'));

    $decoded = json_decode($res->value, true);

    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveKey('composed_tax_plan')
        ->and($decoded['composed_tax_plan'])->toHaveKeys(['items', 'combined_annual_saving', 'locked']);
});
```

```bash
./vendor/bin/pest tests/Unit/Services/AI/Pointers/Handlers/RecommendationHandlerTest.php
```

Expected: the new case FAILS (current payload is a bullet list, not JSON).

- [ ] **Step 6.3: Implement the swap**

Replace `RecommendationHandler`'s dependency and `fetch()`:

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchResult;
use App\Services\Coordination\ComposedTaxPlanService;
use Illuminate\Support\Carbon;

final class RecommendationHandler implements FetchHandler
{
    public function __construct(private readonly ComposedTaxPlanService $plans) {}

    public function id(): string
    {
        return 'recommendations';
    }

    public function fetch(FetchContext $ctx): FetchResult
    {
        // §6b — the skill returns the same composed plan as get_recommendations,
        // so skill and tool cannot disagree (shape-parity pinned in tests).
        $plan = $this->plans->forUser($ctx->user);

        $surfaced = array_values(array_filter(array_map(
            static fn (array $item): string => (string) ($item['strategy_type'] ?? ''),
            $plan['items'],
        ), static fn (string $id): bool => $id !== ''));

        $locked = array_values(array_map(
            static fn (array $l): string => $l['strategy_type'],
            $plan['locked'],
        ));

        return FetchResult::make(
            (string) json_encode(['composed_tax_plan' => $plan], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'recommendation engine',
            Carbon::now()->toDateString(),
            [
                'strategy_ids' => implode(',', $surfaced),
                'locked_strategy_ids' => implode(',', $locked),
            ],
        );
    }
}
```

Note: the `extra` fourth argument to `FetchResult::make` is added in Task 7 Step 7.2. **Do Tasks 6 and 7 as one branch; run Step 7.2 (FetchResult extension) before this compiles.** Keep `mode: tool` and fail-degrade untouched (dispatcher already catches throwables). Match the existing `id()` method if the current class names it differently — verify in Step 6.1.

- [ ] **Step 6.4: Write the shape-parity test (skill vs tool, pinned)**

Create `tests/Unit/Services/AI/Pointers/Handlers/RecommendationHandlerParityTest.php`:

```php
<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\Handlers\RecommendationHandler;
use App\Services\Coordination\ComposedTaxPlanService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('the fetch-skill payload is byte-equal in substance to get_recommendations composed_tax_plan', function (): void {
    $user = User::factory()->create();

    // The skill's payload.
    $res = app(RecommendationHandler::class)->fetch(new FetchContext($user, 'what should i do'));
    $skillPlan = json_decode($res->value, true)['composed_tax_plan'];

    // The tool handler's payload (private — same reflection pattern as
    // ProceduralVersionStampingTest::persistEpisode).
    $agent = app(CoordinatingAgent::class);
    $m = new ReflectionMethod($agent, 'handleRecommendations');
    $m->setAccessible(true);
    $toolPlan = $m->invoke($agent, $user)['composed_tax_plan'];

    // And the source of truth both must equal.
    $direct = app(ComposedTaxPlanService::class)->forUser($user);

    expect($skillPlan)->toEqual($toolPlan)
        ->and($skillPlan)->toEqual($direct);
});
```

- [ ] **Step 6.5: Run both test files**

```bash
./vendor/bin/pest tests/Unit/Services/AI/Pointers/Handlers/ 2>&1 | tail -5
```

Expected: PASS (after Step 7.2 lands the FetchResult extension). The pre-existing `fetches live recommendations… as rendered text` case asserts `value` is a string — JSON is a string, it stays green; if it asserts bullet content, update it to the JSON shape (it is testing the old contract this task replaces).

- [ ] **Step 6.6: Update the pointer body + version**

`fyn-memory/procedural/pointers/recommendations.md` — bump `version: 2` and replace the body (the body IS the `fetch_recommendations` tool description the LLM reads):

```markdown
Use when the user asks what they should do, or for recommendations. Returns
the composed tax plan: strategies ordered by what to do first, with conflicts
resolved, claim tiers, a combined annual saving, and any locked strategies
with the single data point each needs. Computed live from the user's current
position -- exposed as a tool because it is a heavier, explicit ask, not a
blanket pre-fetch. Check the conversation for strategies already surfaced this
session and acknowledge prior discussion when re-surfacing one.
```

```bash
php artisan fyn:pointers:reindex
./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php 2>&1 | tail -3   # pointer baseline (count) unchanged → still green
git add app/Services/AI/Pointers/ fyn-memory/procedural/pointers/recommendations.md tests/Unit/Services/AI/Pointers/
git commit -m "feat(track2): Recommendation fetch-skill returns the composed plan, shape-parity pinned (§6b)"
```

---

### Task 7: §6e — strategy-id granularity in fetch provenance

**Files:**
- Modify: `app/Services/AI/Pointers/FetchResult.php`
- Modify: `app/Agents/CoordinatingAgent.php` (`handleRecommendations`)
- Test: extend `tests/Unit/Services/AI/Pointers/FetchDispatcherProvenanceTest.php`; create `tests/Feature/AI/RecommendationProvenanceTest.php`

- [ ] **Step 7.1: Write the failing provenance test**

Append to `tests/Unit/Services/AI/Pointers/FetchDispatcherProvenanceTest.php` (mirror its existing arrange pattern for pointer + handler fakes):

```php
it('merges a handler\'s extra fields (strategy ids) into the provenance entry', function (): void {
    $result = \App\Services\AI\Pointers\FetchResult::make(
        '{"composed_tax_plan":{}}',
        'recommendation engine',
        '2026-06-11',
        ['strategy_ids' => 'pa_taper_rescue,bed_and_isa', 'locked_strategy_ids' => 'junior_isa'],
    );

    $entry = $result->provenance('recommendations', 'recommendations');

    expect($entry['strategy_ids'])->toBe('pa_taper_rescue,bed_and_isa')
        ->and($entry['locked_strategy_ids'])->toBe('junior_isa')
        ->and($entry['pointer_id'])->toBe('recommendations')
        ->and($entry)->toHaveKeys(['handler', 'source_label', 'source_version', 'digest']);
});
```

Run: expected FAIL (`make()` takes 3 args).

- [ ] **Step 7.2: Extend `FetchResult` (additive, default `[]` — every existing caller unaffected)**

```php
final class FetchResult
{
    public function __construct(
        public readonly string $value,
        public readonly string $sourceLabel,
        public readonly string $sourceVersion,
        public readonly string $digest,
        /** @var array<string,string> handler-specific provenance fields (e.g. surfaced strategy ids) */
        public readonly array $extra = [],
    ) {}

    /** @param array<string,string> $extra */
    public static function make(string $value, string $sourceLabel, string $sourceVersion, array $extra = []): self
    {
        return new self($value, $sourceLabel, $sourceVersion, substr(hash('sha256', $value), 0, 16), $extra);
    }

    /** Provenance tuple for ai_messages.metadata. @return array<string,string> */
    public function provenance(string $pointerId, string $handler): array
    {
        return array_merge([
            'pointer_id' => $pointerId,
            'handler' => $handler,
            'source_label' => $this->sourceLabel,
            'source_version' => $this->sourceVersion,
            'digest' => $this->digest,
        ], $this->extra);
    }
}
```

The `digest` (hash of the JSON payload) is the spec's "composed-plan version/hash" — no extra field needed. Run the Step 7.1 test: PASS.

- [ ] **Step 7.3: Record provenance from the `get_recommendations` tool path too**

In `CoordinatingAgent::handleRecommendations`, after building the plan:

```php
$plan = app(ComposedTaxPlanService::class)->forUser($user);

$surfaced = array_values(array_filter(array_map(
    static fn (array $item): string => (string) ($item['strategy_type'] ?? ''),
    $plan['items'],
), static fn (string $id): bool => $id !== ''));

app(\App\Services\AI\Memory\Episodic\FetchProvenanceCollector::class)->record([
    'pointer_id' => 'recommendations',
    'handler' => 'recommendations',
    'source_label' => 'recommendation engine',
    'source_version' => now()->toDateString(),
    'digest' => substr(hash('sha256', (string) json_encode($plan)), 0, 16),
    'strategy_ids' => implode(',', $surfaced),
    'locked_strategy_ids' => implode(',', array_map(static fn (array $l): string => $l['strategy_type'], $plan['locked'])),
]);

return [
    'recommendations' => $analysis['ranked_recommendations'] ?? [],
    'total' => count($analysis['ranked_recommendations'] ?? []),
    'surplus' => $analysis['available_surplus'] ?? 0,
    'composed_tax_plan' => $plan,
];
```

(Adapt to the method's exact current body — the `composed_tax_plan` line already exists; this inserts the collector record and hoists the service call into `$plan`.)

- [ ] **Step 7.4: Feature test — the episode record carries the ids (spec §6e acceptance)**

Create `tests/Feature/AI/RecommendationProvenanceTest.php`, following `ProceduralVersionStampingTest`'s reflection pattern for `persistEpisode`:

```php
<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Memory\Episodic\FetchProvenanceCollector;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('a recommendation turn persists surfaced strategy ids into fetch_provenance', function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
    Storage::fake('local');

    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $assistant = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant']);

    $agent = app(CoordinatingAgent::class);

    // Execute the tool handler (records into the request-scoped collector).
    $h = new ReflectionMethod($agent, 'handleRecommendations');
    $h->setAccessible(true);
    $h->invoke($agent, $user);

    expect(app(FetchProvenanceCollector::class)->all())->not->toBeEmpty();

    // Flush the episode (same seam ProceduralVersionStampingTest uses).
    $m = new ReflectionMethod($agent, 'persistEpisode');
    $m->setAccessible(true);
    $m->invoke($agent, $assistant, $conv, $user, 'SYS', 'CTX', 'grok-4', null, null);

    $assistant->refresh();
    $entry = collect($assistant->fetch_provenance)->firstWhere('pointer_id', 'recommendations');

    expect($entry)->not->toBeNull()
        ->and($entry)->toHaveKeys(['strategy_ids', 'locked_strategy_ids', 'digest']);
});
```

(Verify `persistEpisode`'s exact signature on the merged tree before invoking — copy the argument list from `ProceduralVersionStampingTest`.)

- [ ] **Step 7.5: Run + commit**

```bash
./vendor/bin/pest tests/Unit/Services/AI/Pointers/ tests/Feature/AI/RecommendationProvenanceTest.php 2>&1 | tail -5
git add app/Services/AI/Pointers/FetchResult.php app/Agents/CoordinatingAgent.php tests/
git commit -m "feat(track2): strategy-id granularity in fetch provenance for skill + tool paths (§6e)"
```

---

### Task 8: §6c — planner routing heuristics (authored procedure, zero code)

**Files:** Create `fyn-memory/procedural/recommendation-routing.md`; create `tests/Feature/Fyn/PlannerHeuristicsTest.php`

- [ ] **Step 8.1: Branch (after PR-C content is committed — same worktree)**

```bash
git checkout coala && git pull origin coala && git checkout -b track2/planner-overlays
```

(If PR-C is not yet merged, branch off `track2/fetch-skill-provenance` instead and note the dependency in the PR body.)

- [ ] **Step 8.2: Author the procedure file**

`fyn-memory/procedural/recommendation-routing.md` (root level — `FynMemoryStore::procedures()` reads root `*.md` only). **CSJ requirement (2026-06-11): the frontmatter MUST be valid YAML conforming exactly to `fyn-memory/procedural/_TEMPLATE.md`** — keys `id`, `title`, `applies_when` (block scalar `>`), `version`, `owner`, parsed by `FynMemoryStore::parse()` via `Yaml::parse()`. Validate by asserting `procedures()` returns the entry with all fields populated (the Step 8.3 test does this implicitly via `proceduralContext()`):

```markdown
---
id: recommendation-routing
title: Recommendation turns — route to the composed plan
applies_when: >
  The user asks what they should do, asks for recommendations, strategies,
  ways to save tax, or next steps with their money.
version: 1
owner: CSJ
---

## Goal

A recommendation-intent turn surfaces the composed strategy plan computed from
the user's live position — never an answer from memory or generic advice.

## Steps

1. Choose `ground` (or `reason`) so the reasoner runs; the reasoner must call
   `get_recommendations` or the `fetch_recommendations` skill rather than
   answering from prior context.
2. If a surfaced strategy is locked behind missing data, the turn should ask
   the single unlock question the plan names — not propose the action blind.
3. Check the conversation for strategies already surfaced this session; when
   one comes up again, acknowledge the earlier discussion and build on it
   rather than pitching it as new.
```

- [ ] **Step 8.3: Write the acceptance tests**

Create `tests/Feature/Fyn/PlannerHeuristicsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Memory\FynMemoryStore;

it('the recommendation-routing procedure loads into the planner context', function (): void {
    config(['fyn.memory.procedural_path' => base_path('fyn-memory/procedural')]);

    $context = app(FynMemoryStore::class)->proceduralContext();

    expect($context)->toContain('Recommendation turns — route to the composed plan')
        ->and($context)->toContain('get_recommendations')
        ->and($context)->toContain('unlock question');
});
```

Plus one routing test proving a planner `ground` decision on the recommendation surface flows through the loop — copy the existing ground-action case in `tests/Feature/Fyn/PlannerTest.php` (it scripts `FynStreamHarness::fake()->toolTurn('plan', ['action_type' => 'ground', ...])`) verbatim, substituting the surface with `get_recommendations`, and assert the parsed `Action` has `ActionType::Ground`. Read that existing test first for the exact ground input keys (`surface`/`args` naming) — mirror it exactly.

- [ ] **Step 8.4: Run + commit**

```bash
./vendor/bin/pest tests/Feature/Fyn/PlannerHeuristicsTest.php tests/Feature/Fyn/PlannerTest.php 2>&1 | tail -3
git add fyn-memory/procedural/recommendation-routing.md tests/Feature/Fyn/PlannerHeuristicsTest.php
git commit -m "feat(track2): planner routing heuristics for recommendation turns as authored procedure (§6c)"
```

---

### Task 9: §6c — A1/A2 overlay files (inactive, validated, not consumed)

**Files:** Create `fyn-memory/procedural/system_prompt_overlay/general/a1-answer-first.md` + `a2-ack-hygiene.md`. Uses the EXISTING `system_prompt_overlay` kind per Pre-flight flag (5) — `active: false` keeps them out of every prompt (the consumption decision stays with finish-Phase-4).

- [ ] **Step 9.1: Verify the agreed A1/A2 wording**

Read the A1/A2 sections of `docs/superpowers/specs/2026-06-10-recommendation-insight-quality-design.md` (Track 1's spec, on the merged tree). The drafts below mirror their substance — correct them against the spec's exact rules before committing (Rule #16: the agreed wording wins).

- [ ] **Step 9.2: Author both files**

`fyn-memory/procedural/system_prompt_overlay/general/a1-answer-first.md`:

```markdown
---
procedure_id: 'general.overlay.a1_answer_first'
kind: system_prompt_overlay
module: general
version: 1
active: false
effective_from: 2026-06-11
---

Answer the user first. When a turn contains both a direct question and
capturable data, answer the question substantively before any acknowledgement
of recorded data. A capture acknowledgement never replaces an answer; if the
question needs a tool call, make the call and answer from its result in the
same turn.
```

`fyn-memory/procedural/system_prompt_overlay/general/a2-ack-hygiene.md`:

```markdown
---
procedure_id: 'general.overlay.a2_ack_hygiene'
kind: system_prompt_overlay
module: general
version: 1
active: false
effective_from: 2026-06-11
---

Acknowledgement hygiene. One short acknowledgement per set of captured items,
woven into the substantive reply — never a standalone acknowledgement bubble,
never stacked or repeated acknowledgements for the same items, and never an
acknowledgement of data the user did not just provide.
```

- [ ] **Step 9.3: Validate — recognised, parsed, and NOT consumed**

```bash
php artisan fyn:procedural:validate
```

Expected: SUCCESS, and the listing shows both files are absent from the `active:` lines (inactive). Then a pinning test — append to the existing procedural loader test file (`tests/Unit/Services/AI/Memory/` — locate `ProceduralCorpusLoader`'s test by name first):

```php
it('ships A1/A2 overlays inactive — validated but never injected', function (): void {
    config(['fyn.memory.procedural_corpus_path' => base_path('fyn-memory/procedural')]); // match the config key the loader test file already uses

    $corpus = app(\App\Services\AI\Memory\Procedural\ProceduralCorpusLoader::class)->loadStrict();

    expect($corpus->active('general.overlay.a1_answer_first'))->toBeNull()
        ->and($corpus->active('general.overlay.a2_ack_hygiene'))->toBeNull();
});
```

(Mirror the existing test file's config-key + API usage exactly — verify `loadStrict()`/`active()` signatures there first.)

- [ ] **Step 9.4: Run + commit + PR-D**

```bash
./vendor/bin/pest tests/Unit/Services/AI/Memory/ 2>&1 | tail -3
git add fyn-memory/procedural/system_prompt_overlay/ tests/
git commit -m "feat(track2): A1/A2 behavioural overlays authored inactive in the procedural corpus (§6c)"
git push -u origin track2/planner-overlays
gh pr create --base coala --title "Track 2 §6c: planner heuristics + A1/A2 overlays" --body "<note the Pre-flight flag (5) decision + flag (6) decision>"
```

---

### Task 10: §6d — lean-prompt conformance tests (no construction)

**Files:** Create `tests/Feature/AI/LeanPromptConformanceTest.php` (lands in PR-C, branch `track2/fetch-skill-provenance`)

- [ ] **Step 10.1: On-demand arrival + no-prefetch pinning**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use App\Services\AI\Pointers\PointerRegistry;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('the composed plan never rides in the assembled context — fetch-on-demand only', function (): void {
    $user = User::factory()->create();

    $out = app(FynContextAssembler::class)->build(FynTurnContext::make(
        user: $user,
        message: 'What is the weather like for planning a holiday?',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'general'],
    ));

    expect($out)->not->toContain('composed_tax_plan')
        ->and($out)->not->toContain('combined_annual_saving');
});

it('the recommendations pointer stays tool-mode — never a blanket prefetch (lean-prompt law)', function (): void {
    $pointers = app(PointerRegistry::class)->all();
    $rec = collect($pointers)->first(fn ($p) => $p->pointerId === 'recommendations');

    expect($rec)->not->toBeNull()
        ->and($rec->mode)->toBe('tool');
});
```

(Verify `FynTurnContext::make`'s named-arg list and the `Pointer` VO's `mode` property name on the merged tree first — copy from `FynContextAssemblerKnowledgeTest.php` and `PointerRegistryTest.php` respectively.)

- [ ] **Step 10.2: A4 synthesis persistence under FynLoop — verify-first, then gate**

Locate Track 1's A4 synthesis-persistence coverage on the merged tree: `grep -rln "synthesis" tests/ app/Services/Onboarding/ | head`. If a test exists, run it on the merged tree and record it green in the PR body — that plus the Task 3 live Azlan replay satisfies §6d's "verified to still hold under the shared loop post-merge". If NO test covers persistence (only the live journey did), add one feature test asserting the savetax synthesis turn writes the synthesis text to `ai_messages` (pattern: the campaign flow tests `CampaignSectionFlowTest` — extend with a persistence assertion on the synthesis state's message row).

- [ ] **Step 10.3: Run + commit (into PR-C)**

```bash
./vendor/bin/pest tests/Feature/AI/LeanPromptConformanceTest.php 2>&1 | tail -3
git add tests/Feature/AI/LeanPromptConformanceTest.php
git commit -m "test(track2): lean-prompt conformance — on-demand arrival + tool-mode pin + A4 persistence (§6d)"
git push -u origin track2/fetch-skill-provenance
gh pr create --base coala --title "Track 2 §6b/§6e/§6d: composed-plan fetch-skill, strategy-id provenance, lean-prompt conformance" --body "<test evidence>"
```

---

### Task 11: Final verification + success-criteria map

**Files:** none (verification + PR hygiene)

- [ ] **Step 11.1: Full suite on the final merged state of `coala`** (after all four PRs merge)

```bash
cd /Users/CSJ/Desktop/fynla-coala && git checkout coala && git pull origin coala
./vendor/bin/pest 2>&1 | tail -6
php artisan fyn:semantic:reindex && php artisan fyn:procedural:validate && php artisan fyn:pointers:reindex
```

Expected: 0 failures; all three corpus gates SUCCESS.

- [ ] **Step 11.2: Map the run to the spec's §8 success criteria — every line needs evidence in a PR body**

| § | Criterion | Evidence |
|---|---|---|
| 8.1 | merge; suite, golden masters, FynLoop + gate parity green; live-grok Azlan green; interrupts exercised; plan v0.5 | Task 3 outputs in PR-A |
| 8.2 | ~20 house_view files pass validator; intent turns load them; unrelated turns don't | Tasks 4–5 in PR-B |
| 8.3 | fetch-skill shape-parity green | Task 6 in PR-C |
| 8.4 | planner heuristic routing tests green | Task 8 in PR-D |
| 8.5 | overlay category validated; A1/A2 match Track 1 rules | Task 9 in PR-D |
| 8.6 | composed plan on demand only; A4 persistence under FynLoop | Task 10 in PR-C |
| 8.7 | episodes record surfaced strategy ids; re-pitch guidance in tool description + planner prompt | Task 7 (PR-C) + Task 2 (PR-A) + Task 8 (PR-D) |

- [ ] **Step 11.3: Out-of-scope tripwires (verify none were crossed)**

```bash
git diff origin/coala@{1} origin/coala -- app/Services/AI/Fyn/FynSystemPrompt.php   # empty diff expected vs pre-Track-2 (the merge itself must not touch it either — the snapshot test enforces this)
./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynSystemPromptTest.php tests/Unit/Services/AI/Ground/ tests/Unit/Services/AI/Actions/ 2>&1 | tail -3
```

No change to `FynSystemPrompt::text()`, no write-surface widening, no flag changes, no Phase 3/6 work, no coala→dev landing.

---

## Out of scope (from spec §7 — do not touch)

Phase 3 consolidation; Phase 6 learning actions; B3 lean-context rework; planner default flip; overlay consumption wiring (the A1/A2 files stay `active: false`); Option-A shell deletion; the coala→dev landing; `fca` corpus authoring; non-tax corpus growth; `FynSystemPrompt::text()`; the write-safety contract and gated write skills.
