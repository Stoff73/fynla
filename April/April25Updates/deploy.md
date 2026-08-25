# Deploy Notes — Session 77 (25 April 2026)

**Branch:** `feature/fyn-persona-split`
**Commits in this deploy:** `1d61a47` (S0.12) → `05e7525` (S0.13) → `04a99fa` (S0.14)
**Target environments:** dev (`csjones.co/fynla`) first, production (`fynla.org`) only after dev smoke-test.
**Status:** Pushed to origin. Not yet deployed.

> ⚠️ This is sprint-feature-branch work. The work flows `feature/fyn-persona-split → dev → main` per memory `feedback_main_via_dev_only.md`. Do NOT deploy direct to production; merge to `dev` first, deploy to dev, smoke-test, then PR `dev → main`.

---

## What changed

| Area | Files | Notes |
|------|-------|-------|
| Audit chain (S0.12) | `app/Models/AiAuditEvent.php`, `app/Services/AI/AuditChainService.php`, `app/Console/Commands/AiAuditVerifyChainCommand.php`, `app/Jobs/AiAuditRetentionJob.php`, `database/migrations/2026_04_25_000013_create_ai_audit_events_table.php` | New table + service + artisan command + weekly retention job |
| Audit hooks | `app/Agents/CoordinatingAgent.php`, `app/Traits/HasAiChat.php`, `app/Services/Onboarding/OnboardingChatDirector.php`, `app/Console/Kernel.php` | `executeTool` signature gained optional `?int $conversationId`; chain append at dispatched/persisted/failed; weekly schedule entries |
| Admin chain UI | `app/Http/Controllers/Api/AiAuditController.php`, `routes/api.php`, `resources/js/components/Admin/AiAudit.vue`, `resources/js/services/aiAuditService.js` | Two new endpoints + chain-view tab + integrity banner |
| HMAC config | `config/app.php`, `.env.example` | New `app.ai_audit_hmac_key` config key (falls back to `APP_KEY`) + `AI_AUDIT_HMAC_KEY` env var |
| CoreIdentity rewrite (S0.13) | `app/Services/AI/Prompts/CoreIdentity.php`, `app/Services/AI/AdvicePromptBuilder.php` | Guidance-only framing + FCA signposting layer gated on `QuerySchemas::isAdviceType` |
| Out-of-remit refusal (S0.14) | `app/Constants/QuerySchemas.php`, `app/Services/AI/QueryClassifier.php`, `app/Services/AI/AdviceFyn.php` | New `OUT_OF_REMIT` constant + classifier patterns + `AdviceFyn::handle` early-return |
| Tests | `tests/Architecture/CoreIdentityFramingTest.php`, `tests/Feature/Audit/{HashChain,HmacSigning,ChainTamperDetection,RetentionPseudonymisation}Test.php`, `tests/Feature/Fyn/{FcaSignposting,OutOfRemit}Test.php` | 22 new tests, no production code |

---

## Pre-deploy actions (local)

```bash
# 1. Confirm green tests
./vendor/bin/pest tests/Feature/AI tests/Feature/Fyn tests/Feature/Onboarding tests/Feature/Audit tests/Architecture tests/Unit/Constants tests/Unit/Services/AI

# Expected: 495 passing, 1943 assertions
```

This branch is NOT on `dev` yet. Before deploying:
1. Open PR `feature/fyn-persona-split → dev` on GitHub.
2. Merge after CSJ approval (CODEOWNERS forces `@Stoff73` reviewer).
3. `git checkout dev && git pull` locally.

---

## Build (dev — csjones.co/fynla)

```bash
./deploy/csjones-fynla/build.sh
```

This rebuilds `public/build/` with `VITE_BASE_PATH=/fynla/build/` + `VITE_ROUTER_BASE=/fynla/`. **Do not use the production build script for dev.**

---

## Files to upload (dev — csjones.co/fynla)

Upload via SiteGround File Manager to `~/www/csjones.co/public_html/fynla/`:

### PHP — backend
- `app/Agents/CoordinatingAgent.php`
- `app/Console/Commands/AiAuditVerifyChainCommand.php`
- `app/Console/Kernel.php`
- `app/Constants/QuerySchemas.php`
- `app/Http/Controllers/Api/AiAuditController.php`
- `app/Jobs/AiAuditRetentionJob.php`
- `app/Models/AiAuditEvent.php` ← NEW
- `app/Services/AI/AdviceFyn.php`
- `app/Services/AI/AdvicePromptBuilder.php`
- `app/Services/AI/AuditChainService.php` ← NEW
- `app/Services/AI/Prompts/CoreIdentity.php`
- `app/Services/AI/QueryClassifier.php`
- `app/Services/Onboarding/OnboardingChatDirector.php`
- `app/Traits/HasAiChat.php`
- `config/app.php`
- `routes/api.php`

### Migration
- `database/migrations/2026_04_25_000013_create_ai_audit_events_table.php` ← NEW

