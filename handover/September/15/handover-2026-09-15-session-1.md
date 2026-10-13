---
type: handover
mode: session-end
date: 2026-09-15
session: 1
repo: fynla
branch: dev (main checkout); work lives in a worktree on feat/savetax-property-caps-no-advice
---

# Session Handover — 2026-09-15, Session 1

## Where things stand

A long live-fix day driven by CSJ walking the SaveTax campaign on fynla.org as `c.jones@csjones.co`. Six fixes shipped to prod (main `ae88af383`, all PHP-only except one nullable column and the corpus file): joint 50/50 default, no record card mid-onboarding, spouse joint-record memory, two-accounts-in-one-clause gate fix, the workplace-pension bare-percentage backstop, every ownership phrasing, pension value question + personal-pension wording + missing-value action, and "my wife has a pension" no longer parking the name "Has". Two PRs are open and NOT on prod: **#854** (refusal re-run once + deterministic backstops for bank/ISA/investment steps + clause fixes; its consolidated-run failures were fixed in commit `ada6d85f7`) and the stacked **feat/savetax-property-caps-no-advice** (no PR yet): ISAs count toward the investments cap, a Save Tax property step, no advice during onboarding, dividends stored on the account (migration), plus uncommitted prompt work described below. The eleven mapping PRs (#829–#839) from yesterday are still parked; their branches were rebased into a stack this morning and force-pushed.

## Priorities for the next session

1. **Finish the refusal fix in the ONE prompt home, then re-verify property on csjones.** In the worktree (`/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/stack/../fix-joint`, branch `feat/savetax-property-caps-no-advice`) there are uncommitted edits: `FynSystemPrompt.php` rule 6 gained an "it NEVER applies to a message that answers a question you asked… record it, never refuse" clause; `CoreIdentity.php` and `OnboardingPromptBuilder.php` were reverted to the base branch versions (the identity flag and CAPTURE clause were dead code — unified mode uses `FynSystemPrompt::text()` as the system prompt; the builder edit that must be KEPT is `'create_property'` in the savetax tool list — check it is still there). Then: `CAPTURE_PROMPT_OVERLAY_GOLDEN=1 pest tests/Feature/AI/PromptOverlayGoldenMasterTest.php` to regenerate fixtures, run that test plus `CaptureAccuracyGateTest`, `CaptureStateToolCoverageTest`, commit, push, deploy to csjones (`git pull` on the branch + `config:cache`), and re-send on web as `gate-0915f@example.com` (user 396, at `campaign_property`): "Our home is worth 450000 with 200000 left on the mortgage, joint with my wife 50/50". Expect a save (model or the property backstop, which now works — gate 180 green with that sentence) → verify → "Yes" → DOB prompt with NO advice in between. Grok has refused that sentence 3× in a row; if it still refuses, the backstop must land it.
2. **Open the PR for `feat/savetax-property-caps-no-advice` → dev**, merge #854 first then this one (stack order). Release to main and deploy: PHP + `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` + `php artisan migrate --force` (one nullable column `investment_accounts.annual_dividend_income`, dump first) + `db:seed --class=RetirementActionDefinitionSeeder --force` is already on prod; run `fyn:procedural:validate`. Then **purge `c.jones@csjones.co`** (memory rule: always, unasked).
3. **Consolidated test pass** on the final tree, run ALONE (foreground Pest while a background run is going collides on the test DB and mass-fails): Onboarding, Unit/Onboarding, AI, Savings, Investment, Stores, Tiers, Architecture/StoreBoundary.
4. **BLOCKED ON CSJ — the eleven mapping PRs #829–#839** (stack tip `docs-bugs-fixed-log`, all rebased and force-pushed this morning; csjones gate results in `.playwright-mcp/gate-2026-09-15/` and in this session). CSJ stopped that work to chase prod regressions; ask whether to resume the merge.
5. **Parked by CSJ:** the iPhone registration bounce (stale `m_scaffold_token` blocks the desktop→/m handoff copy in `router/index.js:1646`); the web Retirement page has no actions block (declined).
6. `tech-debt-session` and `vault-sync` were NOT run (context ran out). Adjacent items noticed: the model writes `joint_owner_name` from its own placeholders; the eval yaml scenarios in `03-multi-entity` no longer assert `entity_created` frames.

## Context to load

