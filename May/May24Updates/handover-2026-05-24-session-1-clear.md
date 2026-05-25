---
type: handover
mode: context-clear
date: 2026-05-24
session: 1
branch: feature/sp1-pass-2-r1-5-b2-fix
trigger: context-handover tripwire (~741k tokens / >92% of 800k Fynla budget)
---

# Context Clear Handover — 2026-05-24, Session 1

## Immediate state

Just pushed PR #372 (SP1 Pass 2 R1.5 — B2-A + W1 fix). 4 PRs now open against `dev`, all yours to review/merge. Pass 2 has two items left after #372 lands: final pass-wide review + `superpowers:finishing-a-development-branch`. Then Pass 3 (Pensions) is unblocked per spec §15.2.

## The thread

- Session opened on `reviewFix`. CSJ asked "have all issues been corrected" on PR #369 — yes for the 4 reviewFix-induced ones (BillingTools × 3 + PaymentWebhookRace, all invoice_sequences seed-row regressions from Q9's schema:dump refresh; fixed via self-healing `insertOrIgnore` in `Invoice::generateNumber()` + new `InvoiceSequenceSeeder`). 3 pre-existing-on-dev failures remained (FynMetering, MobileScaffold, CassetteProvenance).
- CSJ said "fix all except #3 (cassette), don't surface again". I fixed FynMetering (dropped literal model anchor → `not->toBe(softDegradeModel())`) and MobileScaffold (relative → `url('/m/app')`), skipped CassetteProvenance with an inline note + saved memory `project_cassette_provenance_deferred_post_refactor.md` so I stop raising it. Pest went 4028/0/26 — full green. Committed `30d2ff9`, pushed to `reviewFix`, updated PR #369 with two comments.
- CSJ then asked to "continue with `2026-05-14-module-canonical-store-design.md`". I AskUserQuestion'd to pick scope; CSJ chose "Write Pass 3 (Pensions) plan". Invoked `superpowers:writing-plans` skill, read both existing pass plans (Pass 1 Savings 2941 lines, Pass 2 Reference Data 2369 lines), surveyed pension code (4 models: DC/DB/State/PensionInputHistory, ~17 mutation sites, ~28 read consumers, 1 observer). Decision: **one** `PensionStore` with type-dispatched write methods (matches spec §3.1 #6 + §4.1 "one store per entity" while reflecting the 4-model reality).
- Wrote 4200-line plan covering 8 PRs + PR 0 audit. Self-review caught one TBD on AssetCaptureEntityExtractor duplicate-check policy (resolved: duplicate prevention stays in caller, no store-level uniqueness validator) + one calculator test missing `date_of_birth` on user (would have failed at runtime with null `years_to_drawdown`). Both fixed inline.
- CSJ chose "subagent-driven, but land Pass 2 first". I:
  - Saved the plan as docs-only PR #370 (`docs/sp1-pass-3-pensions-plan` → `dev`).
  - Audited R1.0 server-side (no Playwright MCP available — confirmed via static analysis + the 12 existing TaxConfigAdminTest passes). Found **3 defect classes**: B2-A (Vue saveChanges drops 5 of 14 v-model'd sections silently), B2-B (6 sections have no Vue editor at all), W1 (getCalculations hardcodes ~125 lines of `'£12,570'` literals — Rule #3 violation + display drifts from saved config).
  - Wrote audit memo `May/May24Updates/sp1-pass-2-r1-0-b2-audit.md` → PR #371.
  - CSJ chose "Option B" — minimum diff. Wrote 2 failing tests (B2-A round-trip + W1 reads-active-config), then fixed Vue saveChanges (5 lines added) + rewrote `TaxSettingsController::getCalculations()` into 6 small builders that read `TaxConfigStore::activeConfig()['config_data']` and `number_format()` each value. Caught my own bug during TDD: `activeConfig()` returns `?array` not a model (used `?->config_data` initially → null). Fixed to `['config_data']` array access.
  - PR #372 opened. TaxConfigAdminTest 14/14 green (12 existing + 2 new). Full suite 4026/3/25 — same 3 pre-existing failures as `dev`, no new regressions from R1.5.

## Files touched this session (this branch only)

```
 app/Http/Controllers/Api/TaxSettingsController.php | 219 +++++++++++++-------
 resources/js/components/Admin/TaxSettings.vue      |   9 +
 tests/Feature/Admin/TaxConfigAdminTest.php         | 111 +++++++++++
```

Plus the plan file landed on `docs/sp1-pass-3-pensions-plan` (PR #370): `docs/superpowers/plans/2026-05-24-sub-project-1-pass-3-pensions-plan.md` (4200 lines).

Plus the audit memo landed on `feature/sp1-pass-2-r1-0-audit` (PR #371): `May/May24Updates/sp1-pass-2-r1-0-b2-audit.md` (159 lines).

Plus the cassette memory (saved to `~/.claude/projects/-Users-CSJ-Desktop-fynla/memory/`): `project_cassette_provenance_deferred_post_refactor.md` and MEMORY.md index entry. Memory files are out-of-repo; the file is local-only.

## WIP commit

- **No WIP needed** — current branch tree is clean. Last commit: `0511d27 fix(admin): close B2 admin-edit gap + W1 hardcoded calculations (R1.5)`.
- **Pushed:** Yes. `origin/feature/sp1-pass-2-r1-5-b2-fix` exists.

## Open decisions

1. **PR landing order** — CSJ controls. Recommended order:
   - **#369 (reviewFix)** first — closes 3 Pest failures that other branches rebase against.
   - **#371 (R1.0 audit)** next — docs-only, prerequisite for #372 context.
   - **#372 (R1.5 B2 fix)** after #371 — closes the last Pass-2 code change before final review.
   - **#370 (Pass 3 plan)** anytime — docs-only, no dependencies.
2. **csjones smoke for #372** — needs you in a browser (Tax Settings panel). I can't drive Playwright in this environment. The two smoke steps are in the PR body (edit 5 panels → reload → confirm persist; edit personal_allowance → confirm reference panel updates).
3. **Default if you don't redirect on resume:** session-start will auto-continue on whatever branch git puts it on. The most-recent-direction-of-travel is "finish Pass 2" → after #372 merges, do final pass-wide review then run `superpowers:finishing-a-development-branch`. **Don't start Pass 3 yet** per spec §15.2.

## Pick up from here (auto-continue contract)

1. **Check PR statuses** — `gh pr list --base dev` will show #369, #370, #371, #372. If CSJ has merged any, the next session adapts. If all 4 are still open, do nothing destructive — the merge decisions are yours.
2. **After #372 merges:** start the final SP1 Pass 2 review. Run `./vendor/bin/pest --testsuite=Architecture` to confirm all 4 reference-data boundary tests are locked (TaxConfigStore, ActuarialLifeTableStore, CurrencyRateStore, SavingsMarketRateStore). Audit the four `App\Services\Stores\*Store::md` docs (if any exist) per Pass 2 acceptance criteria; create any that are missing.
3. **Then:** invoke `superpowers:finishing-a-development-branch` to close out Pass 2 formally.
4. **Only after Pass 2 is on main:** start Pass 3 execution — dispatch a fresh subagent for PR 0 of the Pensions plan per CSJ's "subagent-driven" decision. Pass 3 plan is at `docs/superpowers/plans/2026-05-24-sub-project-1-pass-3-pensions-plan.md` (lands when #370 merges; until then read it from the branch via `git show origin/docs/sp1-pass-3-pensions-plan:...`).
5. **Do NOT** retry the Cassette Provenance test — see memory `project_cassette_provenance_deferred_post_refactor.md`. Deferred to post-Fyn-refactor.

## What the next Claude needs to know

- **Branch state at handover:** local has 4 feature branches I created this session (`reviewFix`, `docs/sp1-pass-3-pensions-plan`, `feature/sp1-pass-2-r1-0-audit`, `feature/sp1-pass-2-r1-5-b2-fix`). All pushed. All have open PRs. Working tree clean on all of them.
- **`activeConfig()` returns `?array` not a model.** Caught during TDD on R1.5. The `read()` method on `TaxConfigStore` does `$row->toArray()` + Carbon-canonical date overrides. `config_data` is a NESTED key inside that array. Don't use `?->config_data` — use `['config_data']`. See `app/Services/Stores/TaxConfigStore.php:219` and `:263`.
- **Pass 2 has 3 pre-existing test failures on origin/dev** (FynMetering, MobileScaffold, CassetteProvenance). First two are fixed on PR #369 (reviewFix). Cassette is intentionally skipped — see memory.
- **Pass 3 plan was self-reviewed** — placeholder scan clean, type-consistency check clean, one runtime bug in a calculator test caught + fixed (calculator returns `null` for `years_to_drawdown` when User.date_of_birth is null; test now sets DOB explicitly).
- **B2-B (6 sections with no Vue editor)** is deferred per CSJ's Option B choice. Tracked in PR #371's audit memo. Don't auto-include in any future PR without CSJ's go-ahead — scope is genuinely a decision (~600 lines of Vue if all 6 ship together).
- **Dev server was running at session start** (PHP :8000, Vite :5173). Should still be unless killed. `./dev.sh` if not.
- **Pass 1 dependencies** are all live (IngestSource, TierGate, SnapshotPolicy, etc.). Pass 2 dependencies (TaxConfigStore, ActuarialLifeTableStore, CurrencyRateStore, SavingsMarketRateStore) all live too — Pass 3 PR 0 will re-verify.

## Branch / deploy state

- Branch: `feature/sp1-pass-2-r1-5-b2-fix`
- Behind origin: 0
- Ahead of origin: 0 (just pushed)
- PR: https://github.com/Stoff73/fynla/pull/372 (open, awaiting review)
- Deploy status: **NOT deployed** to csjones or production. Nothing this session has reached even csjones — every PR is dev-targeted and awaiting your review.
- Other branches with open PRs (all also at `origin/HEAD = local HEAD`):
  - `reviewFix` → PR #369
  - `docs/sp1-pass-3-pensions-plan` → PR #370
  - `feature/sp1-pass-2-r1-0-audit` → PR #371
