# Fynla — Full Code, Security & Best Practices Review

**Date:** 12 May 2026
**Branch / commit at review time:** `main` (clean, `a7f137a`)
**Codebase scale audited:** 828 PHP files · 733 Vue components · 134 JS files · 215 migrations · 409 test files · 297 services · 110 controllers · 111 models · 35 Vuex modules · 9 Agents
**Reviewers:** Seven parallel domain agents — backend, frontend, security, database, tax compliance, testing, conventions
**Method:** Static analysis of code, migrations, configs, deploy templates, plus `composer audit` + `npm audit`. No browser testing performed for this audit.

This is the executive synthesis. Each domain has a full report linked below; this document surfaces the cross-cutting themes, top critical issues, and a prioritised remediation roadmap.

---

## Sub-reports

| Domain | File | Lines | Findings |
|--------|------|-------|----------|
| Backend (Laravel/PHP) | [`review-backend.md`](review-backend.md) | 461 | 4 critical, 12 important, 11 medium-low |
| Frontend (Vue/JS) | [`review-frontend.md`](review-frontend.md) | 727 | 8 critical, 19 important, 22 medium-low |
| Security audit (OWASP + Fynla-specific) | [`review-security.md`](review-security.md) | 770 | 0 critical, 6 high, 17 medium, 19 low, 11 info |
| Database (schema/queries) | [`review-database.md`](review-database.md) | 1398 | 10 critical, 37 high, 50 medium-low |
| Tax / HMRC compliance | [`review-tax-compliance.md`](review-tax-compliance.md) | 851 | 4 critical, 30+ high-medium, P0–P3 list |
| Testing / coverage | [`review-testing.md`](review-testing.md) | 787 | 8 systemic, 175/290 services untested |
| Best practices & conventions | [`review-conventions.md`](review-conventions.md) | 704 | 100+ across 16 CLAUDE.md rules + dead code |
| **Total** | | **5,698** | **~400 findings** |

---

## 1. Executive Summary

**Overall verdict: B-.** Fynla is a mature, well-architected app with strong foundations — the Two-Fyn architecture is fully respected, joint-ownership patterns are consistent, mass-assignment is locked down, MFA / Sanctum / Revolut webhook signature verification are best-of-breed, the AI audit hash-chain design is textbook, and the project's own canonical contracts are honoured throughout. The team has clearly read OWASP and applied many defensive patterns.

That said, several themes recur across multiple domains that need attention:

**The four standout issues** (each flagged independently by 2+ domain agents):

1. **PCLS (pension tax-free cash) is uncapped at the Lump Sum Allowance** — every PCLS calculation is `pot × 0.25` with no `£268,275` ceiling. A user with a £1.5M pot is told they can take £375k tax-free; HMRC caps at £268,275. This is a **user-visible numeric error of ~£100k for higher-net-worth users**. (Tax C-1, partially Backend C-3)

2. **The TransientToken `$id` bug family has a 7th site** — `TokenRefreshController:23` calls `currentAccessToken()->delete()` without an `instanceof PersonalAccessToken` guard. Under SPA cookie auth, the "new" token issued is the only valid one; the old token (cookie/bearer) silently remains active until 30-day natural expiry. **Token rotation silently fails.** A second site (`EvalBypassGate:45`) reads `$token->abilities` unsafely on a security boundary. (Security A01-01, Backend C-1/C-2, memory `reference_transient_token_family_bugs.md`)

3. **Currency precision: float-everywhere across users + holdings.** The `users` table has ~18 expenditure/income columns stored as `double`. The `Holding` model casts `decimal(15,6)` schema values back to PHP `float` on read. Every portfolio total, every CGT calculation, every rebalancing recommendation passes through float arithmetic that drifts by pence per page render and pounds per quarterly report. (Database S-01, S-02)

4. **Tax-year drift: docs say 2025/26, seeded config is 2026/27, ~30 stale fallback literals are 2025/26.** The active tax year mismatch isn't itself a bug, but the fallback literals (`?? 0.0875`, `?? 0.10`, `?? 0.20`, `?? 0.138`) silently downgrade to 2025/26 rates if the lookup ever returns null. Worst offender: `TaxDragCalculator.php` has dividend rates fully hardcoded with no config lookup at all. (Tax §1a/1b, Conventions Rule #3, Backend M-1)

