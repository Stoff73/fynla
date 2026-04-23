# CSJTODO — Fynla

*Last updated: 23 April 2026 — session 64 (subscription/checkout hotfix day)*
*Previous session: 18 April 2026 — session 63 (full codebase tech debt audit + remediation)*

---

## Session 64 (23 April) — Subscription/checkout hotfix: R1–R5 shipped to production and merged

### Completed This Session

- [x] **R1 — expired-trial checkout loop fixed** — `PlanSelectionModal` + `DataRetentionOverlay` no longer stack on `/checkout`. New `isOnCheckoutRoute` computed in `AppLayout` gates both overlays. `DataRetentionOverlay` "Subscribe Now" is now an `@subscribe` event emitter (was a `<router-link>` that dropped plan/cycle query params). `checkTrialStatus` only auto-shows the non-dismissable plan modal for non-grace expired users.
- [x] **R2 — Student plan gated to `.ac.uk` emails** — `User::isEligibleForStudentPlan()` helper (case-insensitive `str_ends_with('.ac.uk')`) + `PaymentController::createOrder` 422 gate. Frontend hides the Student card in `PlanSelectionModal.filteredPlans`. Public `/pricing` unchanged (all 4 plans for marketing).
- [x] **R3 — plan-card feature copy polish** — new `displayFeatures(plan)` computed. Standard card inlines Student bullets when Student is hidden. Family card always shows "Parents included" + "Children for free".
- [x] **R4 — "Have a discount code?" link removed** from `PlanSelectionModal`. CheckoutPage discount field remains the single entry point.
- [x] **R5 — /pricing Family card bullets** match plan modal (Parents included + Children for free).
- [x] **Production smoke-tested end-to-end** across all five releases with test user `bugrepro_expired_2026_04_23@fynla.org` (non-`.ac.uk`) and temporary `bugrepro_student_r3_2026_04_23@kent.ac.uk` (deleted post-test). `POST /api/payment/create-order` confirmed 200 for eligible users, 422 with correct message for ineligible.
- [x] **PR #222 admin-merged to main** as squashed commit `ad73bd0` (6 files, +140/-41). `prodHotFix` branch deleted (local + remote).
- [x] **Deploy guide + findings + fix plan + patch notes** all written to `April/April23Updates/production/` (repo) + mirrored to `fynlaBrain/April/April23Updates/production/` (vault).
- [x] **User-facing patch notes** written at `April/April23Updates/subscribePatch.md` — ready for you to publish (blog / email / in-app). Also mirrored to vault.
- [x] **Tech debt audit** on changed files — 0 critical, 0 warnings, 3 suggestions (minor). Report at `tech-debt-report.md` + mirrored to vault.
- [x] **Vault sync done** — April Index updated with April 23 session summary, `Git History/Apr2026/Apr23.md` written, all deploy docs + findings + patch notes mirrored.

### Outstanding from this session

- [ ] **Publish `subscribePatch.md`** wherever you communicate updates to users (blog, email, in-app notification). The markdown is plain-English and ready to post as-is.
- [ ] **Delete the prod test user** when you're finished with it: `bugrepro_expired_2026_04_23@fynla.org` (currently `status=expired` + in grace period, left in place per your earlier instruction). Tinker snippet is in `deploy-fix-2026-04-23.md` §"Tear down test user".
- [ ] **Optional — address the 3 tech-debt suggestions** (all low priority, none block anything): (S1) rename module-level `isEligibleForStudentPlan` helper in `PlanSelectionModal.vue` to avoid shadowing the computed of the same name; (S2) drop the semi-dead `discountCode: null` from the emit payload and clean up the downstream `discountParam` branch in `SubscriptionManagement.vue`; (S3) extract a `<BulletItem>` component in `PricingPage.vue` to stop duplicating the checkmark SVG (~18 occurrences).

### Context for Next Session

On `main`, clean working tree, in sync with `origin/main`. The subscription hotfix is fully merged and live. No rollback needed, no pending deployments from today's work.

The **session 63 tech-debt branch** (`feature/csj/tech-debt-session-63`) remains unmerged and is still the top priority carried forward — browser testing + PR to `dev` → `main` hasn't been done yet. See the "Outstanding — session 63 carryover" block below for the full state.

