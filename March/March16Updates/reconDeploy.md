# Recon Fixes Deployment

**Changes since last commit (ec0505c)**
**Rebuild required:** YES — `AiChatPanel.vue` changed (quick reply chip removal)

---

## Step 1: Rebuild frontend locally

```bash
./deploy/fynla-org/build.sh
```

---

## Step 2: Upload files via SiteGround File Manager

All paths relative to `~/www/fynla.org/public_html/`.

### Overwrite these 4 files:

```
app/Services/PrerequisiteGateService.php
app/Services/AI/AiToolDefinitions.php
app/Traits/HasAiChat.php
routes/api.php
```

### Upload frontend build:

```
public/build/          (entire directory — overwrite)
```

---

## Step 3: Clear caches on server

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan optimize
php artisan db:seed
```

---

## What changed and why

| File | Change | Why |
|------|--------|-----|
| `PrerequisiteGateService.php` | Complete rewrite — all module gates now mirror exact blocking checks from each agent's DataReadinessService | Gates were inventing their own rules. Retirement falsely blocked users who had data. Investment didn't check risk_profile. Savings didn't check DOB or income. Expenditure missed ExpenditureProfile source. |
| | Protection: `date_of_birth`, `income`, `marital_status` | Matches ProtectionDataReadinessService |
| | Savings: `date_of_birth`, `income`, `expenditure` (3 sources) | Matches SavingsDataReadinessService |
| | Retirement: `date_of_birth`, `marital_status`, `risk_profile` | Matches RetirementDataReadinessService blocking + risk_profile per product requirement |
| | Investment: `date_of_birth`, `income`, `risk_profile`, `expenditure` (3 sources) | Matches InvestmentDataReadinessService |
| | Estate: `date_of_birth`, `marital_status`, `at_least_one_asset` | Matches EstateDataReadinessService (unchanged) |
| | Expenditure check now uses `hasExpenditure()` which checks ExpenditureProfile, User.monthly_expenditure, User.annual_expenditure | Matches ResolvesExpenditure trait fallback chain |
| | All income routes: `/valuable-info?section=income` | Was `/profile` — wrong page |
| | All expenditure routes: `/valuable-info?section=expenditure` | Was `/profile` — wrong page |
| | Risk profile routes: `/risk-profile` | New — not previously included |
| `AiToolDefinitions.php` | Valid routes expanded from 17 to 26 | Missing income, expenditure, will builder, LPA, plans, actions, what-if |
| `HasAiChat.php` | 1. Module context map expanded from 15 to 24 entries | AI had no context for income, expenditure, will builder, LPA pages |
| | 2. `buildUserProfile()` now resolves retirement age from 3 sources: `users.retirement_date`, `users.target_retirement_age`, `retirement_profiles.target_retirement_age` | AI could not reference user's retirement age — prompt only checked `retirement_date` |
| `routes/api.php` | Added `GET /api/internal/agent/recommendations` route | Python sidecar tool had no matching route |

---

## Step 4: Verify

- [ ] Ask Fyn "When can I retire?" — should reference user's actual retirement age (e.g. "age 70"), NOT ask what age they want
- [ ] Ask about expenditure — should navigate to `/valuable-info?section=expenditure`, NOT `/profile`
- [ ] Ask about income — should navigate to `/valuable-info?section=income`, NOT `/profile`
- [ ] Ask about investments without a risk profile — should be told to complete risk profile and navigate to `/risk-profile`
- [ ] Confirm no quick reply chips after assistant messages
- [ ] Check browser dev tools for console errors — should be none
