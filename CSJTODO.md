# CSJTODO — Fynla

*Last updated: 15 April 2026 — session 56*
*Previous session: 14 April 2026 — sessions 51 & 52*

---

## Session 56 (15 April) — Awin Affiliate Integration Live on Production

### Completed This Session

- [x] **Full Awin affiliate attribution integration built, bundled with dev branch, and deployed to production.** Merchant ID 126105. Dual-track attribution (browser pixel + server-to-server). Phase 1 scaffold (`config/awin.php`, `CaptureAwcCookie` middleware, `EncryptCookies` exception, CSP extension, 4 new `payments.awin_*` columns via nullable/backfill-safe migration). Phase 2 backend (`AwinTrackingService`, `FireAwinConversionJob` with `tries=3` + backoff `[30s, 5min, 30min]` + idempotent via `awin_fired_at`, wired into `PaymentController::createOrder`/`confirmPayment` + `WebhookController::handleOrderCompleted`, response payload threading). Phase 3 frontend (`resources/js/utils/awinTracking.js`, `cookieConsent` hooks, `router.afterEach` hook, `CheckoutPage.vue` fires browser pixel after GA4 event). 32 new tests (16 unit + 7 job + 9 integration) all green.
- [x] **Merged `origin/dev` into `awinPlusDev`** to bundle PR #210 (Stocks & Shares ISA + How Much To Retire insight pages) and PR #211 (10 email template redesigns + review carousel + Meta Pixel tracking + persona modal mobile fix + `email:test` artisan command) with the Awin ship. Resolved 3-way merge conflict in `CheckoutPage.vue` (both branches inserted blocks at the same post-GA4 location — kept both, Meta Pixel first then Awin) + clean auto-merges in 2 `.env.production` templates.
- [x] **Meta Pixel CSP fix** — PR #211 shipped `fbq('track','Subscribe')` but never updated `SecurityHeaders.php`. Added `connect.facebook.net` + `www.facebook.com` to `script-src` / `img-src` / `connect-src`. **Resolves CSJTODO session 51 outstanding item "Meta Pixel CSP — connect.facebook.net and www.facebook.com not in SecurityHeaders.php whitelist."**
- [x] **PR #197 cleanup** — moved 9 content blueprints from repo root to `Articles/` via `git mv` (history preserved): `faq.md`, `how-it-works.md`, `ice-letters.md`, `iht-planning.md`, `monte-carlo.md`, `net-worth-dashboard.md`, `pension-tracker.md`, `protection-gap.md`, `when-can-i-retire.md`. `SITE_ARCHITECTURE.md` moved to `fynlaBrain/Architecture/`. **Resolves CSJTODO carry-over "PR #197 cleanup — 9 markdown files in repo root should be moved to Articles/"** (was actually 10 files, not 9).
- [x] **LifeStageService `current_value` typo fix** — diagnosed the "missing migration" CSJTODO item as a **code typo, not a missing migration**. `hasPensionValueAbove()` at line 194 summed `current_value` but the `dc_pensions` table column has always been `current_fund_value`. 57 production errors logged since 8 April (all silently caught in a try/catch). Fixed in commit `1ce51d4`, verified post-deploy against 5 real users with DC pensions returning correct sums (£12k-£844k range). **Resolves CSJTODO carry-over "Fyn Quick Start flow — `dc_pensions.current_value` column missing on prod" and "Run pending migration on production — `dc_pensions.current_value`."** Also confirmed during the investigation that the other two fynQuickStartBugs items (`employment_status` enum missing `full_time`, `users.plan` enum missing `family`) are already resolved on production.
- [x] **Committed remaining untracked sources** — `awin/` onboarding materials (6 PNGs + integration.md), 4 research `.docx` files, `.claude/skills/security-and-hardening/SKILL.md`. Extended `.gitignore` with `.claude/scheduled_tasks.lock` runtime state file.
- [x] **Deployed to production in-session.** 23 PHP/Blade files uploaded via SSH with 17-file rollback backup at `~/www/fynla.org/backup/2026-04-15-awin-deploy/`. Migration `2026_04_15_153100_add_awin_tracking_to_payments_table` run on prod. `AWIN_ENABLED=true` added to live `.env`. All Laravel caches cleared + rebuilt. Local production build run via `./deploy/fynla-org/build.sh` with `VITE_AWIN_ENABLED=true` baked in, `public/build/` uploaded by CSJ.
- [x] **Post-deploy smoke tests all clean.** Playwright verification on `https://fynla.org/`:
  - 0 console errors (previously 1: Meta Pixel CSP — now fixed)
  - `window.fbq === 'function'`, PageView queue flushed
  - `window.AWIN` initialised, `#awin-master-tag` script with `src=https://www.dwin1.com/126105.js` present in DOM
  - `awc=DEPLOY-SMOKE-2026-04-15` Set-Cookie captured with all 6 attributes: 365d TTL, Secure, HttpOnly, SameSite=Lax, domain=fynla.org, path=/
  - Both new insight pages render with correct titles: `/insights/stocks-shares-isa-uk`, `/insights/how-much-to-retire-uk`
  - CSP headers contain all 4 whitelisted domains: `dwin1.com`, `awin1.com`, `connect.facebook.net`, `facebook.com`
