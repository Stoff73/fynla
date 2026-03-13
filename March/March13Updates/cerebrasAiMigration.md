---
tags:
  - ai
  - cerebras
  - fyn-chat
  - march-2026
---

# Cerebras AI Migration — Fyn Chat

**Date:** 2026-03-13
**Branch:** `aiUpdate`
**Model:** `gpt-oss-120b` on Cerebras (120B params, ~3000 tok/s)

## What Changed

Replaced OpenAI GPT integration with Cerebras API for the Fyn AI chat assistant.

### Model Selection

| Model | Params | Speed | Tool Calling |
|-------|--------|-------|-------------|
| `llama3.1-8b` | 8B | ~2200 tok/s | Broken with `tool_choice: auto` (outputs JSON as text) |
| **`gpt-oss-120b`** | 120B | ~3000 tok/s | Works perfectly with structured `tool_calls` array |

Initially tried `llama3.1-8b` but tool calls came back as raw text in the `content` field instead of the structured `tool_calls` format. Switched to `gpt-oss-120b` which is actually faster and supports all tool features.

### Files Changed

| File | Change |
|------|--------|
| `app/Services/AI/AiChatService.php` | Cerebras API URL, restored tool loop |
| `app/Services/AI/AiContextBuilder.php` | Restored tool-aware system prompt |
| `app/Services/AI/AiModelResolver.php` | Default model → `gpt-oss-120b` |
| `app/Services/AI/AiToolDefinitions.php` | Docblock update only |
| `config/services.php` | Added `cerebras` config block |
| `.env` | `CEREBRAS_API_KEY`, `CEREBRAS_CHAT_MODEL` |

### Tools Available (17 total)

**Core (5):** navigate_to_page, get_module_analysis, run_what_if_scenario, get_tax_information, generate_financial_plan

**Data Creation (12, non-preview only):** create_goal, create_life_event, create_savings_account, create_investment_account, create_pension, create_property, create_mortgage, create_protection_policy, create_estate_asset, create_estate_liability, create_estate_gift, get_recommendations

### Production Deployment

1. Add to production `.env`:
   ```
   CEREBRAS_API_KEY=csk-jtjv6mkxyyhttf96mwfck5epyc3rnwyv65whrtxhdxx3y583
   CEREBRAS_CHAT_MODEL=gpt-oss-120b
   ```
2. Upload: `AiChatService.php`, `AiContextBuilder.php`, `AiModelResolver.php`, `AiToolDefinitions.php`, `config/services.php`
3. `php artisan config:clear && php artisan cache:clear`

### Cerebras API Notes

- OpenAI-compatible endpoint: `https://api.cerebras.ai/v1/chat/completions`
- Same message format, tool definitions, and response structure as OpenAI
- Extra `reasoning` field in responses (model's chain-of-thought)
- Extra `time_info` field with detailed timing breakdown
- Rate limits can hit 429 under load ("queue_exceeded") — transient, retry works
- `AiToolDefinitions` and `AiToolExecutor` unchanged — tool infrastructure fully compatible