- `handover/September/14/handover-2026-09-14-session-4.md` — the parked mapping-PR stack and its merge order.
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_joint_ownership_defaults_fifty_fifty.md` and `feedback_clear_cjones_prod_account_after_every_fix.md` — rules CSJ set today.
- `app/Services/AI/Fyn/FynSystemPrompt.php:59` — rule 6, the one home for the refusal text; uncommitted edit in the worktree.
- `app/Services/Onboarding/OnboardingChatDirector.php` — the refusal re-run (`refusal_retried`), `isCompletionDeclaration()`, the zero-output guard.
- `app/Services/Onboarding/OnboardingStateMachine.php` — property section, pot loop (`saysValueUnknown`, `afterPensionPots`, `pension_value_declined`), advice skip.
- `.claude/skills/fyn-architecture/SKILL.md` — Rule 20.

## Completed this session (all on prod unless stated)

- #840 joint 50/50 default in `CaptureAccuracyGate`; #841 record cards withheld mid-onboarding (`AiChatController::writeClientEvent`); #842 `SpouseJointRecords` memory (later routed through the stores); #844 two accounts in one "and" clause + zero placeholders stripped before the guards; #846 `extractOccupationalPensionAnswer` reads "5 and employer matches"; #848 every ownership phrasing (65-case dataset); #850 pension value loop for Save Tax, personal-pension wording, `pension_value_unknown` action (shows before the readiness gate), declined values never re-asked; #852 spouse-name parking fix. Releases #843/#845/#847/#849/#851/#853.
- NOT on prod: #854 (branch `fix/capture-refusal-retry-and-backstops`, commits through `ada6d85f7`); `feat/savetax-property-caps-no-advice` through `667eb2456` + uncommitted prompt edits.
- Memory written: joint 50/50 rule; purge c.jones after every fix; verify-sequence memory marked superseded (no advice during onboarding).

## Verification state

- Every shipped PR: its own suite plus scoped families green (numbers on each PR). Live on csjones before merge and re-checked on prod via tinker or CSJ's own runs.
- `feat/savetax-property-caps-no-advice` at `667eb2456`: gate 180, extractor 66, sections/state parity green; batch run (Onboarding, Savings, Investment, Stores, Tiers, StoreBoundary) was 4 failed / 1516 passed before the parity fixes — not re-run since. Live on csjones: ISA cap counting, investments step, property prompt reached with no advice; property capture itself NOT yet green live.
- Not run: `tech-debt-session`, `vault-sync`, full suite.

## Decisions and dead ends

- CSJ: joint = 50/50 default everywhere except property; bank accounts can only be 50/50. Free plan: 2 bank accounts + 2 investment accounts; ISAs (cash included) count toward investments. No advice during onboarding. Purchase cost dropped from the investments prompt; dividends stored on the account. Property must be captured in Save Tax. Purge the test account after every prod fix.
- Grok refuses plain data answers with the canned refusal sentence often (bank, pension, property steps). Fixes stacked: one automatic re-run, deterministic backstops per step, and the prompt clause (pending). Editing `CoreIdentity` does nothing in unified mode — dead end.
- Running foreground Pest during a background Pest run collides on the test DB (312 phantom failures) — never overlap.
- `Property` co-owner memory, `SpouseJointRecords`, must go through the stores (architecture tests).

## Things that will bite you

- Two worktrees under the scratchpad: `stack` (mapping PRs, on `docs-bugs-fixed-log`) and `fix-joint` (today's fix branches, real vendor copy, `.env` copied). Main checkout stays on dev; `FynSystemPrompt.php` and `OnboardingPromptBuilder.php` show as modified there with an empty diff — `git checkout` them.
- csjones is on `feat/savetax-property-caps-no-advice` (`05f1859f8`+) with the dividend migration applied; test users 392–396 (`gate-0915a..f@example.com`, `MapTest2!`); user 396 has a Premium entitlement granted for testing.
- Prod backups per release under `~/release-backups/2026-09-15{,b,c,d,e,f}/`.

## Branch and deploy state

- Main checkout: dev at `513f3b1fc` (behind origin/dev? pull first). Worktree branch `feat/savetax-property-caps-no-advice` at `667eb2456` pushed; uncommitted prompt edits as above.
- Prod: main `ae88af383`. csjones: feature branch.