If next session is another fire-fighting day, today's test user (`bugrepro_expired_2026_04_23@fynla.org`) is still on production with `status=expired` + grace period — useful as a ready-made expired-trial account for any subscription-path investigation.

---

## Outstanding — session 63 carryover (tech debt remediation branch)

Branch `feature/csj/tech-debt-session-63` (3 commits, +729/-2,160 net, 84 files) is pushed to origin and **ready for PR**. All work is isolated on the feature branch — `main` is untouched. PR gate blockers:

- [ ] **Browser-test the feature branch end-to-end** before opening PR to dev. Per `April/April18Updates/handover-tech-debt.md` §4a, 8 flows must be verified: Estate/IHT dashboard, Investment dashboard (holdings/fees/tax/rebalance), Protection dashboard, Expenditure form (penny-level totals), Estate CRUD (asset/liability/gift/LPA/trust — Vuex actions removed, components call service directly), Net worth dashboard (NetWorthAnalyzer patched), Savings dashboard (renamed component), Investment detail (renamed components).
- [ ] **PR `feature/csj/tech-debt-session-63` → `dev`** — follow feature → dev → main workflow per CLAUDE.md. Do NOT PR straight to main. CODEOWNERS requires @Stoff73 review.
- [ ] **After dev green, PR `dev` → `main` + deploy** — standard two-environment flow.

### Why this matters

The tech-debt branch closes **7 critical items** from the full codebase audit: 70 float→decimal:2 casts across 12 models, 17 dead API methods removed, 54 orphaned Vuex actions removed (store size −31%), 2 dead Vue components deleted, 5 single-word components renamed, strict_types added to 38 files, 6 generic exception throws converted to `FinancialCalculationException` factories, architecture test added to prevent regression. Sitting unmerged, none of it is benefitting production.

---

## Outstanding — other carryovers

- [ ] **NPM `--force` fix** — schedule a 2-4h window for vite 8 + @capacitor/cli 8 major upgrades with full PWA + iOS + web regression. 6 high-severity vulnerabilities remain until this is done. Carried from session 63.
- [ ] **Test Fyn chat fixes on dev (csjones.co/fynla)** — deployed in session 58 but not browser-tested. Carried from session 58.
- [ ] **Re-enable branch protection on `dev`** — carried from session 57.
- [ ] **Add `Current State/Insights.md`** to the vault — carried from session 62.
- [ ] **`AutoRiskCalculatorTest` pre-existing failure** — `risk_level` enum truncation. Pre-existing since 16 April. Surfaces in every full-suite run but not blocking.

---

## Outstanding — long-running tech debt (session 63 deferred, still valid)

- [ ] **28 Vue god components** (>800 lines) — prioritise `Admin/TaxSettings.vue` (3,068 lines) and `UserProfile/ExpenditureForm.vue` (2,574 lines). Multi-week.
- [ ] **13 backend god files** — decompose `SavingsActionDefinitionService.php` (3,686 lines), `RetirementActionDefinitionService.php` (2,701), `ProtectionActionDefinitionService.php` (2,349), `RetirementIncomeService.php` (2,292), `IHTCalculationService.php` (1,641).
- [ ] **54 controllers using inline `$request->validate()`** — convert to Form Request classes (~60-80h total). Top 10 first: Admin, Payment, Retirement, Auth, UserProfile, Investment, Property, TaxSettings, Onboarding, Recommendations.

---

## Deploy Status

- **fynla.org (production)** — subscription hotfix R1–R5 live (commit `ad73bd0` on main). Test user `bugrepro_expired_2026_04_23@fynla.org` still on prod for follow-up testing.
- **csjones.co/fynla (dev)** — deployed state unknown; last known state was the `onboardingFyn` branch from earlier April work. If you need dev for another test, verify which branch is currently deployed before building (`feedback_dev_server_is_separate.md`).
- **Tech debt branch (session 63)** — not deployed anywhere; sitting on `feature/csj/tech-debt-session-63` awaiting browser tests + PR.