- [x] **Pushed `awinPlusDev` and `awinIntegrate` to origin.** `awinPlusDev` is 8 commits ahead of `main`, tracking `origin/awinPlusDev`.
- [x] **Full vault sync** — 6 new update notes copied to vault, `Apr15.md` git history file created, April Index updated with Session 56 summary + 11 wikilinks, Home.md updated with new totals (2,219 commits / 279 for April / 11 days) and `AwinIntegration` added to Current State section, CLAUDE.md metrics bumped (Vue 660→663, PHP services 233→234).
- [x] **Tech debt audit** — clean bill of health across the 14 in-scope files I authored this session. Report at `tech-debt-report.md`. Zero issues found (strict_types ✓, type hints ✓, no hardcoded tax values ✓, no banned colours ✓, no TODO markers ✓, no dead code ✓, all file sizes well under thresholds, security invariants correct).

### NOT Done — Outstanding from Session 56

- [ ] **🔴 First real Awin conversion validation** — `awinPlusDev` holding on merging to `main` until a real purchase fires end-to-end. Success criteria: `payments.awin_fired_at` populated, `[awin] s2s fired` entry in `storage/logs/laravel.log` with status 200, sale visible in Awin merchant dashboard within 2h. When validated, merge to main:
  ```bash
  git checkout main
  git merge awinPlusDev --no-ff -m "merge: awinPlusDev → main — Awin live validated"
  git push origin main
  ```
  Then delete `awinIntegrate` (subset of `awinPlusDev`).
- [ ] **Clean up `public/build/` cruft on production** — directory is currently 207MB with stale hashed files from multiple prior builds. Not a blocker (older files are unreachable from the current `manifest.json`), but future housekeeping task.

### Context for Next Session

Production is running the full Awin stack as of ~20:00 BST on 15 April. Backend cookie capture, S2S job, and admin/preview exclusions are all live. First Meta Pixel `Subscribe` event will fire on next real subscription checkout (CSP fix is live). First Awin S2S will fire when a user with an `awc` cookie completes a payment.

The `awinPlusDev` branch is 8 commits ahead of main, all pushed to origin. Main has received no changes this session — the user explicitly asked to hold the merge until first-conversion validation. If a real Awin conversion lands overnight, tomorrow's session should:
1. Run the verification tinker command (below) to confirm `awin_fired_at` populated
2. Grep `storage/logs/laravel.log` for `[awin] s2s fired` entries
3. If both confirm, merge `awinPlusDev` → `main` and delete the feature branches

