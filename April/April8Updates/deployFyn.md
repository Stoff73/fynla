# Deploy Guide — Fyn Bug Fixes (fynWork branch)

**Date:** 8 April 2026
**Branch:** `fynWork` (6 commits, 12 files changed)
**Type:** Frontend + Backend — build `public/build/` + upload PHP files
**IMPORTANT:** Production CoordinatingAgent.php was partially patched via SSH — upload the full local file to replace it cleanly.

---

## What Changed

### Fyn AI Data Gaps (CoordinatingAgent.php)
- **FYN-01 Mortgage:** Fixed `lender` → `lender_name`, added `rate_type`, `rate_fix_end_date`, `start_date`, `maturity_date`, `original_loan_amount`
- **FYN-02 Life Insurance:** Added `premium_frequency`, `policy_start_date`, `policy_end_date`, `policy_term_years`, `in_trust`, `is_mortgage_protection`, `joint_life`, `ownership_type`
- **FYN-02 Critical Illness:** Added `policy_type`, `premium`, `premium_frequency`, `policy_start_date`, `policy_term_years`, `ownership_type`
- **FYN-02 Income Protection:** Added `benefit_frequency`, `premium`, `premium_frequency`, `deferred_period_weeks`, `policy_start_date`, `ownership_type`
- **Estate Liabilities:** Added `interest_rate`, `monthly_payment`, `maturity_date`, `is_priority_debt`

### Fyn Empty Response Fix (HasAiChat.php)
- When model hit 5-tool limit, loop exited without generating text response
- Fix: make one final pass with tools disabled to force text generation
- Browser tested: ISA question now returns full response after 5 tool calls

### Fyn Context Improvements (SystemPromptBuilder.php)
- Added spouse monthly expenditure + combined household total to Layer 4
- Expanded liability detail in Layer 6 (interest rate, monthly payment, priority flag)
- Added tool efficiency guidance in Layer 8b (only call required + strictly necessary tools)

### FYN-05 Debug Context Leak (StructuredResponseValidator.php)
- Added sanitisation for `[System:]`, `[Debug:]`, `[Internal:]` blocks

### BUG-JS-01 Investments Page Error (investmentService.js)
- Added missing `getPortfolioProjections()` method — was called by Vuex store but never defined

### BUG-SCENARIO-01 Completeness 0% (lifeStage.js)
- Falls back to binary step completion when field-level completeness data not loaded

### BUG-COMMITMENTS-01 Wrong Total (UserProfileService.php)
- Moved total calculation after lump sums are computed (was excluding them)

### Toast Notification System (new files)
- `ToastNotification.vue` — global toast component (spring success / raspberry error)
- `toast.js` — Vuex module with `show`, `success`, `error` actions
- Mounted in AppLayout, dispatched via `store.dispatch('toast/success', 'message')`

### UI Fixes
- Fyn placeholder: "Ask Fyn anything..." → "Ask Fyn..."

---

## Files Changed

### PHP (upload to server)
```
app/Agents/CoordinatingAgent.php
app/Services/AI/StructuredResponseValidator.php
app/Services/AI/SystemPromptBuilder.php
app/Services/UserProfile/UserProfileService.php
app/Traits/HasAiChat.php
```

### Frontend (build + upload public/build/)
```
resources/js/components/Shared/AiChatPanel.vue          (modified)
resources/js/components/Shared/ToastNotification.vue     (new)
resources/js/layouts/AppLayout.vue                       (modified)
resources/js/services/investmentService.js               (modified)
resources/js/store/index.js                              (modified)
resources/js/store/modules/lifeStage.js                  (modified)
resources/js/store/modules/toast.js                      (new)
```

---

## Deploy Steps

### 1. Merge branch to main
```bash
git checkout main
git merge fynWork
git push origin main
```

### 2. Build frontend
```bash
./deploy/fynla-org/build.sh
```

### 3. Upload PHP files to production
Upload via SiteGround File Manager to `~/www/fynla.org/public_html/`:
```
app/Agents/CoordinatingAgent.php
app/Services/AI/StructuredResponseValidator.php
app/Services/AI/SystemPromptBuilder.php
app/Services/UserProfile/UserProfileService.php
app/Traits/HasAiChat.php
```

### 4. Upload frontend build
Upload `public/build/` directory to:
```
~/www/fynla.org/public_html/public/build/
```

### 5. Clear caches (SSH)
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Browser Test Results (chris@fynla.org, local dev)

| Test | Tool Calls | Result |
|------|-----------|--------|
| ISA allowance used | 5 → text | £5,460 used, £14,540 remaining of £20,000 |
| Mortgage details | 2 | Lender (Halifax), rate type (fixed), rate (4.5%), monthly (£1,100), term (300mo) |
| Protection policies | 2 | Aviva £500K £35/mo in trust, Royal London £200K £85/mo in trust |
| Liabilities / debts | 5 → text | £200K at 4.5%, £3.5K at 0%, advised pay highest rate first |
| Investments page JS | 0 errors | getPortfolioProjections working, no console errors |
| Fyn placeholder | — | Shows "Ask Fyn..." |

## No Migration or Seeding Required
- No database changes
- No new routes
- No composer changes