**Security posture is strong but not perfect.** 0 critical, 6 high. The 6 high items are: supply-chain CVEs (capacitor biometric auth-bypass + 13 axios advisories), the TransientToken refresh bug, conflicting `X-Frame-Options` headers (SAMEORIGIN vs DENY), non-constant-time email-code comparisons in password reset and GDPR deletion, eval-route prod guard fragility, and admin auto-promotion at login from `ADMIN_EMAILS`.

**Testing coverage is the weakest dimension.** 175 of 290 services (60%) have no unit test. 4 of 9 agents are completely untested (Estate, Investment, Retirement, TaxOptimisation). 22 tax tests mock `TaxConfigService` and assert on the mocked literals — silently defeating the no-hardcoded-tax-values rule at the test layer.

**Design system compliance is mostly clean — except icons.** Zero `amber-*` / `orange-*`. No `'sole'` enum. No `v-if`+`v-for`. But ~14 dashboard cards and ~19 detail-view components ship decorative icons in violation of Rule #16. The Goals module uses emoji throughout via `goalIcons.js` (🔥 🎯 📈 ⭐ 🏆). 72 Unicode glyph hits across `resources/js/`.

---

## 2. Top 10 Critical Issues (Cross-Cutting)

Ordered by combined user impact × confidence × ease of exploit/regression.

### #1 — PCLS uncapped at Lump Sum Allowance (£268,275)
**Domain:** Tax compliance · **Severity:** Critical · **Confidence:** 95
**Files:**
- `app/Services/Retirement/RetirementIncomeService.php:256, 1937`
- `app/Services/Retirement/DecumulationPlanner.php:232, 244`
- `app/Services/Retirement/RetirementActionDefinitionService.php:1982`

Every PCLS computation is `pot × 0.25` with no LSA ceiling. The seeded TaxConfiguration has no `lsa` / `lump_sum_allowance` key, so a config-lookup approach is impossible today. Affected user-facing surfaces: retirement plan tax-free cash, drawdown projections, decumulation strategy.

**Fix:**
1. Add `lump_sum_allowance: 268275` and `lump_sum_and_death_benefit_allowance: 1073100` to `TaxConfigurationSeeder` under `pension`.
2. Replace every `× 0.25` PCLS site with `min(pot × pclsRate, lsaRemaining)` against tracked `lsa_used`.
3. Add a Pest golden test: £1.5M pot returns £268,275 tax-free, not £375,000.

---

### #2 — TransientToken `$id` bug family — 7th site (TokenRefreshController)
**Domain:** Security + Backend · **Severity:** High · **Confidence:** 95
**Files:**
- `app/Http/Controllers/Api/V1/Auth/TokenRefreshController.php:20-26` — refresh path silently no-ops on cookie auth, old token never revoked
- `app/Services/Eval/EvalBypassGate.php:45-50` — reads `$token->abilities` without instanceof guard on a security boundary
- `app/Http/Middleware/EnsureMFAVerified.php:31` — `currentAccessToken()?->can('mfa_verified')` without instanceof guard (MFA bypass/block risk under SPA auth)

The memory file `reference_transient_token_family_bugs.md` lists six sites. This audit finds three new ones bringing the total to nine. **Fix pattern:**
```php
$token = $user->currentAccessToken();
if (! ($token instanceof PersonalAccessToken)) {
    return $this->errorResponse(/* cookie-auth path */);
}
$token->delete();
```

---

### #3 — Float arithmetic on currency at the storage layer
**Domain:** Database · **Severity:** Critical · **Confidence:** High
**Files:**
- `database/schema/mysql-schema.sql:2354-2380` — 18 expenditure columns on `users` are `double`
- `app/Models/Investment/Holding.php:39-49` — schema `decimal(15,6)` cast back to PHP `float`
- ~20 other "monetary" fields across various tables (see review-database.md §1)

PHP `(float) £12,345.67` cannot be exactly represented. Sums computed in MySQL (`SUM(food_groceries)`) drift from PHP-computed sums. CGT calculations on a 25-holding £1.2M portfolio drift £0.40 per page render and compound.

