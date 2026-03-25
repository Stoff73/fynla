# Complete Deploy Fix — All Missing Files (25 March 2026)

**Status:** DEPLOYED to production 25 March 2026

**Problem:** AI chat returned 500 on production. `deployAI.md` was incomplete — missing the xAI foundation layer. Fixed with 3 rounds of uploads.

## ALL PHP Files to Upload

Upload every file below to `~/www/fynla.org/public_html/` + the relative path.

### New files (not on server)

| # | File | Notes |
|---|------|-------|
| 1 | `app/Services/AI/XaiToolDefinitions.php` | xAI-specific tool definitions with strict mode |
| 2 | `app/Services/AI/XaiClient.php` | OpenAI SDK wrapper for xAI Grok API |

### Modified files (out of date on server)

| # | File | Notes |
|---|------|-------|
| 3 | `app/Traits/HasAiChat.php` | Routes xAI to XaiToolDefinitions, system prompt, existing records |
| 4 | `app/Agents/CoordinatingAgent.php` | All form fill handlers, resolveFamilyNames, list_records |
| 5 | `config/services.php` | xAI config block (api_key, model, base_url) + ai_provider setting |
| 6 | `app/Providers/AppServiceProvider.php` | XaiClient singleton registration |
| 7 | `app/Http/Controllers/Api/AdminController.php` | AI provider GET/POST endpoints |
| 8 | `app/Http/Middleware/SecurityHeaders.php` | CSP headers for api.x.ai |
| 9 | `routes/api.php` | list_records route, admin AI provider routes |
| 10 | `app/Http/Requests/StoreInvestmentAccountRequest.php` | SAYE validation fix |
| 11 | `app/Http/Controllers/Api/Estate/TrustController.php` | Settlor field validation |
| 12 | `app/Services/Auth/SessionService.php` | Orphaned sessions filter (WARN-002 fix) |
| 13 | `app/Http/Controllers/Api/SessionController.php` | Try-catch on sessions index |
| 14 | `composer.json` | Adds openai-php/client dependency |
| 15 | `composer.lock` | Lock file for reproducible install |

### Frontend build

```bash
./deploy/fynla-org/build.sh
```
Upload `public/build/` → `~/www/fynla.org/public_html/public/build/`

## SSH Commands (in order)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# 1. Install new PHP dependency (openai-php/client)
composer install --no-dev --optimize-autoloader

# 2. Clear ALL caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize
```

## .env Variables (add if not present)

```
AI_PROVIDER=xai
XAI_API_KEY=xai-xxxxxxxxxxxx
```

Replace `xai-xxxxxxxxxxxx` with your actual xAI API key.

## Verify After Deploy

1. **Admin Panel → AI Provider tab** — should show provider cards with toggle (not blank)
2. **Fyn chat** — send "Hello" — should get a response (not 500)
3. **Fyn chat** — send "I own a house worth £350,000" — should navigate to property form and fill it
