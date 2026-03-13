# Deploy Notes — Anthropic AI Migration + Login Fix — 13 March 2026

**Status:** NOT YET DEPLOYED
**Branch:** `aiUpdate`
**Type:** PHP files + frontend rebuild + .env update

## Summary

Two changes in this branch:

1. **AI Chat: Cerebras to Anthropic** — Switched Fyn assistant from Cerebras `gpt-oss-120b` to Anthropic `claude-haiku-4-5-20251001`. Same tool calling, same SSE streaming, same frontend — only the backend API integration changed.

2. **Login fix: challenge_token** — Fixed login breakage caused by IDOR fix in `AuthController.resolveLoginUserId()`. Frontend was sending `user_id` instead of `challenge_token` for verify-code and resend-code requests, causing 422 errors.

---

## Pre-Deploy: Build Frontend

The frontend must be rebuilt — login fix touches 5 Vue/JS files.

```bash
./deploy/fynla-org/build.sh
```

Then upload the entire `public/build/` directory.

---

## PHP Files to Upload

Upload via SiteGround File Manager to `~/www/fynla.org/public_html/`.

### AI Services (3 files)

| Local | Remote |
|-------|--------|
| `app/Services/AI/AiChatService.php` | `~/www/fynla.org/public_html/app/Services/AI/` |
| `app/Services/AI/AiModelResolver.php` | `~/www/fynla.org/public_html/app/Services/AI/` |
| `app/Services/AI/AiToolDefinitions.php` | `~/www/fynla.org/public_html/app/Services/AI/` |

### Config (1 file)

| Local | Remote |
|-------|--------|
| `config/services.php` | `~/www/fynla.org/public_html/config/` |

---

## Frontend Build (Upload `public/build/`)

After running `./deploy/fynla-org/build.sh`, upload the entire `public/build/` directory.

**5 frontend files changed (login fix):**

| File | What Changed |
|------|-------------|
| `services/authService.js` | `verifyCode()` and `resendCode()` now send `challenge_token` instead of `user_id` |
| `components/Auth/VerificationCodeModal.vue` | Replaced `userId` prop with `challengeToken`, sends `challenge_token` in verify/resend requests |
| `views/Login.vue` | Stores `challenge_token` from login response, passes to `VerificationCodeModal` |
| `mobile/views/MobileLoginScreen.vue` | Passes `challengeToken` via query params (not router state) |
| `mobile/views/VerificationCodeScreen.vue` | Reads `challengeToken` from query params for verify/resend |

---

## Environment Variables

**Add to production `.env`:**

```env
ANTHROPIC_CHAT_MODEL=claude-haiku-4-5-20251001
```

**Note:** `ANTHROPIC_API_KEY` already exists in production `.env` (used by document extraction). The chat now shares this key.

**Can remove (optional):**

```env
CEREBRAS_API_KEY=...
CEREBRAS_CHAT_MODEL=...
```

---

## Files NOT to Upload (Dev Only)

| File | Reason |
|------|--------|
| `.env` | Contains local secrets |
| `.env.example` | Template only |

---

## Post-Deploy: SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Add new env var
echo 'ANTHROPIC_CHAT_MODEL=claude-haiku-4-5-20251001' >> .env

# Optionally remove old Cerebras vars from .env
# (edit manually via File Manager or nano)

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Upload Checklist

- [ ] Run `./deploy/fynla-org/build.sh` locally
- [ ] Upload `public/build/` directory
- [ ] Upload 3 AI Service files
- [ ] Upload `config/services.php`
- [ ] SSH: Add `ANTHROPIC_CHAT_MODEL` to `.env`
- [ ] SSH: Remove `CEREBRAS_API_KEY` and `CEREBRAS_CHAT_MODEL` from `.env`
- [ ] SSH: Clear caches and optimise
- [ ] Verify login works (challenge_token fix)
- [ ] Verify login verification code works (enter code, resend code)
- [ ] Verify Fyn chat responds — test: "Hello, what can you help me with?"
- [ ] Verify Fyn chat tool calling — test: "Show me my dashboard"
- [ ] Verify Fyn chat in mobile app (if deployed)

---

## What Changed — Technical Detail

### Anthropic API Integration

| Aspect | Cerebras (old) | Anthropic (new) |
|--------|---------------|-----------------|
| Endpoint | `api.cerebras.ai/v1/chat/completions` | `api.anthropic.com/v1/messages` |
| Auth header | `Authorization: Bearer {key}` | `x-api-key: {key}` |
| Model | `gpt-oss-120b` | `claude-haiku-4-5-20251001` |
| System prompt | In messages array as `role: system` | Top-level `system` field |
| Max tokens field | `max_completion_tokens` | `max_tokens` |
| Response format | `choices[0].message.content` | `content[]` blocks (text/tool_use) |
| Tool definitions | `{type: 'function', function: {name, parameters}}` | `{name, input_schema}` |
| Tool args | `tool_calls[].function.arguments` (JSON string) | `content[].input` (object) |
| Tool results | `{role: 'tool', tool_call_id, content}` | `{role: 'user', content: [{type: 'tool_result', tool_use_id, content}]}` |
| Finish reason | `finish_reason: 'tool_calls'` | `stop_reason: 'tool_use'` |
| Token usage | `usage.prompt_tokens` / `completion_tokens` | `usage.input_tokens` / `output_tokens` |
| Tool choice | `tool_choice: 'auto'` | `tool_choice: {type: 'auto'}` |

### Login Fix (challenge_token)

The IDOR fix in `AuthController.resolveLoginUserId()` removed the `user_id` fallback — it now only resolves via `challenge_token`. The frontend was still sending `user_id`, causing 422 "Invalid or expired verification session" errors on both verify-code and resend-code.

Fixed across web (Login.vue, VerificationCodeModal.vue) and mobile (MobileLoginScreen.vue, VerificationCodeScreen.vue, authService.js).

---

## Rollback

If Anthropic chat fails:

1. Revert `AiChatService.php`, `AiModelResolver.php`, `AiToolDefinitions.php`, `config/services.php` to previous versions from `main` branch
2. Re-add `CEREBRAS_API_KEY` and `CEREBRAS_CHAT_MODEL` to production `.env`
3. Clear caches

The login fix is independent and should NOT be rolled back.

---

## Total: 4 PHP files + frontend build + 1 env var
