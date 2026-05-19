---
tags:
  - may-2026
  - deployment
  - fix/persona-split-review-fixes
date: 2026-05-01
---

Back to [[May Index]]

# deployFynFix.md — csjones.co/fynla deploy of `fix/persona-split-review-fixes`

**Target:** dev / staging only — `https://csjones.co/fynla`
**Source branch:** `fix/persona-split-review-fixes` @ `7cfef90`
**Currently deployed on csjones:** `feature/fyn-persona-split` @ `23f68ec`
**Net surface:** 18 commits, 33 PHP files, 10 Vue/JS files, 3 new migrations, 1 file rename, 1 config edit.

> **Do NOT deploy this to fynla.org.** This branch is dev-only. The `main`/production cycle starts after PR #239 merges into `feature/fyn-persona-split` AND that merges into `dev` AND `dev` merges into `main` — none of which has happened.

---

## 1. Pre-deploy

### 1a. Merge PR #239 → `feature/fyn-persona-split`

Review/merge `fix/persona-split-review-fixes` → `feature/fyn-persona-split` via PR #239 first. The csjones build script reads from the local working tree, so the branch must be checked out and clean before building.

```bash
gh pr view 239 --json state,mergeable,reviewDecision
gh pr merge 239 --merge --admin   # only when ready
git checkout feature/fyn-persona-split
git pull
```

### 1b. Verify `.env` on csjones has both keys set

Commit `33ff535` changed `ai_audit_hmac_key`'s ultimate fallback from a literal string to `''` so `AuditChainService` throws when nothing is configured. The deploy will break on first AI-audit write if BOTH `AI_AUDIT_HMAC_KEY` and `APP_KEY` are empty in `~/www/csjones.co/public_html/fynla/.env`.

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co \
  'grep -E "^(AI_AUDIT_HMAC_KEY|APP_KEY)=" ~/www/csjones.co/public_html/fynla/.env'
