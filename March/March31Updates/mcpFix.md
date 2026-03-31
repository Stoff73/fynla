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

## Root Cause Hypothesis

The upload tool claims to write to paths relative to `~/www/fynla.org/public_html` but:

1. Files may be written to a temporary location or buffer that is not flushed/committed
2. The tool may have a file size limit — the PaymentController is ~630 lines / ~20KB
3. The tool may have a character encoding issue with PHP content (e.g. `<?php` opening tag, special characters like `\u{2192}`)
4. There may be a permission issue where the tool creates files owned by a different user/process that gets cleaned up
5. The write may succeed in a different working directory than expected (though the success message showed the correct full path)

The migration file was attempted **twice** and failed both times — `find ~ -name "*upgrade_from_plan*"` found nothing after both attempts.

---

## Workarounds Used

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

## Recommended Fix

### 1. Investigate the MCP server implementation

Check the SSH MCP server source code. The tool description says:
> "Write content to a file on the Fynla production server via SSH. Creates parent directories automatically."

Verify:
- Is it using `sftp`, `scp`, or piping content through `ssh exec`?
- Is there a content size limit?
- Is the write atomic (temp file + rename) or direct?
- Does it properly handle PHP `<?php` opening tags and Unicode characters?

### 2. Add verification to the tool

The tool should verify the file was written by:
- Checking file size after write matches expected size
- Or reading back a hash of the file content
- Returning an error if verification fails instead of "Successfully wrote"

### 3. Add a test

Create a simple test: write a known file, read it back, compare content. Run this before trusting the tool for deployments.

### 4. Fallback strategy

Until fixed, use `ssh_exec` with heredocs for PHP files, and `rsync` via local Bash for large uploads. The `ssh_exec` tool is reliable.

---

## Impact

- Caused a `BadMethodCallException` on production when the `/api/payment/upgrade` endpoint was hit
- Error message: "Method App\Http\Controllers\Api\PaymentController::upgradeSubscription does not exist."
- Required manual patching via `ssh_exec` (sed, heredoc, python3) to fix
- Added ~15 minutes of debugging and patching to the deployment
