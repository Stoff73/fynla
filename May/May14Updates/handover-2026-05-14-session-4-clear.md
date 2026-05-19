---
type: handover
mode: context-clear
date: 2026-05-14
session: 4
branch: dev
previous_session: 2026-05-14 session 3 (context-clear, PR #294 admin-merged — taxConfig hydrate rail laid)
tripwire: fired at ~659k tokens (1M context budget; CSJ's effective 200k Fynla budget exceeded). All 5 migration PRs already merged + CSJTODO pushed before tripwire — clean handoff.
---

# Context Clear Handover — 2026-05-14, Session 4

## Immediate state

**5 migration PRs (#295–#299) admin-merged into `dev` at `48798f9`.** REVIEW §4 High #28 fully shipped across every authenticated component — 33 Vue files migrated from `@/constants/taxConfig` scalar imports → `mapGetters('taxConfig', [...])`. CSJTODO updated and pushed (`48798f9`). Working tree clean. Tripwire fired AFTER everything was merged + documented — nothing in flight.

## The thread

- Session opened from `handover-2026-05-14-session-3-clear.md` recommending PR #295 (Estate component migration) as the next priority. Phase 5 of session-start auto-resumed.
- Worked through the 5-PR sequence the handover laid out (Estate → Investment → Savings → Retirement → Shared/Dashboard/Insights). Each PR followed the same pattern: branch off latest dev, migrate, run module-specific Pest suite, browser-verify in Playwright, commit, push, open PR, poll CI via `Monitor`, admin-merge with `--admin --delete-branch`, sync local dev, branch the next.
- Discovered en route that the actual remaining file count was bigger than CSJTODO anticipated — Protection (3 files), UserProfile (2 Composition-API files), NetWorth (Property + InvestmentProjections), Trusts, two Investment views, and `views/Dashboard.vue` were also importing the constants. Bundled all 14 of those into PR #299 rather than splitting further.
- Session-4-final commit pushed CSJTODO with the full 5-PR ledger and the remaining roadmap (extend backend payload + public-store dispatch → migrate CalculatorsPage → delete constants file).
- Tripwire fired at ~659k. CSJ invoked `/session-end context clear`.

## Files touched (all already merged this session)

No loose ends in the working tree. Everything below is in `origin/dev`.

- `63b53e4` PR #295 Estate (squashed into merge `ea6d3c8`) — 6 files
- `c32fe431` PR #296 Investment (squashed into merge `adfd982`) — 9 files
- `18e79e8` PR #297 Savings (squashed into merge `0ac3527`) — 2 files
- `d66695a` PR #298 Retirement (squashed into merge `3e25265`) — 2 files
- `0f4cc8f` PR #299 Shared/Dashboard/Insights/Protection/UserProfile/NetWorth/Trusts/Investment-views (squashed into merge `52e662f`) — 14 files
- `48798f9` CSJTODO ledger + roadmap update

Pre-existing untracked items at session close (all out of scope, carried from earlier sessions): `FCA-Supercharged-Sandbox-Application-Draft.md`, `FCA/`, `FCAsuperchargeApp.md`, `Fynla-Narrative-Memo-Template.docx`, `May/May1Updates/deployFynFix.md`, `campaigns/`, `fyn/`, `personas/`, `prompts/`, `tools/`.

## What the next Claude needs to know

- **The rail is done across every authenticated touchpoint.** 33 components migrated this session + the backend endpoint + Vuex store from session 3 = 34 touchpoints on the rail. `grep -rn "from '@/constants/taxConfig'" resources/js/` returns ONLY `views/Public/CalculatorsPage.vue` now.
- **CalculatorsPage cannot be migrated yet.** It's an unauthenticated public page so the Sanctum-gated `/api/tax/config` endpoint won't fire there. AND it uses 17 constants of which ~10 (SDLT/LBTT/LTT band tables, student loan repayment rate) are NOT in the current backend payload yet. Two unblockers needed first — see "Pick up from here" below.
- **Composition API works.** Two UserProfile files (`IncomeDefinitionsPanel.vue`, `IncomeOccupation.vue`) use `<script setup>` style with `useStore() + computed()` refs. Pattern is documented in PR #299 body for any future Composition API migrations.
- **Naming-collision pattern.** `views/Investment/AccountSummaryPanel.vue` had a local `isaAnnualAllowance()` computed that combined `lisaEligible` + the constant. Resolved with object-form mapGetters aliasing the store getter to `storeIsaAnnualAllowance` so the local computed name is preserved. Use this pattern any time a local computed already shares a name with the canonical store getter.
- **csjones is now 9 PRs behind** `dev` (#291 through #299). The bundle is ready to deploy whenever CSJ says so. Smoke check: `GET /api/tax/config` returns the seeded snapshot + every migrated module renders correctly.
- **Vault-sync deferred for the 3rd session running** (sessions 2 + 3 + 4 all tripwire-led). Next EOD session-end should catch up.
- **Stale worktree `cranky-lewin-6bc99c`** still present, still clean, still safe to remove with `rm -rf .claude/worktrees/cranky-lewin-6bc99c && git worktree prune` when convenient.
- **Architecture suite has 25 deprecated PHPUnit warnings** (ReflectionMethod/ReflectionProperty `setAccessible()` deprecated since PHP 8.5). Not introduced by this session — pre-existing pestphp/pest-plugin-arch issue. 95 tests still passing.

## Pick up from here

1. **Backend payload extension — `TaxConfigController::buildPayload()`.** Add the constants CalculatorsPage needs:
   - SDLT band tables: `SDLT_STANDARD_BANDS`, `SDLT_FTB_BANDS`, `SDLT_FTB_MAX_PRICE`, `SDLT_ADDITIONAL_SURCHARGE`, `SDLT_NON_UK_SURCHARGE`
   - LBTT band tables: `LBTT_BANDS`, `LBTT_ADDITIONAL_SURCHARGE`, `LBTT_NON_UK_SURCHARGE`
   - LTT band tables: `LTT_BANDS`, `LTT_ADDITIONAL_SURCHARGE`, `LTT_NON_UK_SURCHARGE`
   - `STUDENT_LOAN_REPAYMENT_RATE`
   Add corresponding Vuex getters to `resources/js/store/modules/taxConfig.js` (`sdltStandardBands`, `lbttBands`, `lttBands`, `studentLoanRepaymentRate`, etc.). Source values in `app/Services/TaxConfigService.php` — check whether SDLT/LBTT/LTT are already in the database via `TaxConfigurationSeeder` or whether they need to be added to the seeder first.

2. **Public-store dispatch on `PublicLayout`.** CalculatorsPage uses `<PublicLayout>` (not `<AppLayout>`), so the `App.vue` mount-time `taxConfig/fetchConfig` doesn't fire for unauthenticated visitors. Two options to evaluate:
   - **Option A (preferred):** Extend the existing `/api/public/tax-allowances` endpoint to return the same payload shape as `/api/tax/config`, and dispatch a public-version action from `PublicLayout` on mount. Adds an unauthenticated path with the same Vuex store target.
   - **Option B:** Make `/api/tax/config` itself unauthenticated (move route out of the Sanctum group). Simpler but exposes the same payload publicly — check with CSJ whether that's acceptable before doing it.

3. **CalculatorsPage migration.** Once (1) and (2) land, CalculatorsPage migration is mechanical — drop the import, add `mapGetters('taxConfig', [...])`, swap usages. Pattern is identical to PR #299's other 14 files. Estimate: 30 minutes once unblocked.

4. **Final cleanup PR — delete `resources/js/constants/taxConfig.js`.** Grep before deletion: `grep -rn "from '@/constants/taxConfig'" resources/js/` must return zero hits. Note that `import * as taxConfig from '@/constants/taxConfig'` (namespace import — already removed in PR #299 from `TaxYearStatBlock`) and `import { TAX_CONFIG }` (already removed) variants must also be checked.

5. **Deploy csjones when CSJ says so.** Bundle 9 PRs (#291 through #299). Smoke checklist:
   - `GET /api/tax/config` returns `{ tax_year, isa, pension, income_tax, ... }` with seeded values
   - Vuex devtools on `csjones.co/fynla` show `taxConfig.config` populated post-login
   - Estate dashboard's Gifting tile shows "Annual Exemption: £3,000"
   - Estate IHT page shows NRB band -£650,000 (joint) or -£325,000 (single)
   - `/net-worth/cash` → Add Account → Cash ISA → max value 20000 (from store)
   - Dashboard has no console errors

6. **Eventually — release PR `dev → main`.** Now 67 commits ahead of `main`, carrying 19 PRs (#281 through #299 + a couple of doc commits). Smoke notes for the release PR body: PR #294 introduced new authenticated `/api/tax/config` endpoint + Vuex boot dispatch; PRs #295–#299 are pure refactor (Vue components reading from store instead of imported constants); no DB migrations; no behaviour change to existing endpoints.

## Context hints

- Active branch type: **mainline** (currently on `dev` after the PR #299 merge and CSJTODO push)
- Behind origin/dev by: **0** — synced after every merge
- Ahead of `main`: 67 commits (audit batch + taxConfig rail + 5 migration PRs = 19 PRs total)
- Uncommitted: none of my work; only pre-existing untracked notes/folders
- Last commit on dev: `48798f9` docs(csjtodo): record session 4 — PRs #295–#299 admin-merged
- Test sweep: full Unit suite (1876 passed / 5837 assertions) + Architecture (95 passed / 416 assertions) all green this session
- CSJTODO updated: yes (this session, pushed)
- Vault sync: **deferred for the 3rd session running** — tripwire fired before vault-sync could run. Next EOD session-end should catch up.
