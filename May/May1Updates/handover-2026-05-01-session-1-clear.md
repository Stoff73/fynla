---
type: handover
mode: context-clear
date: 2026-05-01
session: 1
branch: feature/fyn-persona-split
previous_session: 124 (2026-04-30 → 2026-05-01 late evening — dev deploy + 4 critical fixes shipped during smoke)
---

# Context Clear Handover — 2026-05-01, Session 1 (post-124 wrap)

## Immediate state

Just finished a full-branch review of `feature/fyn-persona-split` via 6 parallel `eval-reviewer` agents and wrote the aggregated report to `May/May1Updates/branch-review-fyn-persona-split.md`. Both that report (commit `41eed00`) and the earlier skill migration (`97b21a3`) are committed and pushed to origin. Working tree clean.

## The thread

- User asked to make session-start, session-end, vault-sync skills available across all branches. Decision: move to user-level `~/.claude/skills/` (option B) rather than commit to dev. `session-end` upgraded to the elaborate onboardingFyn version (context-clear vs end-of-day mode, dated handover files, planning-with-files mirror). `vault-sync` modified to dispatch via Haiku 4.5 subagent at high effort.
- Project-level `.claude/skills/{session-start,session-end,vault-sync}/` deleted on this branch (`97b21a3`). Same deletion still needs to land on `dev` and `main` for full cross-branch effect — otherwise checking out those branches will restore the old project-level files which override user-level.
- User then asked for full-branch review (not just the skill files). Dispatched 6 parallel eval-reviewers covering 212 of 213 non-doc/non-test files. Aggregated FAIL verdict: 8 critical, 32 major, 45 minor, 37 nit.
- Wrote review to `May/May1Updates/branch-review-fyn-persona-split.md`, committed as `41eed00`.

## Files touched (uncommitted or recently committed)

Committed this session:
- `97b21a3` — deleted `.claude/skills/{session-start,session-end,vault-sync}/SKILL.md`
- `41eed00` — created `May/May1Updates/branch-review-fyn-persona-split.md`

