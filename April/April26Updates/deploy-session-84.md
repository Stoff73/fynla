# Deploy notes — session 84 (2026-04-26)

**Branch:** `feature/fyn-persona-split` at `085bfe7`
**Single commit:** `feat(auth): record GDPR consents at registration verifyCode (S0.5.z)`

This is a **dev-environment-only** deploy (csjones.co/fynla) until the branch is merged via PR to `dev`, then promoted to `main`. Do NOT push directly to fynla.org from this branch.

## What changed

Real product fix: registered users were silently locked out of onboarding because `AuthController::verifyCode` never recorded the four GDPR consents the post-registration journey depends on (terms, privacy, data_processing, ai_chat). Without `ai_chat` consent, the dashboard's auto-onboarding hit a 403 on `/api/ai-chat/onboarding/start` and the frontend silently fell back to a blank conversation. Form footer "By creating an account, you agree to our Terms of Service and Privacy Policy" was a UX promise the backend wasn't honouring.

## Files to upload

| Category | File | Action |
|---|---|---|
| PHP Backend | `app/Http/Controllers/Api/AuthController.php` | Upload via SiteGround File Manager |

No frontend rebuild, no migrations, no seeders, no composer changes, no routes/config changes (the new `ConsentService` dep is auto-resolved by Laravel's container).

## Pre-deploy verification (already done locally)

- 486 Pest tests pass across `tests/Feature/Auth`, `tests/Feature/AI`, `tests/Feature/Fyn`, `tests/Feature/Onboarding`, `tests/Architecture` (1605 assertions, 0 regressions)
- BS-01 GREEN end-to-end via Playwright real-user flow (User #54, all 4 consents granted, onboarding_completed=true, Aviva £300k policy created, spouse linked)

## Dev deploy (csjones.co/fynla)

1. Open PR `feature/fyn-persona-split → dev`, merge after review
2. `git checkout dev && git pull`
3. `./deploy/csjones-fynla/build.sh` — only needed if frontend changed (it didn't this session); for this deploy, **skip the build** and just upload the single PHP file
4. Upload `app/Http/Controllers/Api/AuthController.php` to `~/www/csjones.co/public_html/fynla/` via SiteGround File Manager (preserve directory structure)
5. SSH and clear caches:
   ```
   ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
   cd ~/www/csjones.co/public_html/fynla
   php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
   ```
6. Smoke test: open https://csjones.co/fynla in incognito, click Quick start with Fyn, register a fresh user, verify the chat opens with the onboarding turn (no 403 on `/api/ai-chat/onboarding/start` in dev tools)

## Production deploy (fynla.org) — only after dev verified

1. Open PR `dev → main`, you self-approve, merge
2. `git checkout main && git pull`
3. Upload `app/Http/Controllers/Api/AuthController.php` to `~/www/fynla.org/public_html/`
4. SSH and clear caches:
   ```
   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
   cd ~/www/fynla.org/public_html
   php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
   ```
5. Smoke test on fynla.org with a fresh registration
6. Monitor `storage/logs/laravel.log` for 10-15 min

## Rollback plan

Single-file change. To rollback: revert commit `085bfe7` and re-upload `AuthController.php` from the prior version (`b698358`). Newly-registered users between deploy and rollback will have the four consent rows; that's harmless data and matches what they'd have post-rollback if they ever touched /settings.
