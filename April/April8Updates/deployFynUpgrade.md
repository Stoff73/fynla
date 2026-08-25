# Deploy Guide — Fyn Token Upgrade (8 April 2026)

**Branch:** `fynUpgrade` merged to `main`
**Type:** Frontend + Backend
**Status:** DEPLOYED to fynla.org — 8 April 2026

---

## What Changed

### 1. Token Limits Doubled
| Tier | Old | New |
|------|-----|-----|
| Preview | 50,000 | 100,000 |
| Trial | 500,000 | 1,000,000 |
| Student | 150,000 | 300,000 |
| Standard | 500,000 | 1,000,000 |
| Family | 500,000 | 1,500,000 |
| Pro | 1,000,000 | 2,000,000 |

### 2. Token Limit Reached UI
- When user hits daily limit, Fyn shows a violet info box with countdown timer
- Timer shows hours and minutes until midnight reset
- Input field disabled with "Daily limit reached" placeholder
- Send button blocked

### 3. New API Endpoint
- `GET /api/ai-chat/token-usage` — returns usage, limit, remaining, reset time

### 4. Reset at Midnight
- Tokens reset at 00:00 each day (was already midnight-based, no change to logic)

---

## Files to Upload

### PHP files (upload to `~/www/fynla.org/public_html/`)

```
app/Http/Controllers/Api/AiChatController.php
app/Traits/HasAiChat.php
app/Traits/HasAiGuardrails.php
routes/api.php
```

### Frontend build (upload entire directory)

```
public/build/
```

---

## SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## No Database Changes
- No migrations
- No seeding required
- No composer changes
- No new environment variables

---

## Post-Deploy Verification

1. Log in and open Fyn chat
2. Send a message — should work normally
3. Check `GET /api/ai-chat/token-usage` returns correct limit for user's tier
4. Verify route exists: `php artisan route:list --path=ai-chat/token-usage`