**Fix:** Migrate to `decimal(12,2)` for currency, `decimal(15,6)` for share quantities, and remove `'float'` casts from Eloquent models. Use `pt-online-schema-change` for large-table conversion.

---

### #4 — SDLT first-time-buyer key mismatch
**Domain:** Tax compliance · **Severity:** Critical · **Confidence:** 95
**File:** `app/Services/Goals/GoalAssignmentService.php:122-123`

Code reads `stamp_duty.residential.first_time_buyer` (singular), but seeder writes `first_time_buyers` (plural) — so FTB bands never load. Code also uses £625,000 max FTB property value but seeder has £500,000.

**Effect:** £5,000+ SDLT over-statement for typical FTB users on the Goals "buy property" path.

---

### #5 — Income tax band ceiling formula bug
**Domain:** Tax compliance · **Severity:** High · **Confidence:** High (currently latent)
**Files:**
- `app/Services/UKTaxCalculator.php:644`
- `app/Services/TaxBandTracker.php:38`
- `app/Services/Property/PropertyTaxService.php:218`

Code computes `personalAllowance + bands[1]['max']`, but `bands[1]['max'] = 125140` is the absolute additional-rate threshold, not a band width. Result is `12570 + 125140 = 137710`, not `125140`. Currently masked by the PA-taper coincidence (PA = 0 by the time income hits £125,140), but breaks immediately for any tax-year config without full PA taper.

`bands[1]['max']` semantics are inconsistent with `bands[0]['max'] = 37700` (band width).

---

### #6 — Admin auto-promotion at login from `ADMIN_EMAILS`
**Domain:** Security · **Severity:** High · **Confidence:** High
**File:** `app/Http/Controllers/Api/AuthController.php:184-192`

Any user with an email in the `ADMIN_EMAILS` env variable is promoted to admin (`is_admin = true`) on next login. No approval workflow. Promotion is one-way — removing from the env doesn't demote. Combined with the registration path at line 501-503, an attacker who can register a fresh account with an admin-listed email gains admin without review.

**Fix:** Remove the auto-promotion path. Admin promotion should be a deliberate admin action via the existing `/admin/users` UI.

---

### #7 — Conflicting / deprecated security headers
**Domain:** Security · **Severity:** High · **Confidence:** High
**Files:** `public/.htaccess:64` (SAMEORIGIN) · `app/Http/Middleware/SecurityHeaders.php:23` (DENY) · `.htaccess` also sets deprecated `X-XSS-Protection: 1; mode=block`

Violates the documented `feedback_htaccess_vs_middleware_headers.md` rule. Apache wins on SiteGround so the stricter middleware value is silently overridden. The deprecated `X-XSS-Protection` header can be harmful — Chrome's auditor that consumed it was removed in 2018 and the header has been linked to XSS vulnerabilities of its own.

**Fix:** Single source of truth — pick middleware OR `.htaccess`, not both. Remove `X-XSS-Protection`. Confirm `Permissions-Policy`, `Strict-Transport-Security`, `Content-Security-Policy` are set in exactly one place each.

---

### #8 — Unauthenticated `BugReportController` is a phishing-vehicle / email-flood vector
**Domain:** Security · **Severity:** High · **Confidence:** High
**File:** `app/Http/Controllers/Api/BugReportController.php`

Endpoint is unauthenticated, rate-limited to 5/hour by IP. Submits `description` + `user_agent` + `page_url` + `console_logs` (up to 10KB) into a `Mail::send` to `chris@fynla.org`. Attacker-controlled content can craft credible phishing messages that arrive at the support inbox from `noreply@fynla.org`.

**Fix:** Strip HTML from all fields server-side, cap `console_logs` to 2KB, route through a queue with abuse detection, replace `Mail::send` with a templated mail that escapes all user content, and consider requiring authentication for bug reports (or a captcha if anonymous).

---

### #9 — Supply-chain CVEs in frontend dependencies
**Domain:** Security · **Severity:** High · **Confidence:** High
**Source:** `npm audit` — 11 advisories

Most critical:
- `@capgo/capacitor-native-biometric` — Authentication Bypass (GHSA-vx5f-vmr6-32wf). Directly impacts Face ID login.
- `axios` <1.15.x — 13 advisories (prototype-pollution, SSRF, CRLF, credential injection).
- `@babel/plugin-transform-modules-systemjs`, `tar`, `serialize-javascript`, `vite`, `fast-uri`, `postcss`.

