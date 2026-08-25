# Deploy Guide — Fyn-Driven Onboarding (`onboardingFyn` → `dev`)

**Branch:** `onboardingFyn`
**Target:** `csjones.co/fynla` (dev environment)
**Commits:** 14 (from `0f8ef5f` to `c3f3bbb`)
**Build script:** `./deploy/csjones-fynla/build.sh` (run locally; rebuild any time you pull a new commit on `onboardingFyn`)

This deploy ships the full Fyn-driven onboarding flow — the backend-authoritative state machine, grouped LLM field extraction, personalised prompts, spouse/dependant capture with account linking, the narrower NO ICONS rule, and the silent-failure fixes that surfaced during the first live test on csjones.co.

## ⚠️ Sibling-directory layout (read first)

csjones.co/fynla uses the **sibling-dir + symlink** pattern, not a standard Laravel-in-public_html layout. Per `deploy/csjones-fynla/BOOTSTRAP.md`:

```
~/www/csjones.co/
  fynla-app/               ← Laravel app (upload files HERE)
     app/ config/ database/ routes/ resources/ public/ ...
  public_html/
     fynla → ../fynla-app/public    ← symlink, NOT the app dir
```

**Upload PHP/config files to `~/www/csjones.co/fynla-app/` — NOT `public_html/fynla/`.**
**Upload `public/build/` to `~/www/csjones.co/fynla-app/public/build/`.**
**Run `php artisan` commands from `~/www/csjones.co/fynla-app/`.**

The `public_html/fynla/` path only exists as a symlink for serving static assets. Running artisan from there fails with "Could not open input file: artisan" because there's no `artisan` in the symlink target root — it's one level up.

## ⚠️ DO NOT MISS: `app/Traits/HasAiChat.php`

This file is the single most common miss during manual uploads and produces the most cryptic failure. If it's stale, the grouped_extract flow dies with:

```
ERROR: [OnboardingChatDirector] Grouped extract delegation failed
error: "Call to undefined method App\Agents\CoordinatingAgent::setChatOverrides()"
```

`setChatOverrides()` lives on the trait, not on CoordinatingAgent directly. `CoordinatingAgent` consumes it via `use HasAiChat;`, so when the trait is stale, new method calls from fresh CoordinatingAgent methods fail with "undefined method on CoordinatingAgent" — which makes people look at the wrong file.

**Always upload `app/Traits/HasAiChat.php` alongside `app/Agents/CoordinatingAgent.php`.** Never just one.

The post-upload sanity check below catches this.

## Pre-flight (local)

1. `git branch --show-current` → `onboardingFyn`
2. `git status` → clean (apart from gitignored `.claude/skills/security-and-hardening/`)
3. `git log --oneline dev..HEAD | wc -l` → `14`
4. `ls public/build/manifest.json` → new manifest exists
5. `./vendor/bin/pest tests/Unit/Services/Onboarding/` → 106 passed

If you've pulled new commits since the last build, re-run `./deploy/csjones-fynla/build.sh` before uploading.

## Files to upload

**Total: 20 PHP/config/migration files + entire `public/build/` directory.**

Tick each one off as you upload it.

### New PHP files (9)

Upload via SiteGround File Manager to `~/www/csjones.co/fynla-app/`:

- [ ] `app/Services/AI/Prompts/EmptyDataGuard.php`
- [ ] `app/Services/Onboarding/OnboardingChatDirector.php`
- [ ] `app/Services/Onboarding/OnboardingPromptBuilder.php`
- [ ] `app/Services/Onboarding/OnboardingStateMachine.php`
- [ ] `app/Services/Onboarding/OnboardingValueInterpreter.php`
- [ ] `app/Services/Onboarding/SpouseLinkingService.php`
- [ ] `config/onboarding.php`
- [ ] `database/migrations/2026_04_15_090000_add_onboarding_fyn_state_to_users.php`
- [ ] `database/migrations/2026_04_15_091500_add_civil_partnership_to_users_marital_status.php`

### Modified PHP files (11)

