# CLAUDE.md

**Fynla** — UK financial planning app (Laravel 10 + Vue 3 + MySQL 8). Seven modules: Protection, Savings, Investment, Retirement, Estate, Goals & Life Events, Coordination.

**Three clients, one backend:** desktop web SPA (`resources/js/`), `/m` mobile web (`resources/mobile/`), native SwiftUI iOS (`ios-native/`). Flow: `Vue → API service → Controller → Agent → Services → Models → DB`.

**Production**: https://fynla.org | **Dev/staging**: https://csjones.co/fynla

## Load these skills instead of guessing

| Working on | Skill |
|---|---|
| Fyn / AI chat / prompts / SSE / tool catalogues | `fyn-architecture` |
| Form Requests, Stores, migrations, Resources, projections | `data-integrity-traps` |
| Writing tests, or a suite went red | `test-failure-forensics` |
| Any module — before starting, and before dispatching an agent | `vault-context` |
| Verifying `/m` | `verify-m` |
| Shipping feature → dev → main | `release` |
| Emails, HTML pages, charts, iOS simulator, PRDs, tech debt | `email-template`, `html-template`, `ui-graph`, `ios-simulator`, `prd-writer`, `tech-debt-session` |

Directory conventions live in the nested `CLAUDE.md` files (`app/Http/`, `app/Services/`, `database/`, `tests/`, `resources/js/`, `ios-native/`) and load when you work there.

## Commands

```bash
./dev.sh                  # Laravel + Vite
./vendor/bin/pest         # tests (add a path/--filter to narrow)
./vendor/bin/pint         # PSR-12 format
php artisan db:seed       # reseed, preserves existing data
```

**NEVER `migrate:fresh` or `migrate:refresh`** — they drop all tables. **NEVER `--env=testing`** — it resolves to the dev database and wipes it.

**Always `php artisan db:seed` after anything that loses local data** (migrations, resets, drops). Targeted reseeds via `db:seed --class=<Name>Seeder --force`: `TaxConfiguration` (tax calcs failing), `TaxProductReference` (Tax Status tab empty), `PreviewUser` (personas broken), `ActuarialLifeTables` (life expectancy errors), `SavingsMarketRates`.

Custom commands: `php artisan list | grep -E 'fyn:|preview:|audit:|subscriptions:|sessions:|registrations:'`.

## Key Rules

**1. Preview isolation.** Preview users (`is_preview_user = true`) are seeded personas, separate from real users. Debug them with `WHERE is_preview_user = true`. Test via the landing-page persona selector at localhost:8000, never direct URLs.

**2. No hardcoded tax values.** Everything through `TaxConfigService` (`$this->taxConfig->getInheritanceTax()['nil_rate_band']`) or `taxConfig.js` on the frontend — including values inside user-facing strings and Vue arithmetic. Active year 2026/27; never hardcode the year either.

**3. Form modals emit `save`, not `submit`** — the parent makes the API call and closes on success. See `resources/js/CLAUDE.md`.

**4. Canonical enums.** Ownership: `individual` (never `sole`), `joint`, `tenants_in_common` (property only), `trust`. Property: `main_residence`, `secondary_residence`, `buy_to_let`. Mortgage: `repayment`, `interest_only`, `mixed`.

**5. Currency formatting** via `currencyMixin` — never a local `formatCurrency()`.

