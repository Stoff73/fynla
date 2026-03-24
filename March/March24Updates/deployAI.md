# Deploy Guide — xAI Property Form Fill (24 March 2026)

**Branch:** `grokAI`
**Commits:** af546ca..5d88550 (18 commits)

## What Changed

Separate xAI-optimised tool definitions with strict function calling. Grok now fills in property forms (all types, ownership, mortgages) end-to-end via the Fyn chat assistant.

## Files to Upload

### New File (1)

| File | Server Path |
|------|-------------|
| `app/Services/AI/XaiToolDefinitions.php` | `~/www/fynla.org/public_html/app/Services/AI/XaiToolDefinitions.php` |

### Modified Files (3)

| File | Server Path | What Changed |
|------|-------------|-------------|
| `app/Traits/HasAiChat.php` | `~/www/fynla.org/public_html/app/Traits/HasAiChat.php` | Routes xAI to XaiToolDefinitions, removes double-wrapping, strengthened system prompt for immediate tool calling |
| `app/Agents/CoordinatingAgent.php` | `~/www/fynla.org/public_html/app/Agents/CoordinatingAgent.php` | Expanded handleCreateProperty with all fields (ownership, tenure, costs, BTL, mortgage), null sanitisation, required field defaults |
| `resources/js/components/NetWorth/Property/PropertyForm.vue` | Compiled into `public/build/` | AI fill watcher expanded (32 highlight bindings), property_type early-set fix, scroll error fix |

### Frontend Build Required

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` to server.

## Deploy Steps

1. **Build frontend locally:**
   ```bash
   ./deploy/fynla-org/build.sh
   ```

2. **Upload to server via SiteGround File Manager:**
   - `public/build/` → `~/www/fynla.org/public_html/public/build/`
   - `app/Services/AI/XaiToolDefinitions.php` (new file)
   - `app/Traits/HasAiChat.php`
   - `app/Agents/CoordinatingAgent.php`

3. **SSH and clear caches:**
   ```bash
   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
   cd ~/www/fynla.org/public_html
   php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
   ```

4. **Set AI provider (if switching to xAI on production):**
   - Either set `AI_PROVIDER=xai` in `.env` on server
   - Or use the admin panel AI toggle (instant, no SSH needed)

## .env Variables Required for xAI

```
AI_PROVIDER=xai
XAI_API_KEY=xai-xxxxxxxxxxxx
```

These must be set on the server if switching provider. The admin panel toggle overrides `AI_PROVIDER` via cache.

## No Migrations

No database changes. No seeding required.

## Test Verification

All 5 property scenarios tested end-to-end in browser with xAI:

| Scenario | Type | Ownership | Mortgage | Result |
|----------|------|-----------|----------|--------|
| A | Main residence | Individual 100% | None | PASS |
| B | Main residence | Joint 50% | £300k Halifax repayment 4.2% fixed £1600/mo | PASS |
| C | Secondary residence | TiC 70/30 | £150k Nationwide interest-only 3.8% variable £475/mo | PASS |
| D | Buy-to-let | Joint 50% | £160k Barclays repayment 5.1% fixed £950/mo + tenant | PASS |
| E | Leasehold flat | Individual 100% | None + monthly costs (council tax, service charge, insurance) | PASS |

## Key Technical Details

- **XaiToolDefinitions.php** returns pre-wrapped OpenAI function format with `strict: true`
- Nullable enums use `anyOf` pattern (strict mode requirement)
- 3 tools without strict mode: `create_what_if_scenario`, `update_record`, `update_profile` (dynamic key-value objects)
- xAI returns string `"null"` instead of JSON `null` for nullable fields — sanitised in `executeTool()`
- `property_type` must be set early in `pendingFill` watcher before field sequence starts (Vue select reactivity)
- System prompt instructs Grok to call creation tools immediately, not ask questions first
- `create_property` tool description prevents multi-tool calls in same turn (avoids page navigation interrupting form fill)
- Anthropic path completely untouched — no regression risk