```

At least one of them must be a non-empty value. If both are empty, set `AI_AUDIT_HMAC_KEY=$(php -r "echo bin2hex(random_bytes(32));")` before deploying.

### 1c. Local build (NEVER `npx vite build` directly)

```bash
./deploy/csjones-fynla/build.sh
```

Wait for it to finish. Do NOT proceed if the build emits errors.

> **SPA rebuild warning** — this regenerates `public/build/`. If you upload the new `build/` over an existing one, the previous hashed asset bundles disappear and any browser session still holding old chunk URLs gets MIME-type errors until refresh. Use the merge-on-upload pattern (see §3).

---

## 2. Files to upload

### 2a. PHP backend (33 modified)

```
app/Agents/CoordinatingAgent.php
app/Console/Commands/EvalPurgeCommand.php
app/Console/Commands/EvalRecordCommand.php
app/Console/Commands/EvalShowCommand.php
app/Constants/QuerySchemas.php
app/Constants/TaxDefaults.php
app/Http/Controllers/Api/Admin/EvalRecordingController.php
app/Http/Controllers/Api/AiChatController.php
app/Http/Controllers/Api/EvalAuthController.php
app/Http/Requests/AI/SendAiChatMessageRequest.php          (NEW)
app/Jobs/AiAuditRetentionJob.php
app/Listeners/Eval/EvalTraceListener.php
app/Models/EvalRecordingSession.php
app/Models/PensionInputHistory.php
app/Services/AI/AdviceFyn.php
app/Services/AI/AuditChainService.php
app/Services/Eval/EvalBypassGate.php
app/Services/Investment/Recommendation/DataReadinessService.php
app/Services/Investment/Recommendation/RecommendationOutputFormatter.php
app/Services/Onboarding/OnboardingStateMachine.php
app/Services/Onboarding/SpouseLinkingService.php
app/Services/Protection/ProtectionDataReadinessService.php
app/Services/Retirement/RetirementDataReadinessService.php
app/Services/Savings/SavingsDataReadinessService.php
app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php
app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php
app/Services/Tax/Strategies/IncomeBandStrategy.php
app/Services/Tax/Strategies/JointSavingsStrategy.php
app/Services/Tax/Strategies/LifecycleStrategy.php
app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php
app/Services/Tax/Strategies/TaperedAnnualAllowanceStrategy.php
app/Services/Tax/TaxStrategyMath.php
app/Traits/HasAiChat.php
```

### 2b. PHP rename — upload NEW + DELETE OLD

```
NEW:    app/Support/XaiFunctionCallLeakStripper.php
DELETE: app/Support/AssistantContentSanitiser.php
```

The old class file must be removed on the server, otherwise composer's classmap may still resolve it and the renamed call sites in `app/Traits/HasAiChat.php` will mis-load.

### 2c. Config

```
config/app.php
```

Edit only — the `ai_audit_hmac_key` fallback. See §1b for the env-var precondition.

### 2d. Frontend (10 Vue/JS files — included in `public/build/` after the build script runs; do NOT upload .vue / .js source)

Source files for reference only:

```
resources/js/components/Admin/EvalRecordings.vue
resources/js/components/Admin/eval/ChecklistItem.vue
resources/js/components/Admin/eval/EvalDataModal.vue
resources/js/components/Admin/eval/RunPanel.vue
resources/js/components/Fyn/FynOnboardingChat.vue
resources/js/components/Public/StaticFynChat.vue
resources/js/components/Shared/AiChatPanel.vue
resources/js/components/Shared/AiMessageContent.vue
resources/js/store/modules/aiChat.js
resources/js/views/Public/SaveTaxCampaignPage.vue
```

These compile into the `public/build/assets/*` bundle written by the `csjones-fynla/build.sh` script. Upload the entire `public/build/` directory (see §3).

### 2e. Migrations (3 new)

```
database/migrations/2026_05_06_000001_drop_is_eval_user_from_users.php
database/migrations/2026_05_06_000002_rename_eval_user_id_to_preview_user_id.php
database/migrations/2026_05_06_000003_add_operation_created_at_index_to_ai_audit_events.php
```

Order matters — the dropper runs first, then the rename, then the index. `php artisan migrate` will execute in filename order, which matches.

> **Migration `000002`** renames `eval_user_id` → `preview_user_id` on `eval_recording_sessions`. If anything outside this branch (Grafana queries, manual SQL scripts, ad-hoc reports) reads the old column name, it will break. None known in repo, but worth a quick mental check.

---

## 3. Upload to csjones

Via SiteGround File Manager OR rsync. Server path: `~/www/csjones.co/public_html/fynla/`.

### 3a. Backend PHP, migrations, config

Upload preserving directory structure. No build artefacts here — straight file copy.

### 3b. SPA bundle (merge-on-upload pattern)

```bash
# On the SERVER, before uploading new build/:
cd ~/www/csjones.co/public_html/fynla/public
mv build build.old
# Upload the LOCAL public/build/ to ~/www/csjones.co/public_html/fynla/public/build/
# Then merge old assets back without overwriting new ones:
cp -rn build.old/. build/
# Verify, then clean up:
ls build/assets/ | wc -l   # sanity check — should include both old and new hashed chunks
rm -rf build.old
```

This keeps existing browser sessions functional while serving the new bundle for fresh page loads.

### 3c. Delete the renamed PHP file

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co \
  'rm -f ~/www/csjones.co/public_html/fynla/app/Support/AssistantContentSanitiser.php'
```

---

## 4. Server-side finalisation

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/public_html/fynla
php artisan migrate --force
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
composer dump-autoload --no-dev --optimize    # needed for the Sanitiser → Stripper rename
php artisan optimize
exit
```

`composer dump-autoload` is required because the file rename in §2b changes the classmap. Skipping it can leave PHP looking for the deleted class.

---

## 5. Smoke tests (do not skip — every fix area below ships behavioural changes)

Smoke against `https://csjones.co/fynla`. Per `feedback_smoke_must_verify_amounts.md`, verify £ amounts against the persona's actual data, not just HTTP 200.

### 5a. Tax strategy — M11 income basis (the work in this latest sweep)

Log in as a persona that has **employment + dividend or interest income**:

1. Visit `/tax-strategy`.
2. **Marriage Allowance** card — should NOT appear if `(employment + dividends + savings interest) > £50,270`, even when employment alone is under £50,270 and `marriage_allowance_eligible = true`.
3. **Joint Savings** card — should NOT appear if total taxable income > £125,140 (additional rate, PSA = £0).
4. **Cross-Spouse GIA Rebalance** card — for dual-earner couples, the dividend rate-delta should reflect total-income band, not employment-only band.
5. **ISA top-up vs PSA**, **Tapered AA**, **Pension AA Carry-Forward** cards — `marginal_rate` field (visible in admin recommendation drill-down) should reflect total-income band.

### 5b. Eval / Admin (multiple commits in this branch)

1. Log in as `chris@fynla.org`, visit `/admin/eval-recordings`.
2. Create a recording session, run a scenario through it, verify the `result_path` field captures `success`/`error` from the actual tool result string (P0.3 fix).
3. Verify no `is_eval_user` references in any admin UI (P0.1 — column dropped). Any 500 here means the migration didn't run.

### 5c. AI chat / Fyn UI (M24 + AdviceFyn fixes)

1. Open Fyn chat panel anywhere — confirm **no SVG icons** in chat messages, quick-reply bubbles, header chrome, or message buttons. Plain text only (Rule #14).
2. Ask Advice Fyn a write-intent question (e.g. "add my pension"). It must NOT directly create a record — it should hand off to onboarding capture via `delegate_to_capture`.
3. Watch network tab during a stream — no `handoff` SSE events should leak to the frontend (P0.9 — only `delegate_to_capture` handoffs are passed through, all other types dropped).

### 5d. Onboarding (P0.7, P0.8)

1. New-user signup flow — confirm `FynOnboardingChat.vue` renders without console errors (Vue-3 `$listeners` removal, P0.7).
2. Try linking a spouse with mixed-case email (`Spouse@Example.COM`) — must resolve to the same row regardless of case (P0.8).

### 5e. Readiness gauges (M12, M13)

Visit `/investment`, `/protection`, `/retirement`, `/savings`. Each module's "Data readiness" card should show a `completeness_percent` between 0–100 with consistent semantics across the four. Investment readiness must not throw on a brand-new user with zero accounts (M13 — `loadMissing` guards).

---

## 6. Rollback

If smoke fails:

1. Revert the merge commit on `feature/fyn-persona-split`:
   ```bash
   git checkout feature/fyn-persona-split
   git revert -m 1 <merge-commit-sha>
   git push
   ```
2. Re-build and re-upload `public/build/` per §3.
3. **Roll back migrations** (THE THREE 2026_05_06_* migrations are destructive — `000001` drops `is_eval_user`, `000002` renames a column). Do not just re-run `migrate:rollback` blindly:
   ```bash
   ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
   cd ~/www/csjones.co/public_html/fynla
   php artisan migrate:rollback --step=3 --force   # rolls back the 3 new migrations
   ```
   The `down()` methods restore the column structure but NOT any data that lived in `is_eval_user` or under the old `eval_user_id` name. The branch's previous state on csjones used `eval_recording_sessions` as the source of truth so the data isn't critical, but verify with `php artisan tinker` before declaring rollback complete.

---

## 7. Notes for whoever runs this

- Per `feedback_no_deploy_recommendations.md`, the timing of THIS deploy is CSJ's call — this guide doesn't pretend the work is "ready to ship".
- The fix branch contains **2 known-pre-existing failures** unrelated to this work that were not introduced by review-fixes (see CSJTODO §"NOT Done" — the `EvalAuthControllerTest > reset endpoint runs preview:reset` red and the M11 income-basis defer in `AssetShifting`/`CrossSpouse`/`JointSavings` that's now closed by commits `193bb4c` and `7cfef90`).
- Production (`fynla.org`) deploy is a separate document. Do not re-use this guide — the build script, paths, and `.env` differ.