Created OUTSIDE the repo (user-level skills, not tracked):
- `~/.claude/skills/session-start/SKILL.md` (verbatim from this branch's pre-deletion lean version)
- `~/.claude/skills/session-end/SKILL.md` (verbatim from `origin/onboardingFyn`)
- `~/.claude/skills/vault-sync/SKILL.md` (pre-deletion content + new Haiku 4.5 dispatch wrapper at top)

## What the next Claude needs to know

1. **The branch is FAIL — do not merge to dev yet.** 8 critical contract violations, 32 major issues. Full prioritised fix list in `May/May1Updates/branch-review-fyn-persona-split.md`. The headline P0s:
   - **5 RED tests** in `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php` from fix commit `23f68ec` — branch can't pass CI
   - **AdviceFyn leaks 6 capture_* tools** to LLM (Two-Fyn contract violation); `AdviceFynToolListTest` fixture omits them so the guard test gives false assurance
   - **`is_eval_user` dead column + `EvalPurgeCommand`** violate canonical 0.2 (triple-confirmed)
   - **`EvalDeltaBuilder result_path` always returns 'success'** because SSE doesn't carry tool result strings
   - **`EvalRecordCommand::resetPersonaIfMutating`** uses `empty($writes)` against `{created:[],updated:[],deleted:[]}` — fires reset on every scenario (canonical 0.1)
   - **`estimateIsaSubscriptionsThisYear` returns LIFETIME balance** — root cause of yesterday's £75k user defects only suppressed at one site
   - **`v-on="$listeners"` in `FynOnboardingChat.vue:55`** is Vue 3 incorrect (resolves to undefined)
   - **Spouse email lookup is case-sensitive** — duplicate-account risk
   - **`capture_complete` handoff event can leak to frontend** (INV-2.4.1)
   - **`AssistantContentSanitiser` is misnamed** — only strips xAI function_call tags, not a prompt-injection guard

2. **Skill migration is partially done.** User-level `~/.claude/skills/` versions are canonical and verified loading on this branch (the deletion of project-level on this branch lets user-level win). But `dev` and `main` still have the old project-level files. When checking out those branches, they will shadow user-level. To finish: cherry-pick `97b21a3` to `dev` and eventually `main`. User aware — covered in commit message and earlier wrap-up.

3. **Plugin fallback is asymmetric** — `plugins/fynla-dev-skills/skills/` has fallback copies of session-start and session-end, but NOT vault-sync. If `~/.claude/skills/vault-sync/` is ever lost, `session-end` Phase 7 will have no fallback. User chose to accept the asymmetry knowingly (their option B for the eval-reviewer's first finding).

4. **Vault-sync now runs on Haiku 4.5 at high effort.** Dispatched via the `Agent` tool with `subagent_type: general-purpose`, `model: haiku`. The Phases 1–9 are passed inline as the brief. Surface the Phase 9 summary verbatim — don't re-summarise. Verified working this session.

5. **Per `feedback_no_deploy_recommendations.md`** — branch is nowhere near deploy-ready. Don't suggest deploy as next step. The fix path is at least 6–10 commits before merge to dev.

6. **Per `feedback_smoke_must_verify_amounts.md` (issued today)** — after the £75k user fixes land, drive Playwright against that persona and verify £ amounts on `/tax-strategy` against the user's actual profile. HTTP 200 + DOM shape ≠ working product.

## Pick up from here

Two natural next steps in order:

1. **First commit:** Get tests GREEN. Open `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php` and update the 5 failing assertions to match the new behaviour from `23f68ec`:
   - Path A test (line ~49): expects 8 user allowance positions; new behaviour returns 6 (Marriage Allowance + Starting Rate hidden for ineligible single user)
   - Path B test (line ~101): expects 8; new behaviour returns 7
   - 3 Phase 4 carry-forward tests (lines ~956, ~982, ~1004): need `SavingsAccount` factory rows ≥ £10k AND must rename expected field `unused_carry_forward` → `unused_carry_forward_total` (or rename strategy field back to original)
   - Run `./vendor/bin/pest tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php` to verify

2. **Second commit (critical contract fixes):**
   - Add the 6 missing names to `app/Services/AI/AdviceFyn.php` `WRITE_TOOLS` const: `capture_salary_sacrifice`, `capture_spouse_work_status`, `capture_spouse_household_data`, `capture_spouse_non_working_assets`, `capture_pension_history`, `capture_charitable_giving`
   - Replace `tests/Feature/Fyn/AdviceFynToolListTest.php` `$writeTools` fixture with auto-enumeration from `getTools(false)` filtered by `create_|update_|delete_|capture_|set_` prefixes (excluding handoff tools)
   - New migration to drop `users.is_eval_user` column + index, AND remove `app/Console/Commands/EvalPurgeCommand.php` (or repoint to operate on `eval_recording_sessions`/`eval_provider_runs` directly)
   - Fix `app/Console/Commands/EvalRecordCommand.php` `resetPersonaIfMutating`: replace `empty($writes)` with `! ($writes['created'] || $writes['updated'] || $writes['deleted'])`
   - Fix `app/Services/AI/AdviceFyn.php` `wrapStream` to drop ALL `type === 'handoff'` events that aren't routed to DELEGATE_TO_CAPTURE handling
   - Add `strtolower(trim(...))` to spouse email lookup in `app/Services/Onboarding/SpouseLinkingService.php:95` and `app/Agents/CoordinatingAgent.php:1186`
   - Drop `v-on="$listeners"` from `resources/js/components/Fyn/FynOnboardingChat.vue:55`

After these two commits, re-run the eval-reviewers (or just the AI+Onboarding and Tax slices) to verify P0 closure before tackling Step 3 (real-money fixes including `estimateIsaSubscriptionsThisYear`).

## Vault sync (Haiku 4.5)

Ran cleanly. Codebase metrics current. May Index + Home.md + Git History/May2026/May01.md created. Branch review mirrored to vault. 1 suggested new memory (`feedback_skill_canonical_at_user_level.md`) NOT created — defer to user.

---

*Generated by session-end skill (context-clear mode). Pair file: `branch-review-fyn-persona-split.md` in same folder.*
