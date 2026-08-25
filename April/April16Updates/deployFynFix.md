# Deploy Guide — Fyn Chat Fix — DEPLOYED TO DEV, IN TESTING

**Date:** 16 April 2026
**Status:** DEPLOYED to dev (csjones.co/fynla) — in testing
**Branch:** `onboardingFyn` (includes `feature/csj/fynChatFix` merge)
**Target:** csjones.co/fynla (dev)
**Build script:** `./deploy/csjones-fynla/build.sh`

---

## What Changed

Fyn chat was leaking internal tool call metadata, raw route paths, and navigating to wrong pages.

### Fixes

1. **Tool metadata leak** — removed `buildToolCallContext()` from conversation history. Added backend sanitiser + frontend stripping so `- get_module_analysis: module: estate` lines never appear.
2. **Raw route paths** — added compliance rule forbidding paths in responses. Frontend converts any leaked paths to clickable links with human labels (e.g. `/estate` becomes "Estate Planning").
3. **Wrong navigation** — `SAVINGS_ACCOUNTS` and `SAVINGS_DEBT` queries no longer require `get_module_analysis(savings)` (which blocked on missing expenditure). Now uses `list_records` + `get_tax_information(savings_config)` — both ungated.
4. **Auto-navigate on blocked tools** — when a prerequisite gate blocks a tool, the suggested route is auto-navigated instead of relying on the model to call `navigate_to_page`.
5. **Tool descriptions clarified** — `list_records` mentions balances/rates, `get_module_analysis` says analysis-only, `get_tax_information` mentions Personal Savings Allowance.

---

## Files to Upload

### Frontend (built assets)

```
public/build/    ← entire directory (built via ./deploy/csjones-fynla/build.sh)
```

### Backend (6 PHP files)

```
app/Constants/QuerySchemas.php
app/Services/AI/Prompts/ComplianceRules.php
app/Services/AI/StructuredResponseValidator.php
app/Services/AI/XaiToolDefinitions.php
app/Traits/HasAiChat.php
```

### Frontend source (already in build, listed for reference)

```
resources/js/components/Shared/AiMessageContent.vue
```

---

## Upload Target

```
~/www/csjones.co/fynla-app/
```

Upload PHP files to their matching paths under `fynla-app/`. Upload `public/build/` to `fynla-app/public/build/`.

---

## SSH Commands (post-upload)

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

No migrations needed for this change.

---

## Test Plan

1. Log in to https://csjones.co/fynla
2. Open Fyn chat
3. Ask: "How much interest will I earn on my savings this year? Is it within my Personal Savings Allowance?"
   - Should NOT mention expenditure
   - Should NOT navigate to expenditure page
   - Should NOT show raw paths like `/valuable-info?section=expenditure`
   - Should NOT show tool metadata like `- get_module_analysis: ...`
4. Ask: "Do I have a will?"
   - Should NOT show `- get_module_analysis: module: estate` at end
   - If path appears, should be a clickable link labelled "Estate Planning"
5. Load a previous conversation from history — old messages should show paths as clickable links, not raw text