- [ ] `app/Agents/CoordinatingAgent.php`
- [ ] `app/Http/Controllers/Api/AiChatController.php`
- [ ] `app/Http/Requests/UpdateIncomeOccupationRequest.php`
- [ ] `app/Http/Requests/UpdatePersonalInfoRequest.php`
- [ ] `app/Models/User.php`
- [ ] `app/Services/AI/AiToolDefinitions.php`
- [ ] `app/Services/AI/SystemPromptBuilder.php`
- [ ] `app/Services/Onboarding/JourneyFieldResolver.php`
- [ ] `app/Services/Onboarding/JourneyStateService.php`
- [ ] **`app/Traits/HasAiChat.php`** ← **CRITICAL — see warning above, easiest file to miss**
- [ ] `routes/api.php`

### Files to delete from the server

- [ ] `app/Services/AI/Prompts/NewUserContext.php` — replaced by `EmptyDataGuard.php`. If the file exists from a previous deploy that shipped the scripted-prompt deviation, delete it. If it doesn't exist (dev is pristine), skip.

### Frontend build

- [ ] Upload the entire contents of local `public/build/` to `~/www/csjones.co/fynla-app/public/build/`, overwriting everything.

The build was produced with:
- `VITE_BASE_PATH=/fynla/build/`
- `VITE_ROUTER_BASE=/fynla/`
- `VITE_API_BASE_URL=https://csjones.co/fynla`
- `VITE_REVOLUT_SANDBOX=true`

Do NOT upload a production build. If the router base is wrong the SPA will 404 on load.

#### Frontend source files bundled into `public/build/` (reference only — do not upload separately)

- `resources/js/components/Fyn/FynQuickReplies.vue` (new)
- `resources/js/components/Investment/AccountForm.vue` (modified)
- `resources/js/components/NetWorth/PensionList.vue` (modified)
- `resources/js/components/Shared/AiChatPanel.vue` (modified — includes `c3f3bbb` docked error banner + scroll fixes)
- `resources/js/services/aiChatService.js` (modified)
- `resources/js/store/modules/aiChat.js` (modified)
- `resources/js/store/modules/aiFormFill.js` (modified)
- `resources/js/views/Dashboard.vue` (modified)
- `resources/js/views/Public/LandingPage.vue` (modified)

### Optional / doc-only

- [ ] `CLAUDE.md` — the branch modifies this to add the NO ICONS rule (§14). Not runtime-critical; upload if you want the server's copy to match the branch, skip if you're OK with it drifting.

### Not uploaded

- `tests/Unit/Services/Onboarding/*Test.php` — test files, local only
- `deploy/**` — build scripts, local only
- `April/April15Updates/**` — documentation, gitignored

## Database migrations

Two new migrations, both safe and guarded:

1. `2026_04_15_090000_add_onboarding_fyn_state_to_users.php` — adds 4 nullable columns to `users`: `onboarding_fyn_step`, `onboarding_fyn_path`, `onboarding_fyn_selection`, `onboarding_fyn_context`. Guarded by `Schema::hasColumn` so it's idempotent.

2. `2026_04_15_091500_add_civil_partnership_to_users_marital_status.php` — adds `civil_partnership` to the `users.marital_status` ENUM via raw `ALTER TABLE MODIFY COLUMN`. Down migration maps existing `civil_partnership` rows to `married` before shrinking, so it's reversible but lossy on downgrade.

Both migrations are **additive only**. No existing data is modified on `up`.

## SSH commands after upload

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app     # NOT public_html/fynla — see sibling-dir note above

# Apply the two new migrations (additive — safe to run)
php artisan migrate --force

# Clear caches so the new routes, config, and views are picked up
php artisan cache:clear
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan optimize
```

## Post-upload sanity check — RUN THIS BEFORE SMOKE TESTING

These grep checks prove every modified file was actually uploaded. **If any command below prints `0`, that file is stale on the server and must be re-uploaded.**

```bash
cd ~/www/csjones.co/fynla-app

echo "=== Trait: setChatOverrides (CRITICAL) ==="
grep -c "setChatOverrides" app/Traits/HasAiChat.php

echo "=== Trait: toolsListOverride (CRITICAL) ==="
grep -c "toolsListOverride" app/Traits/HasAiChat.php

echo "=== Agent: chatWithPromptOverride ==="
grep -c "chatWithPromptOverride" app/Agents/CoordinatingAgent.php

