# Deployment Guide — 19 March 2026 (Session 2)

## Pre-Deployment

### 1. Run pending migrations

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
```

Migrations (from March 18 + 19):
- `2026_03_18_100000` — SoftDeletes on models
- `2026_03_18_100001` — Student fields + unique constraints
- `2026_03_18_100002` — Database indexes
- `2026_03_19_100000` — Income definition fields (is_registered_blind, charitable_donations, is_gift_aid)

### 2. Build locally

```bash
./deploy/fynla-org/build.sh
```

## Files to Upload

### Frontend Build
Upload entire `public/build/` directory.

### PHP Files — PR #140 (logicFix)

```
app/Http/Requests/UpdatePersonalInfoRequest.php
resources/js/components/UserProfile/LetterToSpouse.vue
```

### PHP Files — PR #141 (taxConfigFix)

```
resources/js/components/Admin/TaxSettings.vue
app/Agents/EstateAgent.php
app/Agents/TaxOptimisationAgent.php
app/Traits/HasAiChat.php
app/Agents/CoordinatingAgent.php
app/Services/AI/AiToolDefinitions.php
```

### PHP + JS Files — PR #142 (aiTools)

```
app/Services/AI/AiToolDefinitions.php          (already in #141 list — upload latest)
app/Agents/CoordinatingAgent.php               (already in #141 list — upload latest)
app/Services/PrerequisiteGateService.php
resources/js/utils/chatNavigationRouter.js     (new file)
resources/js/components/Shared/AiChatPanel.vue
```

### PHP + JS Files — PR #143 (uiFix)

```
resources/js/components/Navbar.vue
resources/js/components/Shared/AiChatPanel.vue (already listed — upload latest)
resources/js/layouts/AppLayout.vue
resources/js/store/modules/aiChat.js
```

### PHP + JS Files — PR #144 (dataReadiness)

```
app/Services/PrerequisiteGateService.php       (already listed — upload latest)
app/Http/Controllers/Api/LifeStageController.php
resources/js/store/modules/completeness.js
resources/js/store/modules/investment.js
resources/js/views/Dashboard.vue
```

## Consolidated File List (deduplicated)

Upload these files to production:

```
# Backend (PHP)
app/Agents/CoordinatingAgent.php
app/Agents/EstateAgent.php
app/Agents/TaxOptimisationAgent.php
app/Http/Controllers/Api/LifeStageController.php
app/Http/Requests/UpdatePersonalInfoRequest.php
app/Services/AI/AiToolDefinitions.php
app/Services/PrerequisiteGateService.php
app/Traits/HasAiChat.php

# Frontend (included in build, but listed for reference)
resources/js/components/Admin/TaxSettings.vue
resources/js/components/Navbar.vue
resources/js/components/Shared/AiChatPanel.vue
resources/js/components/UserProfile/LetterToSpouse.vue
resources/js/layouts/AppLayout.vue
resources/js/store/modules/aiChat.js
resources/js/store/modules/completeness.js
resources/js/store/modules/investment.js
resources/js/utils/chatNavigationRouter.js
resources/js/views/Dashboard.vue
```

## Post-Deployment

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
php artisan db:seed
```

## Verification

1. Log in as admin → Admin Panel → Tax Settings → verify no NaN values, all 10 tabs render
2. AI chat → type "show me my goals" → verify instant navigation (no loading)
3. AI chat header shows "Fyn" not "Fynla Assistant"
4. Info guide button (question mark) is in top navbar, not floating bottom-right
5. Check admin Tax Settings → IHT tab → PET taper relief shows year ranges with percentages
6. Dashboard → user with investments but no knowledge_level → violet nudge banner appears with 3 buttons
7. Click a knowledge level button → banner disappears and doesn't return