`composer audit` reports zero advisories — backend dependency hygiene is good.

**Fix:** `npm audit fix --force` after verifying breaking changes; pin axios to ≥1.15; replace `@capgo/capacitor-native-biometric` with a maintained alternative or upgrade past the patched version. **Test biometric login on iOS after the upgrade** — biometric is the most-touched mobile flow.

---

### #10 — Rule #16 violation: decorative icons in dashboard cards and detail views
**Domain:** Frontend + Conventions · **Severity:** High · **Confidence:** 85-95
**Files (worst offenders):**
- `resources/js/components/Dashboard/DashboardCard.vue:32-34` — chevron icon on every clickable card (cascading impact via reuse)
- `resources/js/components/Dashboard/AlertsPanel.vue:23-60` — severity icons
- `resources/js/components/Dashboard/AreasToConsiderCard.vue:19-50`
- `resources/js/components/Dashboard/ProfileCompletionCards.vue:17-34`
- `resources/js/components/Dashboard/JourneyCard.vue:8-31`
- `resources/js/views/Advisor/AdvisorDashboard.vue:8-31`
- `resources/js/components/Retirement/AnnualAllowanceTracker.vue:125-140`
- `resources/js/constants/goalIcons.js` — emoji map consumed across Goals UI (🔥 🎯 📈 ⭐ 🏆)

Rule #16 explicitly bans decorative icons in dashboard cards, detail views, and Fyn chat. Side nav is the ONE allowed surface. Conventions agent found 14 dashboard files + 19 detail-view files in violation.

**Note on `AiChatPanel.vue:38`:** Fyn avatar `<img>` in chat header is a borderline case — Rule #16 explicitly forbids mascot images as inline icons but this could be argued as functional (service identity). Flagged for CSJ judgment.

---

## 3. Cross-Cutting Themes

These patterns recur across multiple findings and warrant systemic fixes rather than file-by-file repair.

### 3.1 Tax-year drift + stale fallback literals

The codebase rolled forward to **2026/27** in the seeder, but ~30 fallback literals across services still encode **2025/26** rates. The pattern is:

```php
$rate = $config['basic_rate'] ?? 0.0875;   // 2025/26 dividend basic; 2026/27 is 0.1075
$rate = $config['ni_employer'] ?? 0.138;   // 2025/26 employer NI; 2026/27 is 0.15
$rate = $config['cgt_higher'] ?? 0.20;     // pre-Oct-2024 CGT; current is 0.24
$rate = $config['badr'] ?? 0.10;           // 2024/25 BADR; current is 0.18
```

If any config lookup ever returns null (missing TaxConfiguration row in a fresh deploy, key typo, environment drift), these silently use the stale rate. **Worst case:** `BusinessInterestService.php:171,189` has a fallback `0.10` plus user-facing text ("10% rate") for BADR — the displayed advice would be wrong by 8 percentage points.

**Recommended systemic fix:** Introduce `TaxConfigService::require($path)` that throws `FinancialCalculationException::missingConfig($path)` instead of returning a literal fallback. Migrate all 30+ call sites in one PR. Pair with a Pest architecture test that disallows tax-magic-number literals in `app/Services/Tax/`, `app/Services/Investment/Tax/`, `app/Services/Retirement/`, `app/Services/Estate/`.

**Also fix:** CLAUDE.md "UK Tax Context" section says active year is 2025/26 — update to 2026/27 to match the seeded config.

### 3.2 Three sites where DI is bypassed via `app()`

Six services resolve dependencies via the container in method bodies, against the `private readonly` injection convention:

- `app/Services/Estate/GiftingStrategy.php:29` (constructor)
- `app/Services/Risk/RiskPreferenceService.php:121, 148, 216`
- `app/Services/AI/AdvicePromptBuilder.php:466, 672, 1028` — on AI hot path
- `app/Services/Onboarding/OnboardingChatDirector.php:1260`
- `app/Services/Coordination/CashFlowCoordinator.php:42`