echo "=== Agent: handleCapturePersonalDetails ==="
grep -c "handleCapturePersonalDetails" app/Agents/CoordinatingAgent.php

echo "=== Agent: c3f3bbb DOB regex fix ==="
grep -c 'preg_match.*\\\\d\{1,2\}.*\\\\d\{4\}' app/Agents/CoordinatingAgent.php

echo "=== Tools: onboardingExtractionTools ==="
grep -c "onboardingExtractionTools" app/Services/AI/AiToolDefinitions.php

echo "=== Director: handleGroupedExtractTurn ==="
grep -c "handleGroupedExtractTurn" app/Services/Onboarding/OnboardingChatDirector.php

echo "=== Director: emitRetry (c3f3bbb fix) ==="
grep -c "emitRetry" app/Services/Onboarding/OnboardingChatDirector.php

echo "=== Director: onboarding_fyn_path cleared on done (c3f3bbb fix) ==="
grep -c "onboarding_fyn_path = null" app/Services/Onboarding/OnboardingChatDirector.php

echo "=== SpouseLinkingService exists ==="
ls -la app/Services/Onboarding/SpouseLinkingService.php 2>&1 || echo "MISSING"

echo "=== Controller: startOnboarding ==="
grep -c "startOnboarding\|getOnboardingStatus" app/Http/Controllers/Api/AiChatController.php

echo "=== Routes: onboarding/start ==="
grep -c "onboarding/start" routes/api.php

echo "=== Migrations applied ==="
php artisan migrate:status 2>&1 | grep -E "onboarding_fyn_state|civil_partnership"

echo "=== Enum: civil_partnership on DB column ==="
php artisan tinker --execute="\$c = \DB::selectOne(\"SHOW COLUMNS FROM users LIKE 'marital_status'\"); echo str_contains(\$c->Type, 'civil_partnership') ? 'yes' : 'NO';"

echo "=== Config: fyn_flow_enabled ==="
php artisan tinker --execute="echo config('onboarding.fyn_flow_enabled') ? 'enabled' : 'disabled';"

echo "=== Routes registered ==="
php artisan route:list --path=ai-chat | grep -E "onboarding/(start|status)"

echo "=== Frontend build: latest manifest ==="
ls -la public/build/manifest.json
head -1 public/build/manifest.json | cut -c1-200
```

**Expected output for all `grep -c` lines: ≥ 1** (most are ≥ 1, some are 2 or higher). If anything returns `0`, re-upload that file and re-run this block.

**Expected output for routes:** two lines, one for each of `POST api/ai-chat/onboarding/start` and `GET api/ai-chat/onboarding/status`.

**Expected output for migrations:** both migrations listed as `Ran`.

**Expected output for enum:** `yes`.

**Expected output for config:** `enabled`.

## Smoke test the flow

Register a fresh user via the CTA at `https://csjones.co/fynla/register?from=fyn`:

1. Register → verification code (`SELECT verification_code FROM pending_registrations WHERE email='...' ORDER BY id DESC LIMIT 1` via tinker if you need it) → dashboard
2. Fyn auto-opens with `path_choice` bubbles — NO preceding user message
3. Click `Pick a focus` → `Savings`
4. Type: `I was born 12 January 1985 and I am in a civil partnership`
   - Should advance to `base_spouse` with "Great — let's add your **partner's** details" wording (civil partnership branch)
5. Type spouse details including a new email like `Riley, born 15 July 1988, riley@example.com`
   - Should create a linked User + send `SpouseAccountCreated` email + advance to `base_dependants`
6. `No` to dependants
7. `Self-employed`
8. Trade name / role / income in one reply like `Turner Design Studio, freelance graphic designer, £62,000`
9. `About £2,400 a month`
10. Multi-entity savings message like `I have a £10,000 Vanguard Cash ISA and a £500 Monzo current account`
   - Should create 2 SavingsAccount rows
11. `I'm done` → navigates to `/fynla/net-worth/cash` and shows "All set, {name}. Your savings module is ready to explore."

At each step, `php artisan tinker` can verify the DB writes. For example after step 4:

```bash
php artisan tinker --execute="\$u = \App\Models\User::latest()->first(); echo 'dob='.\$u->date_of_birth?->format('Y-m-d').' marital='.\$u->marital_status;"
```