**6. Joint assets are ONE record**, not two: `joint_owner_id` + `ownership_percentage` (primary owner's share; spouse gets `100 - x`). Query `WHERE user_id = ? OR joint_owner_id = ?`. Use `CalculatesOwnershipShare` (backend) or `ownership.js` (frontend). Joint ISAs do not exist in UK law; joint non-ISA savings do.

**7. New auth-related POST routes must be added to `EXCLUDED_ROUTES`** in `PreviewWriteInterceptor.php`, or preview mode blocks them.

**8. No amber or orange, ever.** Warnings → `violet-*`; errors → `raspberry-*`; success → `spring-*`.

**9. No cold acronyms in user-facing text.** Spell out on first use *on the surface the user is looking at* — "the Alternative Investment Market (AIM)", then "AIM" freely. A definition in another component or module does not count. ISA is always fine.

**10. Design system.** `./fynlaDesignGuide.md` (v1.3.1) is the source of truth for colour, type, components and charts. **Where Rules 12 and 15 conflict with it, these rules win** — the guide predates them.

**11. CSS.** Palette tokens only (`raspberry/horizon/spring/violet/savannah/eggshell/neutral/light-*`; never `primary-*`/`secondary-*`/`gray-*`). No hex in `<style>` — `@apply` instead; chart colours from `designSystem.js`. Check `app.css` for an existing global class before adding scoped CSS (`.card*`, `.scrollbar-*`, `.animate-fade-in*`, badges, the spinner).

**12. No scores in user-facing UI.** No "75/100", adequacy/diversification/portfolio-health ratings, score badges or score-based narrative. Use currency, percentages, time periods and actionable guidance.
*Carve-out:* gamification CSJ has explicitly designed is allowed — notably the approved `/m` dashboard layer (level wheel, "X of Y actions complete", "ahead of X% of people", via `MobileLevelService`). **Never strip it, score-launder it, or flag it in an audit.** `ModuleSummaryController::removeScores()` strips *financial-quality* scores only and must never be extended to `level`/`percentile`. Banned regardless: any rating an LLM adds on its own initiative, and any "X/100" anywhere.

**13. Every routed view wraps in `<AppLayout>`** (authenticated) or `<PublicLayout>` (public); `/m` views wrap in `<MobileChrome>`. A chrome-less page is a dead end. Only exception: CSJ says "standalone".

**14. LOOP UNTIL CORRECT — NON-NEGOTIABLE.** For all tests, and whenever CSJ points at a plan and says "make this work": **loop until green per that plan. Do not stop, hand back, declare partial success, or write an apology instead of a fix.**
Diagnose with file:line evidence (systematic-debugging skill) → fix the root cause → re-verify end-to-end in Playwright → if still red, repeat with the new evidence.
**Acceptance is defined by the plan, not by you.** For BS-NN scenarios, the docblock in `tests/Browser/scenarios/BS-NN-*.php` is the contract — every assertion must hold.
Only two exits: **(a)** green per the plan's full criteria, verified live; **(b)** a question genuinely needing a CSJ decision the plan does not answer, after exhausting the plan, spec, canonical contract and memory. "What should I try next?" is not an exit.
Forbidden inside the loop: apologies without a fix attempt, completion on partial evidence, "good enough" because the plan did not anticipate the bug (open the bug-fix sub-task and re-verify in the same loop), and stopping to write reports. **Reports come after green.**
*Owned by CSJ. The `MEMORY.md` and vault copies are read-only mirrors.*

**15. Icons — functionally necessary only; decorative banned.** Functionally necessary = the icon is the only way to identify or operate the element. Decorative = balance, personality, or a label that "feels bare".
**Banned surfaces:** the Fyn chat window (all of it — Fyn speaks in plain text), dashboard cards, and every module detail view and drill-down.
**Allowed surface:** the side nav (`AppNavbar`), which collapses to icon-only.
**Anywhere else — ask CSJ.** Default is no icon; don't copy nearby patterns.
**Banned everywhere, even the side nav:** emoji in any string, label, tooltip, AI response, prompt, commit message, comment, doc, JSON, DB row or migration; Unicode-as-icons (★ ✓ ✗ → ⚠); `::before`/`::after` glyphs; icon fonts.
**The Fyn character is ALWAYS allowed**, everywhere, at any size, on every surface. Never strip it, flag it, or raise it as a decision.
*Carve-out:* icons that are part of a design CSJ specified or approved are allowed even on banned surfaces. Still banned: any icon an LLM adds on its own initiative.
**Forward-only.** Existing violations are grandfathered (`goalIcons.js` emoji, `AdminDashboard.vue` arrows) — don't rip them out or flag them in audits. Everything new complies from the moment it lands. If a plan shows icons on a banned surface, strip them before coding and flag the plan.
*Owned by CSJ. No plan, PR, sub-agent, design guide or historical spec overrides this.*

**16. Build to the agreed spec.** Implement what was specced — never invent an unagreed design decision, substitute a cheaper approximation, or change which behaviours or tiers were agreed. If the spec looks wrong, **stop and ask before deviating**; never ship the deviation and explain afterwards. Verify a spec change in the live UI before calling it done.

**17. Lean cadence.** When CSJ signals "lean" or is iterating on prompts, evals or a multi-PR refactor, queue PRs and do one consolidated test pass instead of a full suite per change. This never weakens Rule 14. Unsure whether something is lean-eligible? Ask.

**18. Internalise agreed plans.** Once CSJ has explained an architecture or deferred an issue, act on it — don't re-raise settled decisions or make CSJ re-explain. Re-read the spec, canonical contract and memory before asking.

**19. Every instruction applies to `/m` too.** "Add X to the dashboard", "fix the tax strategy page" implicitly include `resources/mobile/`. **"Done" for user-facing work = verified on web AND `/m`.** The backend is shared by architecture, so the gap is always the per-surface frontend. A plan silent on `/m` still has `/m` in scope — flag, don't skip. Exceptions: CSJ says "web only", or the surface has no mobile counterpart (e.g. admin).
*Owned by CSJ (2026-06-11).*

**20. GOLDEN RULE — every Fyn change is made ONCE, in ONE place, for ALL surfaces.** Never piecemeal, never per-site copies. **Before fixing any Fyn behaviour, enumerate every mechanism that implements it; if more than one exists, consolidating them is part of the fix.** Editing copies in lockstep is a violation, not a fix. Not done until proven on all surfaces **and all paths** — fresh and resumed conversations, first and repeat turns, every dispatch branch. Details and the failure history: `fyn-architecture` skill.
*Owned by CSJ (2026-07-23). No plan, PR or sub-agent overrides it.*

**21. Running a tester agent makes you the coordinator** for the whole life of that run. **The tester must never sit idle.** The only legitimate idle states are a decision only CSJ can make (after exhausting the persona file, plan, spec, contract and vault), and a finished green run. Anything else is a coordinator failure: keep it fed by re-tasking onto untouched surfaces, batch fixes by subsystem and dispatch in parallel (cause before symptom), unblock tooling/provisioning/test-data yourself, and answer what you can answer. **The run ends when every defect it raised is fixed and green where CSJ tests** — not when the passes finish. Speed comes from parallelism, never from thinning verification.
*Owned by CSJ (2026-08-21).*

**22. Hand over at 900k context, never run into the ceiling.** Applies to main inference and sub-agents alike. At ~900k, or the first harness signal of context pressure, **stop taking new work** — don't start one more check. An agent cannot clear itself: it writes a handover and returns it; the coordinator spawns a fresh agent seeded with it.
The handover must carry: the task as dispatched verbatim plus amendments; what is DONE with evidence (file:line, board ids, DB rows, screenshots); what is IN FLIGHT and its exact state; what is NOT STARTED in priority order; decisions taken and why; dead ends ruled out; environment state depended on.
**Where:** alongside the work — test runs in `tests/Persona/<run>/reports/`, fix batches in `workforce/branches/` — and linked from the run log or board item.
*Owned by CSJ (2026-08-21). This section is the one home for the rule; it is not copied into agent definitions.*

## Working style

- **Scope.** Change only what was asked. **Report adjacent issues rather than silently fixing them.** Validate at system boundaries only — trust internal code and framework guarantees.
- **Never dispatch an agent with just "fix X" or "build Y".** Load `vault-context` for the module first and pass on what you learn: the architecture patterns, the recent bugs and fixes in that area, and the rules that apply.
- **Code review output.** Report every issue with confidence + severity. Coverage, not judgment — don't pre-filter for "only important issues". Use `/code-review` for full reviews, `pr-review-toolkit` agents for targeted passes, `security-reviewer` + `tax-compliance-reviewer` for auth, financial-data and tax changes.
- **Effort.** Default `xhigh`; `high` for routine edits; `max` only for genuinely hard problems.

## Branching and deployment

```
<feature-branch>  --PR-->  dev  --PR-->  main
```

| Env | URL | Branch | Server path | SSH |
|---|---|---|---|---|
| Production | fynla.org | `main` | `~/www/fynla.org/public_html/` | `ssh.fynla.org:18765` as `u2783-hrf1k8bpfg02` |
| Dev/staging | csjones.co/fynla | `dev` | `~/www/csjones.co/fynla-app/` | `ssh.csjones.co:18765` as `u163-ptanegf9edny` |

- **Branch off `dev`, never `main`.** All PRs target `dev`; only `@Stoff73` opens the `dev → main` release PR or merges either branch. CSJ's own branches need no prefix; external contributors use `feature/icecube/<task>`.
- **Never mix builds.** `./deploy/fynla-org/build.sh` (prod) and `./deploy/csjones-fynla/build.sh` (dev) set different `VITE_BASE_PATH`/`RewriteBase`. The wrong one deployed is a blank page or 404 loop with no error. Build locally — the servers lack the npm memory.
- **The `ssh-fynla` MCP tool is PRODUCTION.** Never use it for csjones (use `~/.ssh/fynlaDev`).
- Procedures: `deploy/DEPLOY.md` and the `release` skill. Credentials live only in each server's `.env`. **Never recommend deploying as a next step — CSJ decides when to ship.**

## Mobile clients

**`/m`** (`resources/mobile/`) is an **isolated** Vite bundle (`vite.mobile.config.js` → `public/m-build/`) with its own api/router/store/tokens. **A fix in `resources/js/` does not reach it.** Its only shared file is `store/modules/auth.js` — mobile logout must call `auth/mobileLogout`, never `auth/logout` (which revokes the token and breaks Face ID).

**`ios-native/`** (SwiftUI) — see `ios-native/CLAUDE.md`. Three standing traps, all configuration rather than code:
- **The TestFlight build is `Fynla-Staging` and reads the csjones database.** A fynla.org account does not exist there; login returns 401 `user_not_found`, shown as "Invalid email or password". **Testers must register on csjones.co/fynla.**
- **Production has no `/api/v1/native/*` routes**, so `Fynla-Production` cannot complete login. Fixing it is a `dev → main` release.
- **No in-app purchase products exist in App Store Connect**, so the paywall reads "Premium subscriptions are unavailable". The 6 red `Local StoreKit configuration` tests are a real signal of this, not noise.

**`ios/`** is the dormant Capacitor target, untouched since 2026-03-13. Don't develop against it without asking CSJ.

## Testing

**Browser testing rules — these override everything.**
1. **"Browser tested" = you interacted in Playwright** — clicked, filled, submitted, verified. A snapshot or a diff is not a test, and every `[x]` needs a corresponding interaction.
2. **Not done until every form is filled and submitted.** Never "requires user assistance".
3. **If you could not test it, write "I COULD NOT TEST THIS"** — never "verified", "pass" or "confirmed".
4. **Re-test from step 1 after any code change**, and hit a blocker → stop and ask. **No completion report until testing is done.**

**Pest:** `it()`/`describe()`, `declare(strict_types=1)`, `RefreshDatabase`, `Sanctum::actingAs()`, `Mockery::close()` in `afterEach`. TaxConfiguration is auto-created by the `Pest.php` global hook (a 2019/20 safety net); tests needing real seeded years still call `$this->seed(TaxConfigurationSeeder::class)`. Conventions in `tests/CLAUDE.md`; failure diagnosis in the `test-failure-forensics` skill.

**Login for testing.** Local dev — fetch the code yourself, never ask CSJ:
```bash
php artisan tinker --execute="\$u = \App\Models\User::where('email','john@example.com')->first(); echo \App\Models\EmailVerificationCode::where('user_id', \$u->id)->latest()->first()->code ?? 'none';"
```
Users: `john@example.com` / `jane@example.com` (spouse) / `sarah@example.com`, all `password`; `chris@fynla.org` / `Password1!` (admin). **On production, ask CSJ for the verification code.**

## Troubleshooting

CSJ tests in incognito — never suggest clearing the browser cache.

| Symptom | Fix |
|---|---|
| Homepage or `/m` landing shows the SPA instead of the server-rendered page | `php artisan route:clear`. **NEVER `optimize`/`route:cache` on this app** — the compiled matcher lets the SPA catch-all shadow `/`. Re-cache config only. |
| Blank page referencing 127.0.0.1:5173 | `rm public/hot` on the server |
| "Fixes aren't coming through" locally | Check `public/hot` mtime and the Vite port first. Vite is `:5173` strictPort (5174 collides with fynlaInternational). |
| MIME type errors | Rebuild with the correct env's `build.sh` |
| 500 DirectoryMatch | Upload `deploy/fynla-org/.htaccess` |
| 429 Too Many Requests | `php artisan cache:clear` |

Routes: `php artisan route:list --path=<endpoint>`.

## Standards

- `declare(strict_types=1);` in every PHP file.
- **User-facing text is British** (Optimisation, Customise); **code syntax is American** (optimize, center). Vuex actions use British spelling: `analyse`, `optimise`.
- Never edit `.env` or DB rows to work around a bug — ask. Never version-bump unless asked.
