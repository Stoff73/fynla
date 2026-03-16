# Fyn Assistant Optimisation — Deploy Guide

**Date:** 16 March 2026
**Branch:** `fynAssist`
**Status:** DEPLOYED TO PRODUCTION — 16 March 2026
**Reference:** `March/March16Updates/fyn-assistant-audit.md` (full audit with 21 recommendations)

---

## Files Changed (7 files)

### Backend (5 PHP files)

| File | Changes |
|------|---------|
| `app/Services/AI/AiChatService.php` | R1: True SSE streaming via Guzzle (replaces synchronous HTTP), R4: Prompt caching with `cache_control: ephemeral`, R13: Error categorisation (429/529/auth/token), R14: Tool call history in message metadata, R15/R20: Complexity classification + token budget checks |
| `app/Services/AI/AiContextBuilder.php` | R5: XML tag prompt structure (13 sections), R6: 5 few-shot examples, R7: Expanded financial summary (savings/investments/pensions/protection/property/tax band), R8: 6-rule regulatory compliance section, R11: Out-of-scope handling, R12: 2-minute Cache::remember on financial summary, R17: Response format instructions, R18: Personality guidelines, R19: Enriched module context for 16 routes. New dependency: `TaxConfigService` |
| `app/Services/AI/AiToolDefinitions.php` | R2: `strict: true` on all 17 tools, `additionalProperties: false` on all schemas, date format validation, fixed 3 empty-properties schemas |
| `app/Services/AI/AiToolExecutor.php` | R3: Input validation (Validator::make) in all 11 create methods, R10: Duplicate detection for accounts/pensions/policies, R21: Structured error returns with `error_type` field |
| `app/Services/AI/AiModelResolver.php` | R9: Max tokens increased to 4096/8192, R15: Model tiering (Haiku default, Sonnet for complex+pro), R20: Daily token budget monitoring with per-plan limits |

### Frontend (2 files)

| File | Changes |
|------|---------|
| `resources/js/components/Shared/AiChatPanel.vue` | R16: "Stop generating" cancel button during streaming |
| `resources/js/store/modules/aiChat.js` | R16: AbortController wired to sendMessage, enhanced abortStreaming action |

---

## Upload Order

### Step 1: Backend files (upload first)

```
app/Services/AI/AiChatService.php
app/Services/AI/AiContextBuilder.php
app/Services/AI/AiToolDefinitions.php
app/Services/AI/AiToolExecutor.php
app/Services/AI/AiModelResolver.php
```

### Step 2: Frontend build + upload

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` directory.

### Step 3: Clear caches via SSH

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## New Dependencies

- **GuzzleHTTP** — already included in Laravel (no composer install needed). `AiChatService` now uses `GuzzleHttp\Client` directly instead of `Illuminate\Support\Facades\Http` for streaming support.
- **TaxConfigService** — now injected into `AiContextBuilder` constructor. Laravel's DI container resolves this automatically.

---

## Configuration

### Optional: Advanced model for pro users (R15)

Add to `.env` if you want to specify a different model for complex queries by pro users:

```
ANTHROPIC_ADVANCED_CHAT_MODEL=claude-sonnet-4-5-20241022
```

If not set, defaults to `claude-sonnet-4-5-20241022` for pro+complex queries.

### Existing config (unchanged)

```
ANTHROPIC_API_KEY=sk_...
ANTHROPIC_CHAT_MODEL=claude-haiku-4-5-20251001   # Override default model
```

---

## No Database Changes

No migrations needed. All changes are in application code only. The `metadata` column on `ai_messages` table (JSON cast) is used for storing tool call history — this column already exists.

---

## Token Budget Limits (R20)

| Plan | Daily Token Limit |
|------|-------------------|
| Student | 50,000 |
| Standard | 200,000 |
| Pro | 500,000 |

These are hardcoded in `AiModelResolver::DAILY_TOKEN_LIMITS`. Adjust as needed.

---

## Rollback

If issues arise, revert the 5 PHP files to their previous versions from the `main` branch. The frontend changes (cancel button) are purely additive and harmless without backend changes.

---

## Verification

After deploying:

1. **Streaming**: Open Fyn Assistant, send a message — text should appear word-by-word immediately (not after a long pause)
2. **Cancel button**: During a response, a "Stop generating" button should appear below the streaming text
3. **Tool creation**: Tell the assistant "I have a Halifax savings account with £5,000" — it should create the account and briefly confirm
4. **Duplicate detection**: Repeat the same message — it should warn about the existing similar account
5. **Out-of-scope**: Ask "What's the weather like?" — it should redirect to financial topics
6. **Regulatory language**: Ask for investment advice — responses should use hedging language, not directives
7. **Error handling**: Check server logs for any streaming errors or Guzzle connection issues