Verification command:
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan tinker --execute="\$p = \App\Models\Payment::where('status','completed')->whereNotNull('awin_fired_at')->latest()->first(); echo \$p ? json_encode(['id'=>\$p->id,'cks'=>\$p->awin_cks,'ref'=>\$p->awin_order_ref,'acq'=>\$p->awin_customer_acquisition,'fired'=>\$p->awin_fired_at?->toIso8601String()], JSON_PRETTY_PRINT) : 'no conversion yet';"
grep '\[awin\]' storage/logs/laravel.log | tail -20
```

---

## Carry-Over From Sessions 51 & 50 — Still Outstanding

- [ ] **Jessica Cracknell (user 301)** — ghost trial cleanup side effect from session 51; she was the only real user in the batch of 11 expired trialing subs. Never received a reminder email. Worth flagging if she gets in touch, and/or a goodwill gesture (trial reset, one-off discount).
- [ ] **Update `fynlaBrain/Architecture/v083/11-CONFIGURATION-DEPLOYMENT.md`** to document the SiteGround cron setup as a deploy step (so this can never recur on a future server migration).
- [ ] **Verify cron is still firing on production** — should be running daily per session 51 setup. Check trial_reminder_log + pending_registrations cleanup (hourly) + laravel.log for any scheduler errors.
- [ ] **Generate missing invoice for payment #17** (user 542, chris@fynla.org) — from session 47.
- [ ] **`fynNew` branch** (25 Fyn Response Architecture commits) still unmerged.
- [ ] **Add `.claude/settings.json` to `.gitignore`** — tax-hook path keeps reverting. Note: this is a different file from `.claude/settings.local.json` (already ignored) and `.claude/scheduled_tasks.lock` (ignored by session 56).
- [ ] **Fyn Quick Start flow "empty user" issue** — the `dc_pensions.current_value` typo blocker is now resolved, but the original fynQuickStartBugs.md report also flagged that `CoordinatingAgent::buildFinancialContext()` runs full module analyses against users with zero data, causing the AI to hallucinate numbers and module agents to error on empty data. Fix options documented in `April/April9Updates/fynQuickStartBugs.md` section 2. The "Quick start with Fyn" CTA is still hidden on the landing page until this is addressed.
- [ ] **Lifecycle email engine (PR #212)** still not deployed. Still targeted at main. Requires conflict resolution in `trial-expiration-reminder.blade.php` (PR #211 redesigned it, PR #212 palette-fixed it) when it comes time to ship.

---

## Outstanding — Tech Debt (Deferred from Code Review)

### Duplicate Code Consolidation (from 9 April audit)
- [ ] DUP-01: Consolidate `determineTaxBand` — 7 implementations across services → UKTaxCalculator
- [ ] DUP-02: Consolidate DC pension annual contribution — 5 duplicates
- [ ] DUP-03: Consolidate pension tax relief — 3 duplicates
- [ ] DUP-04: Consolidate `calculateFutureValue` — 5 duplicates (shared service exists but not injected)
- [ ] CONV-03: Migrate 22 Vue components from direct currency import to currencyMixin

### God Class Refactors (multi-session each)
- [ ] RetirementIncomeService.php (2,292L) → extract ProjectionEngine, PCLSCalculatorService, AllocationStrategyService
- [ ] IHTCalculationService.php (1,641L) → extract CharitableRateCalculator, RNRBCalculator, ProjectedEstateService
- [ ] UserProfileService::getFinancialCommitments (421-line method)
- [ ] User.php (713L, 59 methods) → extract HasSubscription trait, DomicileService
- [ ] InvestmentAccount.php (492L, ~164 fillable) → polymorphic sub-type tables
- [ ] TaxSettings.vue (3,068L), ExpenditureForm.vue (2,574L), CalculatorsPage.vue (2,471L), Dashboard.vue (2,215L), RetirementIncomeTab.vue (2,107L)

### Architectural Debt
- [ ] Float-to-decimal cast sweep across 9 models (65 columns)
- [ ] FormRequest migration across 26 controllers (~78 new classes)
- [ ] API Resource extraction for 92 controllers returning raw JSON
- [ ] Split InvestmentController (1,070L) + AdminController (794L) + GoalsController (792L)
- [ ] Nonce-based CSP to replace `unsafe-inline` (MEDIUM-01 from security audit)
- [ ] npm audit fix (14 vulnerabilities, 11 high — breaking changes need testing)

### Test Coverage Gaps
- [ ] ~85 services with zero tests (IHTCalculationService most critical)
- [ ] Investment Analytics/Rebalancing/Performance/Tax subdirectories entirely untested (~35 services)
- [ ] AutoRiskCalculatorTest — `risk_level` column enum doesn't accept `medium_low` (2 pre-existing failures)

## Known Issues

- [ ] Retirement "Other Assets" cards overflow at 1118px
- [ ] DB pension field mapping mismatch
- [ ] Expenditure form fill doesn't animate
- [ ] property_sale life event creates property record (double navigation)
- [ ] 3 flaky WillBuilder tests (`tests/Feature/Estate/WillBuilderApiTest.php`) — "James Serenity Carter" persona middle name pollution only surfaces under full-suite ordering. Pass 14/14 in isolation. Pre-existing on main, not introduced by this session's work.

## Deploy Status

- **PR #208 (claudeReview):** DEPLOYED 9 April 2026 — spouse toggle, dashboard refresh, markdown, enum, tier gating
- **Trial reminder migration (notifications table):** DEPLOYED 14 April 2026
- **Production cron entry:** ADDED 14 April 2026 via SiteGround Site Tools — verified firing on 15 April
- **`awinPlusDev` bundle:** DEPLOYED 15 April 2026 — Awin integration (phases 1-3) + PR #210 insight pages + PR #211 email redesigns + review carousel + Meta Pixel tracking + Meta Pixel CSP fix + LifeStageService typo fix. Branch not yet merged to main (holding for first real conversion validation).
- **Lifecycle email engine (PR #212):** NOT DEPLOYED. Still targeted at main. Requires merge conflict resolution with PR #211's `trial-expiration-reminder.blade.php` redesign.
