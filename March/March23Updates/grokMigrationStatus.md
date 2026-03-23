# Grok AI Migration Status — 23 March 2026

**Branch:** grokAI
**Commits:** 2 (bc05c9d, 5fdd765)

## Completed

### Phase 0: Security Hardening (deployed to production on main)

- [x] Anti-prompt-injection rules in system prompt (9-point security block)
- [x] PII minimisation: first name only sent to AI provider
- [x] Output sanitisation: strip dangerous HTML tags from streamed AI text
- [x] Removed national_insurance_number from AI profile update allowlist
- [x] Added structured [AI-AUDIT] logging for all AI write tool executions
- [x] Verified: prompt injection blocked, normal chat works, frontend already escapes HTML

### Phase 1: Foundation (grokAI branch)

- [x] Installed `openai-php/client` via Composer (v0.19.1)
- [x] Created `app/Services/AI/XaiClient.php` singleton wrapper
- [x] Added `xai` config block to `config/services.php` with model defaults
- [x] Added `AI_PROVIDER` feature flag (anthropic/xai) for safe rollback
- [x] Updated `AppServiceProvider.php` to register correct client based on flag

### Phase 3: Streaming Chat Rewrite (grokAI branch)

- [x] `HasAiChat.php` rewritten with dual-provider streaming loop
- [x] xAI path: system prompt as role:system message, OpenAI SSE delta format, tool call accumulation by index, tool results as role:tool messages
- [x] Anthropic path: fully preserved, client resolved from container
- [x] `HasAiGuardrails.php` updated: provider-aware model selection
- [x] `AiToolDefinitions.php` updated: returns raw format for xAI, converts to input_schema for Anthropic
- [x] `CoordinatingAgent.php` updated: removed AnthropicClient constructor dependency
- [x] Browser tested: Anthropic path confirmed working on dev server

## Remaining

### Phase 2: Document Extraction

- [ ] Update `app/Services/Documents/AIExtractionService.php`
- [ ] Change API URL from `api.anthropic.com` to `api.x.ai`
- [ ] Change auth header from `x-api-key` to `Authorization: Bearer`
- [ ] Remove `anthropic-version` header
- [ ] Change request body: Anthropic content blocks to OpenAI format
- [ ] Change image format: `source.type:base64` to `image_url.url:data:...`
- [ ] Change response parsing: `content[0].text` to `choices[0].message.content`
- [ ] Change token field names: `input_tokens` to `prompt_tokens`
- [ ] Test with real document upload

### Phase 4: Drop Python Sidecar

- [ ] Create `app/Services/AI/XaiDeepAnalysisService.php`
- [ ] Implement PHP-native tool-use loop using `openai-php/client`
- [ ] Wire tool handlers to call PHP services directly (no HTTP round-trip)
- [ ] Replace `PythonAgentBridge` injection sites with `XaiDeepAnalysisService`
- [ ] Test holistic plan and scenario generation
- [ ] Keep Python files until confirmed working

### Phase 5: Cleanup

- [ ] Remove `anthropic-ai/sdk` from `composer.json` (after full testing)
- [ ] Delete Python scripts: `scripts/fynla_agent/`, `scripts/run_agent.py`, `scripts/requirements.txt`
- [ ] Update frontend legal text (privacy policy, terms — provider name)
- [ ] Add changelog entry to Version page
- [ ] Update `.env.example` with xAI variables
- [ ] Run `composer install --no-dev` on production
- [ ] Upload all changed PHP files
- [ ] Clear caches on production

### Phase 6: Testing (after all phases complete)

- [ ] Set `AI_PROVIDER=xai` and `XAI_API_KEY` in dev `.env`
- [ ] Test normal chat query — verify streaming works
- [ ] Test prompt injection — verify security block works
- [ ] Test tool calling — navigate, create entity, get analysis
- [ ] Test multi-tool response — verify tool call accumulation by index
- [ ] Test document extraction — upload PDF/image
- [ ] Test deep analysis — holistic plan generation
- [ ] Test all 6 preview personas
- [ ] Test long conversation (20+ messages)
- [ ] Test token budget limits
- [ ] Test error handling (invalid key, rate limit)
- [ ] Test mobile SSE (if applicable)
- [ ] Switch back to `AI_PROVIDER=anthropic` — verify rollback works

## To Activate Grok (when ready)

1. Get API key from https://console.x.ai
2. Add to production `.env`:
   ```
   AI_PROVIDER=xai
   XAI_API_KEY=xai-your-key-here
   XAI_CHAT_MODEL=grok-4-1-fast-reasoning
   XAI_VISION_MODEL=grok-4-1-fast-non-reasoning
   ```
3. Upload changed PHP files + `composer.json` + `composer.lock`
4. SSH: `composer install --no-dev && php artisan config:clear && php artisan optimize`

## Rollback

Change `.env` on production:
```
AI_PROVIDER=anthropic
```
Then: `php artisan config:clear`

Both SDKs are installed side by side. The feature flag controls which is used.

## Files Changed (grokAI branch vs main)

| File | Change |
|------|--------|
| `composer.json` | Added `openai-php/client` |
| `composer.lock` | Updated |
| `config/services.php` | Added `xai` config + `ai_provider` flag |
| `app/Providers/AppServiceProvider.php` | Provider-aware client registration |
| `app/Services/AI/XaiClient.php` | NEW — OpenAI SDK wrapper for xAI |
| `app/Traits/HasAiChat.php` | Dual-provider streaming loop |
| `app/Traits/HasAiGuardrails.php` | Provider-aware model selection |
| `app/Services/AI/AiToolDefinitions.php` | Provider-aware tool format |
| `app/Agents/CoordinatingAgent.php` | Security fixes + removed Anthropic dependency |
