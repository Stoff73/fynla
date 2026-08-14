# CLAUDE.md + `.claude/` Audit — 2026-08-14

Scope: the 6 tracked `CLAUDE.md` files and the whole `.claude/` directory on
`fix/widow-persona-cleanup`. Every claim below was verified against the tree,
not inferred. Worktree copies under `.worktrees/` are excluded except where
noted.

---

## Verdict

| File | Lines | Grade | One-line |
|---|---|---|---|
| `CLAUDE.md` (root) | 467 | **B (78)** | Rules are excellent and load-bearing; the factual scaffolding around them has rotted |
| `app/Services/CLAUDE.md` | 107 | **A- (88)** | Best file in the set. Three small staleness bugs |
| `app/Http/CLAUDE.md` | 160 | **B+ (84)** | Accurate but blind to `routes/api_v1.php` — the native/iOS surface |
| `database/CLAUDE.md` | 144 | **B (80)** | Patterns solid; seeder inventory drifted |
| `tests/CLAUDE.md` | 152 | **C (62)** | Counts off by 3–5×; omits Browser + Eval suites, which Rule 14 depends on |
| `resources/js/CLAUDE.md` | 185 | **D (45)** | Web half is good. **Mobile section documents an architecture that no longer exists** |

Average **73/100**. The rules content is genuinely high-value and I would not
touch it. Nearly all the damage is in inventory numbers and in one section that
was never updated after a rewrite.

---

## 1. The finding that actually matters

