# AI Agent System Upgrade

**Branch:** `aiUpgrade`
**Date:** 2026-03-16
**Status:** Complete, tested, committed

---

## What Changed

### Architecture: Before vs After

**Before** — 8 separate AI service files (~3,700 lines) + 9 agents, disconnected:
```
Controller → AiChatService → AiModelResolver
                            → AiContextBuilder → CoordinatingAgent (15% of output used)
                            → AiToolDefinitions
                            → AiToolExecutor
           → AiSimulatedService → AiIntentMatcher → AiSimulatedResponseBuilder
```

**After** — CoordinatingAgent is the single entry point for chat:
```
Controller → CoordinatingAgent::chat()
               ├── PrerequisiteGate::enforce()     ← blocks until data exists
               ├── buildSystemPrompt()             ← uses 100% of orchestrateAnalysis()
               ├── streamCompletion()              ← Anthropic PHP SDK (streaming)
               ├── executeTool()                   ← with prerequisite checks
               └── AiToolDefinitions               ← kept (tool schemas)
```

### Key Improvements

1. **8 AI files → 2 traits + 1 service** (net -293 lines)
2. **100% of orchestrateAnalysis()** now used in prompts (was ~15%) — includes decision traces, cashflow allocation, conflicts, cross-module strategies
3. **Programmatic prerequisite gates** block advice/tools until data exists — user gets clear explanation of what's missing + automatic navigation to the right page
4. **Anthropic PHP SDK** (`anthropic-ai/sdk` v0.6.0) replaces raw Guzzle HTTP calls
5. **Unified code path** — preview + real users both go through `CoordinatingAgent::chat()`
6. **Python Agent SDK sidecar** scaffolding ready for future deep analysis
7. **Removed quick reply chips** — chat is fully user-driven, no "Tell me more" / "What should I focus on?" prompts after assistant responses

### Prerequisite Gate System

When a user asks about a module with missing data, the assistant:
- Explains exactly what data is missing and why
- Lists each missing item as a bullet point
- Navigates the user to the correct page to add the information
- Does NOT attempt to give vague/misleading advice

Gates exist for all 7 modules: Protection, Savings, Retirement, Investment, Estate, Goals, Tax Optimisation.

---

## Files Changed

### New Files (14)

| File | Purpose |
|------|---------|
| `app/Services/PrerequisiteGateService.php` | Centralised prerequisite enforcement for all modules |
| `app/Traits/HasAiChat.php` | Streaming chat, prompt building, message persistence |
| `app/Traits/HasAiGuardrails.php` | Model selection, token budgets, error categorisation |
| `app/Http/Controllers/Api/AgentInternalController.php` | Internal API for Python agent callbacks |
| `app/Http/Middleware/AgentTokenAuth.php` | Shared secret auth for internal agent routes |
| `app/Services/PythonAgentBridge.php` | Subprocess bridge to Python Agent SDK |
| `scripts/fynla_agent/__init__.py` | Python package init |
| `scripts/fynla_agent/agent.py` | Python Agent SDK entry point |
| `scripts/fynla_agent/config.py` | Python config (models, URLs, limits) |
| `scripts/fynla_agent/hooks.py` | PreToolUse prerequisite hooks |
| `scripts/fynla_agent/schemas.py` | Pydantic output schemas |
| `scripts/fynla_agent/tools.py` | MCP tools calling Laravel API |
| `scripts/requirements.txt` | Python dependencies |
| `scripts/run_agent.py` | CLI entry point for subprocess |

### Modified Files (9)

| File | What Changed |
|------|-------------|
| `app/Agents/CoordinatingAgent.php` | Added `use HasAiChat, HasAiGuardrails`, new deps (AnthropicClient, AiToolDefinitions, NetWorthService, PrerequisiteGateService), `executeTool()` with all entity creation + prerequisite gates |
| `app/Http/Controllers/Api/AiChatController.php` | 2 service deps → 1 CoordinatingAgent |
| `app/Http/Kernel.php` | Added `agent.token` middleware alias |
| `app/Providers/AppServiceProvider.php` | Registered `Anthropic\Client` singleton |
| `app/Services/AI/AiToolDefinitions.php` | Removed `strict: true` (was causing API rejection) |
| `composer.json` / `composer.lock` | Added `anthropic-ai/sdk` |
| `config/services.php` | Added `advanced_chat_model` + `agent_internal_token` |
| `resources/js/components/Shared/AiChatPanel.vue` | Fixed docked mode passing wrong prop to AiMessageContent; removed QuickReplyChips (quick reply prompts after assistant messages) |
| `routes/api.php` | Added `internal/agent/*` routes |

### Deleted Files (7)

| File | Replaced By |
|------|------------|
| `app/Services/AI/AiChatService.php` | `HasAiChat` trait on CoordinatingAgent |
| `app/Services/AI/AiContextBuilder.php` | `HasAiChat::buildSystemPrompt()` |
| `app/Services/AI/AiToolExecutor.php` | `CoordinatingAgent::executeTool()` |
| `app/Services/AI/AiModelResolver.php` | `HasAiGuardrails` trait |
| `app/Services/AI/AiIntentMatcher.php` | No longer needed (was for simulated mode) |
| `app/Services/AI/AiSimulatedService.php` | No longer needed (unified code path) |
| `app/Services/AI/AiSimulatedResponseBuilder.php` | No longer needed |

### Kept Unchanged

| File | Reason |
|------|--------|
| `app/Services/AI/AiToolDefinitions.php` | 650 lines of tool schemas — independent of orchestration |

---

### Bug Fixes

| Issue | Root Cause | Fix |
|-------|-----------|-----|
| Chat failing silently — no response shown | `strict: true` on tool schemas exceeded Anthropic's 24 optional parameter limit (had 51), causing API rejection | Removed `strict: true` from `AiToolDefinitions.php` |
| Docked chat panel blank messages | Docked mode passed `:content="msg.content"` (string) instead of `:message="msg"` (object) to `AiMessageContent` | Fixed prop binding in `AiChatPanel.vue` |
| Tool input JSON not accumulated during streaming | SDK property is `partialJSON` (capital JSON), code used `partialJson` | Fixed casing in `HasAiChat.php` |

---

## Testing

- **1,947 automated tests pass** (0 failures)
- **Manual browser test confirmed**: prerequisite gate blocks retirement analysis, explains missing data with bullet points, navigates user to profile page
- **No console errors** in browser
- Database seeded after all changes
