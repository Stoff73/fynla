# Deploy — 25 March 2026 Fixes

**Branch:** `grokAI`

## 1. WARN-002: Sessions API 500 on /api/auth/sessions

**Root cause:** Orphaned `user_sessions` rows referencing deleted `personal_access_tokens` caused failures in the `map()` callback.

| File | Change |
|------|--------|
| `app/Services/Auth/SessionService.php` | Added `->whereHas('token')` to filter out orphaned sessions |
| `app/Http/Controllers/Api/SessionController.php` | Wrapped `index()` in try-catch with `errorResponse()` |

## 2. WARN-003: Vue error on holistic-plan page

**Root cause:** Template accessed `plan.current_situation` without checking it existed.

| File | Change |
|------|--------|
| `resources/js/components/Plans/Holistic/HolisticPlanContent.vue` | Added `&& plan.current_situation` to v-if guards for protection, retirement, estate |

## 3. Investment AI Fill — 14 Account Types Tested & Fixed

Tested all 14 investment account types with Grok AI. Found and fixed 4 bugs:

### Bug fixes

| Bug | Fix | Files |
|-----|-----|-------|
| SAYE: `units_granted` required but AI doesn't always send it | Made optional for SAYE (frontend + backend) | `AccountForm.vue`, `StoreInvestmentAccountRequest.php` |
| SAYE: `grant_date` required but AI sends `scheme_start_date` | Auto-populate `grant_date` from `scheme_start_date` for SAYE | `AccountForm.vue`, `StoreInvestmentAccountRequest.php` |
| Private Co/Crowdfunding cards show "Valuation: £0" | Added `current_value` fallback in `getDisplayValue()` | `InvestmentList.vue` |
| Wine/art routed to investment instead of chattel | Updated tool descriptions to route correctly | `XaiToolDefinitions.php` |

### Test seed fix

| File | Change |
|------|--------|
| `database/seeders/TestUsersSeeder.php` | Added `trial_ends_at` + `Subscription` records so test users have full app access |

## All Files to Upload

### PHP

```
app/Services/Auth/SessionService.php
app/Http/Controllers/Api/SessionController.php
app/Services/AI/XaiToolDefinitions.php
app/Http/Requests/StoreInvestmentAccountRequest.php
```

### Frontend (rebuild required)

```
resources/js/components/Plans/Holistic/HolisticPlanContent.vue
resources/js/components/Investment/AccountForm.vue
resources/js/components/NetWorth/InvestmentList.vue
```

## Deploy Steps

1. Run `./deploy/fynla-org/build.sh` locally
2. Upload `public/build/` to server
3. Upload the 4 PHP files above
4. SSH and clear caches:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Tests

- 18/18 session tests passing
- 14/14 investment account types tested with Grok AI (see deployAI.md for full results)
- 83/83 total AI form fill scenarios passing across all modules
