# Full Deployment Guide — 19 March 2026 (All Changes)

**PRs merged:** #140, #141, #142, #143, #144, #145 + direct commits
**Branch:** `main`

---

## 1. Build (required — frontend files changed)

```bash
./deploy/fynla-org/build.sh
```

A rebuild is **required** — 12 frontend files changed (Vue components, store modules, new utility file).

---

## 2. Run Migrations

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
```

Pending migrations:
- `2026_03_18_100000` — SoftDeletes on models
- `2026_03_18_100001` — Student fields + unique constraints
- `2026_03_18_100002` — Database indexes
- `2026_03_19_100000` — Income definition fields (is_registered_blind, charitable_donations, is_gift_aid)

---

## 3. Upload PHP Files

```
app/Agents/CoordinatingAgent.php
app/Agents/EstateAgent.php
app/Agents/TaxOptimisationAgent.php
app/Http/Controllers/Api/AdvisorController.php
app/Http/Controllers/Api/GoalsController.php
app/Http/Controllers/Api/Investment/AssetLocationController.php
app/Http/Controllers/Api/LifeStageController.php
app/Http/Controllers/Api/UserProfileController.php
app/Http/Middleware/PreviewWriteInterceptor.php
app/Http/Requests/StoreInvestmentAccountRequest.php
app/Http/Requests/UpdateInvestmentAccountRequest.php
app/Http/Requests/UpdatePersonalInfoRequest.php
app/Services/AI/AiToolDefinitions.php
app/Services/Dashboard/DashboardAggregator.php
app/Services/Estate/TrustService.php
app/Services/Investment/AssetLocation/AssetLocationOptimizer.php
app/Services/PrerequisiteGateService.php
app/Services/Tax/TaxOptimisationService.php
app/Services/Trust/IHTPeriodicChargeCalculator.php
app/Traits/HasAiChat.php
routes/api.php
```

**21 PHP files** to upload to `~/www/fynla.org/public_html/`

---

## 4. Upload Frontend Build

Upload entire `public/build/` directory to `~/www/fynla.org/public_html/public/build/`

Frontend files changed (included in build):
```
resources/js/components/Admin/TaxSettings.vue
resources/js/components/Navbar.vue
resources/js/components/Shared/AiChatPanel.vue
resources/js/components/UserProfile/LetterToSpouse.vue
resources/js/layouts/AppLayout.vue
resources/js/store/modules/aiChat.js
resources/js/store/modules/completeness.js
resources/js/store/modules/investment.js
resources/js/utils/chatNavigationRouter.js          (new file)
resources/js/views/Dashboard.vue
resources/js/views/Investment/AccountPerformancePanel.vue
resources/js/views/Investment/PortfolioStrategyPanel.vue
```

---

## 5. Post-Deployment

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
php artisan db:seed
```

---

## 6. Verification Checklist

| # | Test | Expected |
|---|------|----------|
| 1 | Preview persona → Risk Profile page | Shows level number (3), 9 factor cards, asset allocation |
| 2 | Admin → Tax Settings → Income Tax tab | Rates show 20%, 40%, 45% (not 0.2%, 0.4%, 0.45%) |
| 3 | Admin → Tax Settings → Inheritance Tax tab | PET taper relief shows year ranges, no NaN |
| 4 | Admin → Tax Settings → all 10 tabs render | No missing sections, no NaN values |
| 5 | AI chat → type "show me my goals" | Instant navigation to /goals (no loading spinner) |
| 6 | AI chat header | Shows "Fyn" not "Fynla Assistant" |
| 7 | Info guide button | In top navbar (raspberry circle with badge), not floating bottom-right |
| 8 | Dashboard → user with investments, no knowledge_level | Violet nudge banner with 3 buttons |
| 9 | Click knowledge level button | Banner disappears, doesn't return |
| 10 | Expenditure tab → simple entry user | Current £1,500, Retired £1,275 (85%) |
| 11 | Letter to Spouse page | No console errors (properties.reduce fix) |
| 12 | Investment score displays | Show descriptive text not numerical scores |

---

## Summary of Changes by PR

| PR | Branch | Description |
|----|--------|-------------|
| #140 | logicFix | Simple expenditure fix + LetterToSpouse + education_level validation |
| #141 | taxConfigFix | Admin tax settings: NaN fixes, 568/568 config, agent hardcoded values, AI tax tool 18 topics |
| #142 | aiTools | AI CRUD tools (create/update/delete/profile) + zero-token navigation |
| #143 | uiFix | Info guide to navbar + chat "Fyn" rename + session lifecycle |
| #144 | dataReadiness | PrerequisiteGateService refactor + completeness endpoint + knowledge nudge |
| #145 | reviewFix | Code review remediation: 3 critical, 5 high, 5 medium fixes |
| — | main | Risk profile PreviewWriteInterceptor fix |
