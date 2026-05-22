#!/usr/bin/env bash
#
# PostToolUse hook — auto-formats PHP files with Laravel Pint (PSR-12)
# immediately after Claude edits or writes them. Non-blocking and
# fail-open: any error here must never interrupt the workflow.
#
# Only acts on existing *.php files. Everything else is a silent no-op.

set -euo pipefail

PROJECT_DIR="/Users/CSJ/Desktop/fynla"
PINT="$PROJECT_DIR/vendor/bin/pint"

input="$(cat 2>/dev/null || true)"

file="$(printf '%s' "$input" | jq -r '.tool_input.file_path // .tool_response.filePath // empty' 2>/dev/null || true)"

# Bail quietly on: no file, not a .php file, file missing, pint missing.
case "$file" in
  *.php) ;;
  *) exit 0 ;;
esac
[ -f "$file" ] || exit 0
[ -x "$PINT" ] || exit 0

# Explicit path => Pint formats this file regardless of dir scope.
( cd "$PROJECT_DIR" && "$PINT" "$file" -q ) >/dev/null 2>&1 || true

exit 0