**`resources/js/CLAUDE.md` lines 152–186 — "Mobile App (Capacitor)" — describes
files that do not exist.** Root `CLAUDE.md:357` points at this section as the
authority for mobile conventions ("Full conventions in `resources/js/CLAUDE.md`
(Mobile section)").

| Documented | Reality |
|---|---|
| "Mobile views live in `mobile/`" | `resources/js/mobile/` **does not exist**. The mobile SPA is `resources/mobile/` — a separate isolated Vite build (25 `.vue`) |
| `MobileLayout.vue` | **Not found anywhere** |
| Store modules `mobileDashboard`, `mobileNotifications` | **Not in `resources/js/store/modules/`** |
| `normaliseModule()` | **Only occurrence in the repo is this CLAUDE.md file itself** |
| `ModuleSummaryCard` / `ModuleSummary` | **No such files** |
| `VoiceInputButton.vue` (12 lines of gotchas) | **Not found** |
| `BiometricPrompt.vue` | **Not found** |
| `MobileLoginScreen.vue` | **Not found** |
| `appLifecycle.js` | **Not found** |
| `platform.js`, `auth/mobileLogout` | Exist ✓ |
| `MobileDashboardAggregator`, `MobileLevelService` | Exist ✓ (`app/Services/Mobile/`) |

**9 of 12 concrete file references are dead.** Root `CLAUDE.md:363-364` repeats
the dead data-flow chain verbatim ("`MobileDashboardAggregator` → store
`normaliseModule()` → `ModuleSummaryCard`").

Why this is worse than a stale number: Rule 19 makes `/m` parity mandatory on
almost every task, so an agent reads this section constantly, and it will spend
real time hunting for `normaliseModule()` before concluding the docs are wrong.
It also crowds out the thing that *is* true and non-obvious — `resources/mobile/`
is an isolated bundle with its **own** `api.js`, `router.js`, `store.js` and
`tokens.js`, and its API base is chosen at runtime by
`window.Capacitor.isNativePlatform()` (`resources/mobile/api.js:19-21`).

**Recommendation:** delete lines 152–186 and replace with ~10 lines describing
`resources/mobile/` as it is. Keep only the two rules that are still load-bearing
and still true: the `vite.config.js` iOS rules (183–185) and `auth/mobileLogout`
(168).

---

## 2. Inventory deltas — every count is wrong

### Root `CLAUDE.md`

| Claim | Actual | Δ |
|---|---|---|
| Vue Components 675 | 679 | +4 |
| PHP Services 446 | 462 | +16 |
| Controllers 128 | 134 | +6 |
| Models 134 | 138 | +4 |
| Vuex Stores 35 | 35 | ✓ |
| Agents 9 | 9 | ✓ |
| "214 services across 32 module directories" | 462 across 44 | **+248 / +12** |
| "89 controllers" (Api) | 129 | +40 |
| "488 components across 29 module directories" | 517 across 34 | +29 / +5 |
| "138 views" | 158 | +20 |
| "83 Form Request classes" | 98 | +15 |
| "12 observers" | 19 | +7 |
| "45 services" (JS) | 58 | +13 |
| "33 namespaced modules" | 35 | +2 (and contradicts its own table, which says 35) |

### `tests/CLAUDE.md` — worst offender

| Claim | Actual | Δ |
|---|---|---|
| `Unit/` 123 files | **379** | 3.1× |
| `Feature/` 77 files | **377** | 4.9× |
| `Architecture/` 8 files | **39** | 4.9× |
| `Integration/` 3 files | 4 | +1 |
| "1,600+ `it()` cases" | **6,601 `it()` + 110 `test()`** | 4.1× |
| "64 factories" | **55** | −9 (over-claim) |
| `Browser/` | **absent from the doc** — 27 files, 24 BS-NN scenarios | — |
| `Eval` suite | **absent** — declared in `phpunit.xml` | — |

The Browser omission is the material one: root `CLAUDE.md` **Rule 14** makes
`tests/Browser/scenarios/BS-NN-*.php` the definition of done, and the testing
doc doesn't acknowledge the directory exists.

### `database/CLAUDE.md`

| Claim | Actual |
|---|---|
| "23 seeder classes" | **29** (6 undocumented) |
| "64 factories" | **55** |
| Phase 1/2 ordered list (22 named) | 6 seeders unaccounted for |

---

## 3. Factual errors (not just counts)

| # | Location | Problem |
|---|---|---|
| 1 | Root `CLAUDE.md` — Custom artisan commands | **`php artisan trials:expire` does not exist.** Real signature is `subscriptions:expire` (`app/Console/Commands/ExpireSubscriptions.php`). A documented command that fails on invocation. |
| 2 | Root `CLAUDE.md` — Branch workflow | "`.github/CODEOWNERS` forces `@Stoff73` as a required reviewer" — **the file is deleted on this branch** (commit `ab339eb`, *"remove CODEOWNERS — evidence gate replaces approval"*). Still present on `dev`. CLAUDE.md and the branch now disagree; whichever way this lands, one of them needs editing. |
| 3 | Root `CLAUDE.md:280` | Design guide cited as **v1.3.0**; `resources/js/CLAUDE.md:89` cites **v1.2.0**; `designSystem.js:7` cites **v1.2.0**; the actual file is **v1.3.1**. Four sources, three answers. |
| 4 | `app/Services/CLAUDE.md:62` | `getTaxYear(); // '2025/26'` — root CLAUDE.md says the active year is **2026/27**. |
| 5 | `app/Services/CLAUDE.md:9` | "All **9** module agents extend `BaseAgent`" — there are 9 *files*, one of which **is** `BaseAgent`. 8 module agents. Root CLAUDE.md names only **7** and omits **`TaxOptimisationAgent`** entirely — an undocumented agent in a codebase whose whole architecture is agent-first. |
| 6 | `app/Services/CLAUDE.md:96` | Lists 9 observers; there are **19**. |
| 7 | `app/Http/CLAUDE.md:115` | "**All** routes in `routes/api.php`" — there are also `routes/api_v1.php` (the entire `/api/v1/native/*` iOS surface, incl. `native.client` / `native.version` / `native.session` middleware) and `routes/e2e.php`. This is the doc an agent reads before touching routing, and it hides the iOS API. |
| 8 | Root `CLAUDE.md` — artisan table | 10 commands documented of **51** present. Undocumented and non-obvious: `fyn:semantic:reindex`, `fyn:semantic:promote`, `fyn:pointers:reindex`, `fyn:procedural:validate`, `gamification:backfill`, `lifecycle` engine, Apple/StoreKit reconcilers. |

---

## 4. `.claude/` directory

### Good — leave alone
- **11 hooks, all 11 wired** in `settings.json`. No orphans, no dangling references. Genuinely well maintained.
- **5 skills are `disable-model-invocation: true`** (`deploy-checklist`, `deploy-notes`, `plan-and-build`, `release`, `scaffold-feature`) — they exist as *your* slash commands and I cannot self-invoke them. Given `feedback_no_deploy_recommendations`, this is exactly right. It is, however, **undocumented** — nothing in CLAUDE.md says these five exist.

### Defects

| # | Issue | Impact |
|---|---|---|
| 1 | **`.claude/settings.json` is tracked; `hooks/oversight-guard.sh` and `hooks/workforce-guard.sh` are untracked.** The uncommitted settings change wires two `PreToolUse` hooks to scripts git doesn't know about. | Commit `settings.json` without the scripts and every `Bash` + `Write\|Edit` hook fails for any other checkout. **Commit them together or not at all.** |
| 2 | Hook commands use **absolute paths** (`/Users/CSJ/Desktop/fynla/.claude/hooks/…`) in a tracked file. | Breaks on any other machine or clone path. `$CLAUDE_PROJECT_DIR` would fix it. |
| 3 | `settings.local.json` `enabledPlugins.github = false` **contradicts** `settings.json` `= true`. Local wins → the GitHub plugin is off. | Silent; one of the two is a leftover. |
| 4 | `settings.local.json` `autoMode.allow` carries a **standing broad SSH/SCP/artisan authorisation for csjones**, scoped in its own text to *"the `fix/persona-split-review-fixes` deploy session"* — a session long finished. | A blanket staging-write grant outliving its stated scope. Worth revoking or re-scoping. |
| 5 | ~10 junk permission entries in `settings.local.json` from mis-parsed compound commands: `Bash(do:*)`, `Bash(done)`, `Bash(while read date time)`, `Bash(__NEW_LINE_8d0ecab2a0cd9421__ echo:*)`, `Bash(echo === VUE COMPONENTS ===:*)`, `Bash(/tmp/batch2_extras.sh:*)`, `WebFetch(domain:)`. | Noise. `Bash(do:*)` matches nothing meaningful. |
| 6 | **3 of 4 `.claude/references/` files are referenced by nothing**: `accessibility-checklist.md`, `performance-checklist.md`, `testing-patterns.md`. Only `security-checklist.md` is used (by the `security-and-hardening` skill). | Dead weight that reads as live policy. |
| 7 | **19 agents with overlapping remits and no routing rule.** `product-manager` vs `product-lead`; `design-lead` vs `premium-ui-designer` vs `ux-writing-expert` vs `frontend-developer`; `quality-lead` vs the `pr-review-toolkit` plugin agents. CLAUDE.md's "Subagents" paragraph mentions only `Explore`/`general-purpose`, and "Code review output" names three others. | Nothing tells an agent which to pick. The 8 new workforce agents (`archivist`, `build-lead`, `cartographer`, `chief-of-staff`, `compliance-lead`, `intelligence-lead`, `quality-lead`, `quartermaster`) are **untracked** and **unmentioned in CLAUDE.md**. |
| 8 | `.DS_Store` in `.claude/` and `.claude/skills/`. | Untracked, harmless, still noise. |
| 9 | **13 worktrees**, 7 carrying full stale copies of all 6 CLAUDE.md files — one (`fynla-org-repository-migration`) is a whole generation behind (452 lines vs 467). Two live outside the repo (`/private/tmp/fynla-pr674-lint-20260809`, `Desktop/01 Fynla/…`). | Grep for a CLAUDE.md rule and you get 7 copies, some contradicting the live one. |

---

## 5. Conciseness

Root `CLAUDE.md` is **467 lines / 40 KB, auto-loaded into every session**. It
earns most of that — Rules 1–20 are the highest-value content in the repo and
should not be trimmed. Three sections do not earn their place:

1. **Project Overview metrics table** (7 rows) — every number is wrong, none of
   them changes a decision. *Delete, don't update.* A count that must be
   maintained by hand will be wrong again in a month.
2. **UK Tax Context** (10 lines) — labelled "orientation only", listing values
   Rule 2 forbids using. Any agent that reads a number here and skips
   `TaxConfigService` has been actively misled. Cut to the two lines that matter
   (tax year boundary + active year).
3. **Vault Reference table** (15 rows) — 13 of 15 rows point at the same two
   files (`v083/09-MODULES.md`, a `Current State` doc). Collapse to two lines.

Estimated saving ~45 lines with **zero** loss of decision-relevant content.

---

## 6. Recommendations, ranked

| # | Action | Effort | Why |
|---|---|---|---|
| 1 | **Rewrite `resources/js/CLAUDE.md` Mobile section** against `resources/mobile/`; fix the mirrored dead chain in root `CLAUDE.md:363`. | 30 min | Actively wrong on the surface Rule 19 touches most |
| 2 | **Add `Browser/` + `Eval` to `tests/CLAUDE.md`**, with the BS-NN docblock-is-the-contract convention. | 15 min | Rule 14's acceptance criteria live in an undocumented directory |
| 3 | **Fix `trials:expire` → `subscriptions:expire`.** | 1 min | Documented command that errors |
| 4 | **Commit the two workforce hooks with `settings.json`, or revert the settings change.** | 5 min | Tracked config pointing at untracked scripts |
| 5 | **Delete the metrics table**; delete the 3 unreferenced `.claude/references/` files. | 5 min | Rot with no upside |
| 6 | **Reconcile CODEOWNERS**: either restore it or update the Branch-workflow paragraph. | 5 min | Docs and branch disagree |
| 7 | **Add `routes/api_v1.php` to `app/Http/CLAUDE.md`** — native routes + `native.*` middleware. | 10 min | Hides the entire iOS API |
| 8 | **One design-guide version reference**, sourced from the guide. Fix `getTaxYear()` comment to 2026/27. Correct the agent count and document `TaxOptimisationAgent`. | 10 min | Cheap correctness |
| 9 | **Add a 5-line agent-routing block** to CLAUDE.md, and re-scope/revoke the stale `autoMode` csjones grant. | 20 min | 19 agents, no selection rule; a live blanket staging grant |
| 10 | **Prune worktrees** — `git worktree prune` plus deliberate removal of the merged ones. | 10 min | 7 stale CLAUDE.md copies polluting every grep |

**Deliberately not recommended:** updating the counts. They will be wrong again
by September. Delete them, or generate them in CI — nothing in between.

---

## Note on method

Every number above came from a direct count on this tree
(`find`/`grep`/`git ls-files`), not from reading prose. The "not found" rows in
§1 were each confirmed by a repo-wide `find` before being listed.
