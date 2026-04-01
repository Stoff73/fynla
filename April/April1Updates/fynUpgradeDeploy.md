# Fyn AI Phase 2 — Deploy Guide

**Date:** 1 April 2026
**Branch:** `fynImprovement` → merged to `main`
**Version:** v0.9.4 → v0.9.5
**Status:** DEPLOYING

---

## Pre-Deploy: Run Migrations on Server

Two new migrations must run BEFORE uploading PHP files:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
```

Expected output:
```
2026_04_01_150000_create_ai_advice_log_table ... DONE
2026_04_01_160000_add_system_prompt_to_ai_messages_table ... DONE
```

---

## Files Changed (from `git diff main..HEAD --name-only`)

### New PHP Files (upload to server)

| File | Purpose |
|------|---------|
| `app/Constants/QuerySchemas.php` | Query type definitions, KYC requirements, tool sequences, triggers |
| `app/Http/Controllers/Api/AiAuditController.php` | Admin AI audit API (3 endpoints) |
| `app/Models/AiAdviceLog.php` | Advice logging model |
| `app/Services/AI/AdviceReviewService.php` | Data change detection + annual review |
| `app/Services/AI/KycGateChecker.php` | KYC data completeness checker |
| `app/Services/AI/Prompts/ComplianceRules.php` | Layer 2: FCA compliance rules |
| `app/Services/AI/Prompts/CoreIdentity.php` | Layer 1: identity, security, scope |
| `app/Services/AI/Prompts/FcaProcessInstructions.php` | Layer 3: FCA 6-step process |
| `app/Services/AI/Prompts/QueryKnowledge.php` | Layer 8: per-domain knowledge RAG |
| `app/Services/AI/QueryClassifier.php` | Multi-label query classification |
| `app/Services/AI/StructuredResponseValidator.php` | Response compliance validation |
| `app/Services/AI/SystemPromptBuilder.php` | 10-layer prompt assembly |
| `database/migrations/2026_04_01_150000_create_ai_advice_log_table.php` | Advice log table |
| `database/migrations/2026_04_01_160000_add_system_prompt_to_ai_messages_table.php` | System prompt column |

### Modified PHP Files (upload to server)

| File | Changes |
|------|---------|
| `app/Constants/FinancialPlanningKnowledge.php` | Added per-domain accessor methods |
| `app/Models/AiMessage.php` | Added system_prompt to fillable |
| `app/Models/User.php` | Added aiConversations() relationship |
| `app/Traits/HasAiChat.php` | Wired classify + KYC + validator + prompt persistence + token tracking |
| `routes/api.php` | Added admin ai-audit routes |

### Frontend Files (built via build script)

| File | Changes |
|------|---------|
| `resources/js/components/Admin/AiAudit.vue` | NEW — three-panel audit dashboard |
| `resources/js/services/aiAuditService.js` | NEW — API wrapper |
| `resources/js/views/Admin/AdminPanel.vue` | Tab grouping (Users dropdown, AI dropdown) |
| `resources/js/components/Shared/AiChatPanel.vue` | Scroll fixes, suggestion bypass |
| `resources/js/components/Onboarding/steps/IncomeStep.vue` | Other Income field removed |
| `resources/js/components/UserProfile/IncomeOccupation.vue` | Other Income field removed |

### Test Files (do NOT upload to production)

```
tests/Unit/Constants/QuerySchemasTest.php
tests/Unit/Services/AI/AdviceReviewServiceTest.php
tests/Unit/Services/AI/KycGateCheckerTest.php
tests/Unit/Services/AI/QueryClassifierTest.php
tests/Unit/Services/AI/QueryKnowledgeTest.php
tests/Unit/Services/AI/StructuredResponseValidatorTest.php
tests/Unit/Services/Admin/UserMetricsServiceTest.php
```

---

## Build & Upload Steps

### 1. Build frontend locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload to SiteGround

Upload via File Manager to `~/www/fynla.org/public_html/`:

- `public/build/` — entire directory (built assets)
- All PHP files listed above (new + modified)
- `database/migrations/` — both new migration files

### 3. SSH and run post-deploy

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

### 4. Verify

- Login to fynla.org
- Open Fyn chat, send a message — verify response
- Go to /admin → AI Audit tab → verify conversations load
- Check system prompt is stored (click "Show System Prompt")

---

## Rollback

If issues, the changes are additive — no destructive schema changes. The new tables and column can be left in place. To rollback the code, re-upload the previous version's PHP files from main before this merge.
