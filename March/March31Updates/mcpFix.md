# MCP SSH Tool Failures — 31 March 2026

## Problem Summary

The `mcp__ssh-fynla__ssh_upload_file` tool reported "Successfully wrote" for multiple files but the files were **not actually persisted** on the production server. This caused a `BadMethodCallException` on production when the upgrade route was hit.

---

## Affected Tool

**Tool:** `mcp__ssh-fynla__ssh_upload_file`
**MCP Server:** `ssh-fynla`
**Config file:** `.mcp.json` (local project root)

---

## Failure Details

### Files That Failed to Write (reported success, but did not persist)

| File | Tool Response | Actual Result |
|------|--------------|---------------|
| `app/Http/Controllers/Api/PaymentController.php` | "Successfully wrote ~/www/fynla.org/public_html/app/Http/Controllers/Api/PaymentController.php" | File NOT updated — old version remained, missing `upgradeSubscription` method |
| `app/Models/Payment.php` | "Successfully wrote ~/www/fynla.org/public_html/app/Models/Payment.php" | File NOT updated — missing `upgrade_from_plan` in fillable |
| `app/Models/Subscription.php` | "Successfully wrote ~/www/fynla.org/public_html/app/Models/Subscription.php" | File NOT updated — missing `status` and `revolut_order_id` in fillable |
| `database/migrations/2026_03_31_144649_...php` | "Successfully wrote ~/www/fynla.org/public_html/database/migrations/..." (twice!) | File NOT found on server — `find ~ -name "*upgrade_from_plan*"` returned nothing |

### Tool That Worked Fine

| Tool | Notes |
|------|-------|
| `mcp__ssh-fynla__ssh_exec` | All shell commands worked correctly (sed, cat, grep, artisan, python3) |
| `mcp__ssh-fynla__ssh_read_file` | Reading files worked correctly |
| Local `rsync` via Bash tool | Frontend build upload worked correctly |

---

## Root Cause — CONFIRMED

**The tilde (`~`) in the path was not being expanded because it was inside single quotes.**

In `mcp-servers/ssh/server.mjs` line 197 (old code):
```js
const cmd = `mkdir -p '${dir}' && cat > '${filePath}' << 'MCPEOF'\n${args.content}\nMCPEOF`;
```

When `filePath` = `~/www/fynla.org/public_html/app/Models/Payment.php`, the single quotes around `'${filePath}'` prevent shell tilde expansion. The shell interprets `~` as a literal character, creating a directory literally named `~` inside the CWD.

**Proof:** After the failed uploads, a literal `~` directory tree was found on the server:
- `~/~/www/fynla.org/` (inside the home directory, from `ssh_exec`'s `cd ~/www/... &&` prefix)
- `./~/www/` (from the CWD)

All "successfully written" files went into this phantom directory tree, not the real application directory. The tool reported success because `cat >` returned exit code 0 (writing to the wrong path still succeeds).

**Why `ssh_exec` worked fine:** Line 162 uses `cd ${cwd}` with the tilde **unquoted**, so tilde expansion works. Only the upload tool's single-quoted paths were broken.

---

## Fix Applied

**File:** `mcp-servers/ssh/server.mjs`

### Change 1: Add `resolveTilde()` helper (line 38)
```js
function resolveTilde(p) {
  return p.startsWith("~/") ? `$HOME/${p.slice(2)}` : p;
}
```
Replaces `~` with `$HOME` which expands correctly inside double quotes.

### Change 2: Fix `ssh_upload_file` handler
- Resolve tilde before using the path
- Use double quotes instead of single quotes for `mkdir -p` and `cat >`
- Use unique heredoc delimiter (`MCPEOF_${Date.now()}`) to avoid content collisions
- Increased timeout from 15s to 30s for larger files
- **Added write verification**: after writing, checks file exists and has non-zero size
- Returns byte count in success message

### Change 3: Fix `ssh_read_file` handler
- Added `resolveTilde()` to the read path
- Added double quotes around file path in `cat` and `tail` commands

---

## Workarounds Used (before fix)

### For small changes (1-3 lines):
```bash
# sed insertion
ssh_exec: sed -i 's/old_text/new_text/' path/to/file.php
```

### For new files:
```bash
# heredoc via ssh_exec
ssh_exec: cat > path/to/file.php << 'EOF'
<?php
// file content
EOF
```

### For multi-line patches:
```bash
# Python script via ssh_exec
ssh_exec: cat > /tmp/patch.py << 'PYEOF'
# Python find-and-replace script
PYEOF
python3 /tmp/patch.py
```

### For large binary/asset uploads:
```bash
# rsync via local Bash tool (not MCP)
rsync -avz --delete -e "ssh -p 18765 -i ~/.ssh/production" \
  /local/path/ user@ssh.fynla.org:~/remote/path/
```

---

## Status: FIXED

All three issues have been fixed in `mcp-servers/ssh/server.mjs`:
1. Tilde resolution via `resolveTilde()` helper
2. Write verification (file existence + byte count check)
3. Double-quoted paths throughout

The MCP server needs to be restarted for changes to take effect (restart Claude Code or the MCP server process).

### Remaining recommendation: rsync for large uploads

For bulk uploads (e.g. the entire `public/build/` directory with 289 files), `rsync` via the local Bash tool is still the best approach — it handles delta transfers, permissions, and deletions. The MCP upload tool is designed for individual file writes, not bulk directory syncs.

---

## Impact

- Caused a `BadMethodCallException` on production when the `/api/payment/upgrade` endpoint was hit
- Error message: "Method App\Http\Controllers\Api\PaymentController::upgradeSubscription does not exist."
- Required manual patching via `ssh_exec` (sed, heredoc, python3) to fix
- Added ~15 minutes of debugging and patching to the deployment