### Frontend (after running build script)
- Whole `public/build/` directory (use the `cp -rn build.old/. build/` merge pattern per memory `feedback_warn_before_spa_rebuild.md` — keep old hashed assets so live tabs don't 404)

> Per memory `feedback_deploy_guide_completeness.md`: this list was generated from `git diff --name-only HEAD~3..HEAD` not memory.

---

## SSH steps (dev — csjones.co/fynla)

> Per memory `reference_csjones_ssh_access.md`: use `~/.ssh/fynlaDev` (passphrase). Verify `ssh-add -l` first.

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
```

> ⚠️ Per memory `reference_csjones_sibling_dir.md`: artisan runs from `~/www/csjones.co/fynla-app/`, NOT `~/www/csjones.co/public_html/fynla/`. Uploaded files live in `public_html/fynla/` but artisan/composer live in the sibling `fynla-app/` directory.

### 1. Set HMAC key in `.env` (one-time, before migrate)
```bash
# Edit ~/www/csjones.co/fynla-app/.env
# Add line (only if AI_AUDIT_HMAC_KEY is currently unset):
AI_AUDIT_HMAC_KEY=$(php -r 'echo bin2hex(random_bytes(32));')
```
The config key falls back to `APP_KEY` when unset, so this is technically optional for dev. Production MUST set its own.

### 2. Run migration + clear caches
```bash
php artisan migrate --force
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
```

### 3. Smoke-test — chain integrity
```bash
php artisan ai:audit:verify-chain
# Expected (empty chain): {"chain_valid":true,"tip_hash":"0000…0","row_count":0}
```

### 4. Browser smoke
Visit `https://csjones.co/fynla`:
- Login with a test persona.
- Open Fyn chat, send a benign financial question — should work as before.
- Send "I have a headache" — should get the canonical refusal "I'm able to help you with your finances. Medical advice is out of scope." with no tool calls.
- Send a recommendation-style question (e.g. "should I increase pension contributions?") — response should end with the FCA signposting line.
- Admin → AI Audit → Chain view tab — should show the rows from the smoke test, integrity banner should read "Chain valid · N rows".

---

## Production deploy (fynla.org) — only AFTER dev smoke-test

```bash
# 1. PR dev → main, merge after self-review
git checkout main && git pull

# 2. Build
./deploy/fynla-org/build.sh

# 3. Upload same files as above (production paths)
#    Target: ~/www/fynla.org/public_html/

# 4. SSH (production key)
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
```

### Production-only — set HMAC key
```bash
# Edit ~/www/fynla.org/public_html/.env (production)
# This MUST be a fresh, dedicated secret (do NOT reuse APP_KEY in prod):
AI_AUDIT_HMAC_KEY=$(php -r 'echo bin2hex(random_bytes(32));')
# Confirm it's set:
grep ^AI_AUDIT_HMAC_KEY .env
```

```bash
# 5. Migrate + clear caches
php artisan migrate --force
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize

# 6. Smoke
php artisan ai:audit:verify-chain
```

### Production smoke-test
- Login as `chris@fynla.org` (ask CSJ for the email verification code per CLAUDE.md).
- Same browser smoke as dev: financial question → normal response; medical/legal/general-knowledge → canonical refusal; recommendation-style → FCA signposting line.
- Admin → AI Audit → Chain view tab — rows present, integrity green.
- Monitor `storage/logs/laravel.log` for errors over the next 10-15 minutes.

---

## Known cross-branch hotspots

Files that changed in BOTH this branch and `dev`/`main` since divergence (run before merge to flag conflicts):

```bash
BASE=$(git merge-base main HEAD)
MAIN_FILES=$(git diff --name-only $BASE..origin/main -- '*.php' '*.vue' '*.js')
BRANCH_FILES=$(git diff --name-only $BASE..HEAD -- '*.php' '*.vue' '*.js')
comm -12 <(echo "$MAIN_FILES" | sort) <(echo "$BRANCH_FILES" | sort)
```

Most likely conflicts (changed across many sprints):
- `app/Agents/CoordinatingAgent.php`
- `app/Traits/HasAiChat.php`
- `app/Services/AI/AdvicePromptBuilder.php`
- `routes/api.php`
- `app/Console/Kernel.php`
- `resources/js/components/Admin/AiAudit.vue`

Per memory `feedback_merge_branch_conflicts.md`: cross-reference both branches' changed files BEFORE merging.

---

## Rollback

If a deploy goes wrong:

```bash
# 1. Roll back code via git on each server
ssh ...
cd ~/www/<env>/<path>
git checkout <previous-good-commit>

# 2. Roll back the migration (drops the table; chain history is lost)
php artisan migrate:rollback --step=1

# 3. Clear caches
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize
```

The retention job is scheduled but not yet fired. The verify-chain command is read-only — safe to keep around even on rollback. The audit table is forensic; if it's dropped via rollback, no user-visible feature breaks.

---

*Generated 2026-04-25 by session-end skill. Source: `git diff --name-only HEAD~3..HEAD`.*
