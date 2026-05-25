# Tech Debt Report — Session 2026-05-25 (EOD wrap)

**Mode:** end-of-day wrap
**Files analysed:** 11 (4 store docs, 1 seeder, 5 mobile scaffold files, 1 audit memo)
**Substantive code files audited:** 5 (mobile scaffold only — the rest are doc files or 1-line deletions)
**Issues found:** 4 (0 critical, 0 warnings, 4 suggestions)
**Severity breakdown:** 0 critical · 0 warnings · 4 suggestions

(Supersedes the 2026-05-22 report above.)

## Critical issues

None.

## Warnings

None.

## Suggestions

### S1 — Duplicate `formatCurrency` / `formatPercent` across two scaffold views

**Files:** `resources/mobile/views/Dashboard.vue` (lines 73–76, embedded `formatCurrency`), `resources/mobile/views/ModuleDetail.vue` (lines 14–22, both `formatCurrency` and `formatPercent`)
**Category:** Duplicate Code
**What's wrong:** Both views define their own `formatCurrency` helper using `Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', ... })`. `ModuleDetail.vue` additionally defines `formatPercent`. Two small utilities, two definitions.
**Why this is only a suggestion:** The mobile scaffold is intentionally isolated per `resources/mobile/README.md` and SP3 spec §4.5 ("Scaffold is disposable"). The desktop `currencyMixin` is unavailable here on purpose — the scaffold shares zero components with the desktop SPA. Two duplicated four-line functions across the scaffold's 3 placeholder views is below the bar for the isolation principle to override.
**Suggested fix (low priority):** Extract to `resources/mobile/utils/format.js` exporting `formatCurrency` + `formatPercent` once the scaffold has a 3rd consumer or the redesign starts. Don't bother before then.

### S2 — `formatFieldValue()` long if-chain in ModuleDetail.vue

**File:** `resources/mobile/views/ModuleDetail.vue` lines 138–167
**Category:** Complexity & Maintainability
**What's wrong:** Single ~30-line method with chained `.includes()` checks against field-name substrings to decide whether to format as currency, percent, count, or string. Works correctly but reads as if-chain bingo.
**Why this is only a suggestion:** The function is correct, the inputs are bounded (controlled by the curated `MODULE_CONFIG` field list above), and the scaffold is disposable. The redesign will replace this with per-module formatters anyway.
**Suggested fix (low priority):** When the redesign starts, replace with a lookup table or per-module formatter functions in `MODULE_CONFIG`. Don't refactor scaffold code in isolation.

### S3 — `2026_02_27_200003_add_ai_chat_enabled_to_users_table` migration is dead on disk

**Files:** `database/migrations/2026_02_27_200003_add_ai_chat_enabled_to_users_table.php`, `database/schema/mysql-schema.sql`
**Category:** Dead & Redundant Code (cross-file)
**What's wrong:** Today's PR #374 dropped the seeder line that wrote to `ai_chat_enabled`, because the column doesn't exist in the active schema (rebuilt 2026-05-24 09:42 without it). The migration is still on disk, still marked "Ran" in the `migrations` table, but its `Schema::table('users', fn ($t) => $t->boolean('ai_chat_enabled')...)` produced no column.
**Why this is only a suggestion:** Not blocking anything; the column is genuinely dead and no code references it. But the on-disk migration file + the schema-dump-without-column drift is a confusing state to leave around — the next developer who reads the migration list will wonder what happened.
**Suggested fix (when convenient):** Either (a) delete the migration file and add a follow-up `2026_05_XX_drop_dead_ai_chat_enabled_migration.php` to mark it consciously retired, or (b) regenerate the schema dump on a DB where the column exists if we actually want it back. Picking (a) or (b) is a CSJ call — depends on whether a backend AI-chat enable/disable flag is wanted in future.

### S4 — Hardcoded hex in `resources/mobile/style.css`

**File:** `resources/mobile/style.css` (lines 3–12, 16–35)
**Category:** Convention violation (technically — see below)
**What's wrong:** Raw hex values used throughout (`#F7F6F4`, `#1F2A44`, `#E83E6D`, `#C42B54`, `#6B7280`, `#E5E7EB`). CLAUDE.md Rule #12 (CSS Governance) says "No hardcoded hex in style blocks — use Tailwind `@apply` directives."
**Why this is only a suggestion:** Rule #12's intent is the **desktop** SPA where Tailwind is loaded. The mobile scaffold is **deliberately isolated** per SP3 spec §4.5 and `resources/mobile/README.md` — zero shared components, zero shared build, zero Tailwind. The scaffold's `style.css` uses raw hex because it has no Tailwind to `@apply` from. All hex values **do** match the canonical palette (raspberry / horizon / eggshell / neutrals), just not via tokens.
**Suggested fix (for the redesign, not now):** When the SP3 deferred redesign starts, define CSS custom properties at `:root` (`--c-raspberry: #E83E6D;` etc.) so future theme tweaking is centralised. Don't try to wire Tailwind into the isolated scaffold — that fights the SP3 isolation principle.

---

## Top 3 most impactful issues

None of the four findings are blocking. Ranked by future-pain risk:

1. **S3** (dead migration) — confusing to future maintainers; ~5 min fix when CSJ decides whether the backend flag should exist.
2. **S1** (duplicate format helpers) — low pain now, becomes worse as the scaffold/redesign grows. Cheap to fix when there's a 3rd consumer.
3. **S4** (hex in style.css) — pure ergonomic; only matters when theme tweaking is needed. Tied to the deferred redesign anyway.

## Notes on what was NOT audited

- The 4 new `app/Services/Stores/*Store.md` documentation files — pure docs, no code rules apply.
- `May/May25Updates/sp1-pass-3-pre-pass-audit-2026-05-25.md` — pure docs.
- The 1-line removal in `ChrisUserSeeder.php` — too small to surface debt.
- The 1-line addition in `resources/mobile/router.js` — adding a route.

## Verdict

**Clean bill of health for this session's code work.** Zero critical, zero warnings. The four suggestions are all either disposable-scaffold-grade or deferred-cleanup items. No fixes needed before merging PRs #375 or #376.

---
*Generated by tech-debt-session skill — 2026-05-25*