If any step hangs or produces "chat just ended", tail the log immediately:

```bash
tail -100 storage/logs/laravel.log | grep -E "Grouped extract|handleCapture|ERROR|WARNING" | tail -30
```

The c3f3bbb fix adds diagnostic logging around every branch of `handleCapturePersonalDetails` so you can see exactly what Claude extracted and why a call succeeded or failed.

## Kill switch

To hard-disable in an emergency: add `ONBOARDING_FYN_FLOW_ENABLED=false` to `.env`, then `php artisan config:clear`. The CTA still routes to `/register?from=fyn` but the director never triggers — users fall through to the normal empty Fyn chat. Zero file changes needed.

Verification that the switch works:

```bash
# Disable
echo "ONBOARDING_FYN_FLOW_ENABLED=false" >> .env
php artisan config:clear
# POST /onboarding/start should return 503 with {reason: "disabled"}
curl -X POST https://csjones.co/fynla/api/ai-chat/onboarding/start -H "Accept: application/json" -H "Authorization: Bearer <token>" | grep disabled
# Re-enable
sed -i '/ONBOARDING_FYN_FLOW_ENABLED/d' .env
php artisan config:clear
```

## Rollback

1. Set `ONBOARDING_FYN_FLOW_ENABLED=false` in `.env` + `config:clear` → the flow is dead but the code and schema stay. **This is the safest rollback.**
2. If a full rollback is needed:
   ```bash
   php artisan migrate:rollback --step=2  # reverses the two new migrations
   ```
3. Revert the PR in GitHub and re-upload the previous `public/build/` + PHP files from `dev`.

No user-facing data is destroyed by rolling back — `onboarding_progress` rows remain, spouse linking rows remain, savings/investments/etc remain. The user is just dropped back onto the legacy Fyn chat.

## Out of scope for this deploy

- **Mobile Capacitor app** — `MobileFynChat.vue` is not wired for the new `quick_replies` / `onboarding_advance` / `onboarding_complete` / `resume` SSE events. iOS users who click the CTA on mobile web will see the fall-through (normal Fyn chat). Mobile app build + rewire is a separate task.
- **Pest feature tests** — only unit tests exist (106 passing). Feature tests covering the full walkthrough per `fynOnboardFix.md §16` are not written yet.
- **`dc_pensions.current_value` prod migration** — separate ops task; out of scope for dev deploy.
- **8 frontend files still checking `marital_status === 'married'`** without accepting `civil_partnership` (reported at commit `95a1dd3`). Separate latent-bug branch.
- **Pre-existing banned icons** on dashboard cards / detail views (out of scope per the narrower NO ICONS rule in `CLAUDE.md §14`).

## Commits in this deploy (newest first)

```
c3f3bbb onboarding: fix silent failures on grouped_extract turns
e005608 onboarding: fix conversation split race + cleanup on done
d28e251 onboarding: warmer personalisation pass on prompts + asset intros
bd30d14 onboarding: grouped LLM extraction turns + state machine restructure
df0ca74 onboarding: uniform bubble sizing + disable historical bubbles
707dfab docs: narrow NO ICONS rule — functional-only, side nav allowed
9aa6283 docs: lock in NO ICONS rule + strip onboarding emoji
91588c7 onboarding: remove legacy pendingJourneyPrompt dead state
99c4efe onboarding: wire frontend to director endpoints
282c804 onboarding: add director, prompt builder, /onboarding endpoints
95a1dd3 onboarding: add civil_partnership to marital_status enum
a702525 onboarding: add state machine, value interpreter, unit tests
40ae33c onboarding: add fyn_state columns, config flag, User cast
0f8ef5f onboarding: land phase 0-3 keepers, revert phase 4 shortcut
```

## Changelog (this guide)

- **2026-04-15 (v2)** — rewritten after first dev test caught a missed `HasAiChat.php` upload. Added: explicit critical-file warning for the trait, post-upload sanity check grep block, tick-off checkboxes, CLAUDE.md mention, commit range bumped to include `c3f3bbb`, frontend rebuild note, 20-file total count.
- **2026-04-15 (v1)** — initial guide for the `onboardingFyn → dev` deploy (commits `0f8ef5f` to `e005608`).
