# Deploy Guide — Bug Fixes 7 April 2026

**Branch**: `main` (merged from `bugs` via PR #192)
**Commit range**: `6b711a4..e013bac`

---

## 1. Build locally

```bash
./deploy/fynla-org/build.sh
```

---

## 2. Upload PHP files to production

Upload these files via SiteGround File Manager to `~/www/fynla.org/public_html/`:

### Backend — Bug Fixes

| File | Change |
|------|--------|
| `app/Services/UKTaxCalculator.php` | PA taper fix for income > £125,140 |
| `app/Http/Controllers/Api/PropertyController.php` | Soft-delete cascade to mortgages |
| `app/Http/Controllers/Api/InvestmentController.php` | Delete cascade to holdings + error surfacing |
| `app/Agents/CoordinatingAgent.php` | Mortgage tool, joint ownership in tool results |
| `app/Services/AI/XaiToolDefinitions.php` | Added `mortgage` to list_records enum |

### Backend — Fyn AI Improvements

| File | Change |
|------|--------|
| `app/Services/AI/SystemPromptBuilder.php` | Joint ownership labels with co-owner names + share values, family in Layer 4 |
| `app/Services/AI/Prompts/ComplianceRules.php` | Dynamic tax year (was hardcoded "2025/26") |
| `app/Services/AI/Prompts/FcaProcessInstructions.php` | Trimmed duplicate instructions |
| `app/Services/AI/Prompts/QueryKnowledge.php` | Removed redundant caveat append |
| `app/Constants/FinancialPlanningKnowledge.php` | Removed duplicate KNOWLEDGE_CAVEAT + trimmed AFFORDABILITY_RULES |
| `app/Services/AI/XaiClient.php` | xAI prompt caching via x-grok-conv-id header |
| `app/Traits/HasAiChat.php` | Temperature 0.7, cached token logging, legacy code removed |
| `app/Traits/HasAiGuardrails.php` | Token limits: trial/family tiers, increased all limits |

### Frontend

| File | Change |
|------|--------|
| `resources/js/components/NetWorth/InvestmentList.vue` | Joint badge only shows when ownership < 100% |
| `resources/js/components/NetWorth/InvestmentProjections.vue` | Delete error surfaced to user |

### Seeders (run on server after upload)

| File | Change |
|------|--------|
| `database/seeders/PreviewUserSeeder.php` | Income field mapping + Alex Chen dividends |
| `resources/js/data/personas/entrepreneur.json` | Alex Chen dividend_income field |

---

## 3. Upload built frontend

Upload `public/build/` directory to `~/www/fynla.org/public_html/public/build/`

---

## 4. SSH and clear caches + reseed

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
php artisan db:seed
```

---

## 5. Post-deploy verification

### PA Taper (Critical)

1. Log in as Alex Chen preview persona (income £240,000)
2. Go to Income page
3. Verify: NO "Personal Allowance: £12,570 @ 0%" row in tax bands
4. Verify: "Your Allowances" shows "Personal Allowance: £0 (reduced from £12,570)"
5. Repeat for David Mitchell (income £159,290)

### Fyn AI

1. Log in as chris@fynla.org (or any real user with data)
2. Open Fyn chat
3. Ask: "What tax year are you using?" — should say **2026/27**
4. Ask: "What mortgage rate am I paying?" — should return actual rates
5. Ask: "Which properties are jointly owned?" — should name co-owner, show split, user's share values

### Delete Flows

1. Add a test property with mortgage, then delete it — mortgage should also disappear
2. Add a test investment, then delete it — should succeed, no silent failure

---

## Summary of what's fixed

| Bug | Status |
|-----|--------|
| PA taper (£5,656 tax understatement) | Fixed |
| Property delete orphans mortgages | Fixed |
| Investment delete fails silently | Fixed |
| Fyn can't retrieve mortgage rates | Fixed |
| Fyn says "2025/26" instead of "2026/27" | Fixed |
| Joint badge on 100% owned accounts | Fixed |
| Fyn doesn't know about joint ownership | Fixed — shows co-owner, split, share values |
| Alex Chen missing dividends | Fixed |
| Fyn AI prompt redundancies | Cleaned — ~440 tokens saved per request |
| xAI prompt caching | Enabled — 75% discount on cached tokens |
| Token limits too low | Increased across all tiers |