Three of these (`AdvicePromptBuilder`) are on the AI streaming hot path, called on every chat turn. The catches swallow errors silently with a "fall through" comment.

**Fix:** Standard constructor injection across all six. Add Mockery tests for the dependency boundaries.

### 3.3 Currency precision drift (float-on-money)

Three layers of float-on-money in the codebase:

1. **Schema:** 18 `users` table columns + various `holdings`/`monthly_*` columns are `double`.
2. **Eloquent casts:** `Holding` model casts `decimal(15,6)` schema columns to `float`.
3. **Service computation:** Tax services compute pension contributions, CGT, and projections using PHP arithmetic (which is float-by-default unless `bcmath` / `Decimal` is used).

**Fix:** Three-layer cleanup — migrate columns to `decimal(12,2)`/`(15,6)`, change casts to `decimal:2`/`decimal:6`, introduce a `MonetaryAmount` value object for service computations (or use the `bcmath` extension consistently).

### 3.4 Off-palette `purple-*` and `indigo-*` Tailwind classes

~15 component files use `purple-*` (banned — Rule #11/#12). ~5 components use `indigo-*` (also banned). The design system's `RISK_TAILWIND_CLASSES` in `designSystem.js:200-231` itself uses `yellow-*`, `pink-*`, `green-*`, `teal-*`, `blue-*` — none of which are in the approved palette.

**Worst offender:** `AssetsStep.vue` (onboarding) uses `purple-100 text-purple-800` for SIPP/pension/joint badges, exposing the violation on the most-visited onboarding screen.

**Fix:** Bulk find-replace `purple-` → `violet-`, `indigo-` → `violet-`. Then refactor `RISK_TAILWIND_CLASSES` to palette-only. Add an ESLint custom rule to prevent regression.

### 3.5 Hardcoded hex values in scoped style blocks

~196 hex literals across scoped `<style>` blocks. Worst offender: `LetterToSpouse.vue:1178-1423` with 20+ hex values. Inline ApexCharts tooltip HTML in `AssetBreakdownBar.vue:203` and `WrapperOptimizer.vue` (3 sites) also embeds hex in string literals.

**Fix:** Replace with `@apply` directives using palette tokens. For ApexCharts custom tooltips, import constants from `designSystem.js`.

### 3.6 Tests that mock TaxConfigService and assert on the mock

22 tax tests under `tests/Unit/Services/Tax/` and `tests/Unit/Services/Investment/Tax/` mock `TaxConfigService` and assert arithmetic against the mocked literals. This silently defeats Rule #3 at the test layer — if HMRC changes a rate and the seeder updates, the test still passes because the mock is also wrong.

**Worst offenders:** `DividendTaxCalculatorTest.php`, `ISATrackerTest.php`. **Gold-standard counter-example:** `TaxStrategyCalculatorTest.php` uses a real seeder.

**Fix:** Use `RefreshDatabase` + real seeder for tax tests. Mock only at hard service boundaries (HTTP, queue, external API).

### 3.7 Two-Fyn architecture is fully compliant

Three agents independently confirmed:
- `AdviceFyn::WRITE_TOOLS` exhaustively lists every create/update/delete/capture tool; `buildToolList()` strips them via `array_diff`.
- No `FynPersonaOrchestrator`, `DataCapturePromptBuilder`, invoker registry, or `persona_state_change` SSE event exists anywhere.
- `AiChatController::sendMessage` dispatch is a single if-statement on `users.onboarding_completed`.
- `wrapStream` correctly intercepts the `handoff` SSE event before it reaches the frontend (INV-2.4.1).

This is the most load-bearing architectural contract in the codebase right now and it shows. **No action required — note for future contributors.**

---

## 4. Findings by Severity

### Critical (must fix before next release)

| # | Domain | File:Line | Issue |
|---|--------|-----------|-------|
| 1 | Tax | `RetirementIncomeService.php:256, 1937` + 3 others | PCLS uncapped at LSA — £100k+ user-visible error for high-net-worth |
| 2 | Tax | `GoalAssignmentService.php:122-123` | SDLT FTB band key mismatch — £5k+ over-statement |
| 3 | Backend | `V1/Auth/TokenRefreshController.php:20-26` | TransientToken refresh — token rotation silently fails |
| 4 | Backend | `Eval/EvalBypassGate.php:45-50` | TransientToken on a security boundary — bypass risk |
| 5 | Backend | `Retirement/DecumulationPlanner.php:303` | Reads non-existent `higher_rate_threshold` config key |
| 6 | Backend | `Retirement/RetirementStrategyService.php:1186` | Hardcoded £2,000 salary sacrifice limit (Rule #3) |
| 7 | Database | `users` schema, 18 cols | Float columns for currency |
| 8 | Database | `Holding.php` casts | Float cast over `decimal(15,6)` schema |
| 9 | Database | `audit_logs` retention | No partition / no covering index — table-scan retention at scale |
| 10 | Database | `MIG-01` | `truncate()` in migration `down()` — `migrate:rollback` destroys user goals |
| 11 | Tax | `UKTaxCalculator.php:644`, `TaxBandTracker.php:38` | Band ceiling formula bug (latent) |
| 12 | Tax | `UKTaxCalculator.php:687-740` | Starting Rate for Savings not applied — over-tax non-earner savers by £1k |
| 13 | Frontend | `DebugEnv.vue:1-25` | Routed view without `AppLayout` wrapper (Rule #14) |
| 14 | Frontend | 8 dashboard / detail components | Rule #16 decorative icon violations (DashboardCard cascades) |

### High (sprint priority)

| # | Domain | File:Line | Issue |
|---|--------|-----------|-------|
| 15 | Security | `AuthController.php:184-192` | Admin auto-promotion from `ADMIN_EMAILS` |
| 16 | Security | `BugReportController.php` | Unauthenticated phishing/email-flood vector |
| 17 | Security | `public/.htaccess:64` + `SecurityHeaders.php:23` | Conflicting `X-Frame-Options` |
| 18 | Security | `package.json` | 11 npm advisories including biometric auth-bypass |
| 19 | Security | `PasswordResetService.php:79`, `GDPRController.php:426,502,594` | Non-constant-time secret comparison |
| 20 | Security | `EvalAuthController.php:39,89` | Eval-route guard depends on string `APP_ENV='production'` |
| 21 | Backend | `Investment/AssetLocation/TaxDragCalculator.php:303, 317` | Stale 2024/25 hardcoded rates + missing `forUserOrJoint` |
| 22 | Backend | `Estate/IHTCalculationService.php:227` | `saveCalculation()` write-on-read side-effect |
| 23 | Backend | `AiChatController.php:189` | Consent DB query on every SSE event |
| 24 | Backend | `RetirementController.php:217` | Double `agent->analyze()` call wastes computation |
| 25 | Backend | `Retirement/PensionContributionOptimizer.php:461` | `(float) $x ?? 0` operator-precedence bug |
| 26 | Backend | `Dashboard/DashboardAggregator.php:37` | Silent `return []` on exception hides data failures |
| 27 | Frontend | `router/index.js:1543, 1546` | `to.meta.requiresGuest/Admin` instead of `to.matched.some()` |
| 28 | Frontend | `taxConfig.js:34-85` | Frontend hardcoded tax constants stale to backend |
| 29 | Frontend | `RebalancingCalculator.vue:246` | Hardcoded `taxRate: 0.20` in form data |
| 30 | Database | Numerous service queries | N+1 patterns flagged in 11 sites |
| 31 | Database | `investment_accounts` | 154-column god-table needs vertical split |
| 32 | Database | `CoordinatingAgent.php` 7 sites | Raw `orWhere` joint queries instead of `forUserOrJoint` scope |
| 33 | Database | 6 ownership_type enums | Missing `tenants_in_common` (Rule #5) |
| 34 | Tax | `BusinessInterestService.php:171,189` | BADR fallback `0.10` + user-facing "10% rate" warning |
| 35 | Tax | `UKTaxCalculator.php` (income calc) | Adjusted Net Income calculated as gross |
| 36 | Tax | No Scotland support | Despite `'scotland'` placeholder in seeder |
| 37 | Tax | Rental income | No property allowance (£1,000) or rent-a-room relief (£7,500) |
| 38 | Tax | LISA goal logic | No £450k property cap enforcement |
| 39 | Tax | Trust calc | No £500 trust de minimis allowance |
| 40 | Testing | 175/290 services | No unit test |
| 41 | Testing | 4/9 Agents | No agent test (Estate, Investment, Retirement, TaxOptimisation) |
| 42 | Testing | 22 tax tests | Mock TaxConfigService and assert on mock |
| 43 | Testing | 5 planned BS-NN | Missing scenarios (BS-03, 08, 09, 24, 25) |
| 44 | Testing | Rules #5/#6/#7/#9/#10/#13/#14/#16 | Not architecturally enforced |
| 45 | Conventions | ~106 Vue components | Orphaned / dead code |
| 46 | Conventions | `utils/ownership.js` | Documented canonical util, but not imported anywhere |

### Medium / Low

See domain sub-reports for the full ~350 medium/low findings.

---

## 5. Areas That Are Clean

Explicitly called out so they don't regress:

- **Two-Fyn architecture contract** — fully compliant across backend, frontend, tests
- **Joint-ownership pattern** — `forUserOrJoint` scope is used in `InvestmentController`, `SavingsController`, `GoalsController`. Composite indexes exist. (Exception: 7 raw `orWhere` sites in `CoordinatingAgent` and 1 in `TaxDragCalculator` — listed above)
- **Mass-assignment protection** — `$guarded` consistently locks `id`, `is_admin`, `is_preview_user`; no `update($request->all())` anywhere
- **`declare(strict_types=1);`** — all 828 PHP files
- **Sanctum + MFA flow** — progressive lockout, audit logging, encrypted MFA secrets via `Crypt`, hashed recovery codes, login-IP throttling, GDPR multi-step verification, AI audit hash-chain with HMAC fail-loud
- **Revolut webhook signature verification** — `hash_equals` + timestamp tolerance + multi-key rotation
- **`PreviewWriteInterceptor`** — middleware is solid; `EXCLUDED_ROUTES` is reasonable; `bypass-preview-mode` requires BOTH the ability AND the `X-Eval-Run-Id` header
- **Rule #5 (canonical enums)** — zero `'sole'` left in the codebase
- **Rule #9 (no amber/orange)** — zero hits
- **`composer audit`** — zero advisories (backend dependency hygiene is good)
- **iOS `vite.config.js`** — every documented MIME-type / image-import mandate honoured
- **CMS XSS defence** — HTMLPurifier on input, DOMPurify + `sanitizeHtml` on output, `UserContentSanitiser` for AI prompt injection
- **Migration patterns** — anonymous classes, strict types, idempotency guards consistently enforced (with the noted exceptions)
- **Form modal `save` event pattern (Rule #4)** — fully compliant across 33 components

---

## 6. Prioritised Remediation Roadmap

### Phase A — Immediate (this week)

These are user-visible numeric errors or security boundary failures. Don't ship anything else on top until these are fixed.

1. **PCLS LSA cap** — affects every retirement plan touching a £1M+ pot
2. **SDLT FTB key mismatch** — affects every first-time-buyer Goals user
3. **TransientToken refresh + EvalBypassGate guards** — token rotation + security boundary
4. **Admin auto-promotion at login** — remove the path
5. **BugReportController hardening** — strip HTML, queue, escape user content in templates
6. **Conflicting `X-Frame-Options`** — pick one source of truth
7. **`npm audit fix`** — biometric auth-bypass especially
8. **CLAUDE.md tax-year update** — 2025/26 → 2026/27

### Phase B — This sprint

1. **TaxConfigService::require()** + migrate ~30 stale fallback literals
2. **Float-to-decimal migration** for users table + Holding casts
3. **Income tax band ceiling formula fix** (latent but breaks easily)
4. **Starting Rate for Savings** (non-earner savings tax over-charge)
5. **`audit_logs` partition + covering index**
6. **Down-migration safety audit** — find every `truncate()` / `dropIfExists()` in `down()`
7. **Rule #16 icon purge** — start with `DashboardCard.vue` (cascading impact)
8. **Replace `purple-*` / `indigo-*`** with `violet-*` (bulk)
9. **`RISK_TAILWIND_CLASSES`** palette refactor
10. **Architecture tests** for Rules #5, #9, #13, #14 (cheap, high-signal)

### Phase C — Backlog (4-6 weeks)

1. **`investment_accounts` table vertical split** (god-table)
2. **N+1 query cleanup** across the 11 flagged sites
3. **Dead code removal** — ~106 Vue components, 2 PHP services, 1 controller, 3 Vuex modules, `utils/ownership.js` reinstate
4. **Test coverage push** — 175 untested services; the 22 tax tests using mocked `TaxConfigService`; the 4 untested agents
5. **5 missing BS-NN scenarios** (BS-03, 08, 09, 24, 25)
6. **Scotland tax support** (placeholder exists in seeder)
7. **Adjusted Net Income proper deductions** (pension + Gift Aid)
8. **LISA £450k cap enforcement**
9. **`taxConfig.js` (frontend) hydrate from backend** at app boot
10. **ESLint rule for banned Tailwind tokens** (prevent regression)
11. **`bcmath` / `Decimal` adoption** for service-layer tax computation
12. **Vault docs refresh** — design guide version, component counts

### Phase D — Strategic (next quarter)

1. Squash 215 migrations to a baseline schema
2. Pension-IHT inclusion (April 2027 — anticipate now)
3. BADR rate roll-forward to 14% (April 2025) / 18% (April 2026)
4. APR+BPR £1m combined cap (April 2026)
5. New FIG residency regime (April 2025)
6. Frontend dependency upgrade cycle (Capacitor major, Vue minor, Vite major)
7. Browser test CI gate — currently all BS-NN auto-skip
8. Performance budget (bundle size monitoring)
9. Convert 9 remaining legacy PHPUnit-class tests to Pest

---

## 7. Methodology Notes

- **Scope cuts:** No browser/Playwright testing performed. No load testing. No penetration testing. No third-party audit of cryptographic primitives.
- **Confidence calibration:** Each finding carries a confidence tag (low/med/high or 70-100). High-confidence findings have file:line evidence and a clear violation; low-confidence findings are flagged for human judgment (especially Rule #16 ambiguous surfaces like `AiChatPanel.vue` avatar).
- **False positives:** Some flagged "hardcoded tax values" are intentional `TaxDefaults` constants used as fallback layer (architecturally compliant). The reports distinguish these.
- **The `2025/26` vs `2026/27` mismatch in CLAUDE.md is itself a finding** but it doesn't invalidate the rest of the audit — the seeder is authoritative for the active tax year and all tax findings are written against 2026/27 reality.

---

## 8. Verification Plan (Post-Fix)

After Phase A:

1. **Pest golden test** — £1.5M pension pot returns £268,275 PCLS (not £375,000)
2. **Pest golden test** — FTB on £400k house with `first_time_buyers` config produces correct SDLT
3. **Browser test** — login flow, refresh token, logout, verify `personal_access_tokens` row deleted
4. **Browser test** — admin email NOT in `ADMIN_EMAILS` cannot become admin via login
5. **`npm audit`** — zero advisories
6. **Browser response headers check** — exactly one `X-Frame-Options`, no `X-XSS-Protection`
7. **Pest** — full suite green (currently ~940 tests)
8. **Architectural tests** for Rules #5/#9/#13/#14 — added and green
9. **Visual regression** — design system colour audit on dashboard, onboarding, retirement, estate screens

After Phase B:

1. Bulk migration of `users` table on a staging DB clone — measure ALTER duration, plan production window
2. CGT calculation regression suite (decimal precision boundary cases)
3. Audit log retention dry-run on a partitioned clone

---

## 9. Final Word

The Fynla codebase is in good shape architecturally and has strong defensive engineering throughout. The issues surfaced here are concentrated in **edge cases**, **stale year-over-year values**, and **convention drift on UI surfaces** — not foundational design flaws. The Two-Fyn contract, the joint-ownership pattern, the mass-assignment posture, and the auth flow design are all things the team should be proud of.

The **single most user-visible issue** is the PCLS LSA cap (Issue #1) — for any user approaching retirement with a £1M+ pot, the displayed tax-free cash is currently wrong by tens to hundreds of thousands of pounds. Fix that first.

The **single most operationally risky issue** is the TransientToken refresh bug (Issue #2) — silent token-rotation failure is hard to detect and undermines the entire session security model. Fix that second.

Everything else can run in a sprint cadence.
