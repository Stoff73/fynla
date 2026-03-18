#!/usr/bin/env python3
"""
Post-tool-use hook: reminds Claude to verify work before claiming completion.
Triggers after Edit/Write operations to remind about testing.
"""
import sys

# Count edits in this session (approximate via env)
edit_count_file = "/tmp/claude_edit_count"

try:
    with open(edit_count_file, "r") as f:
        count = int(f.read().strip())
except (FileNotFoundError, ValueError):
    count = 0

count += 1

with open(edit_count_file, "w") as f:
    f.write(str(count))

# Every 10 edits, remind about verification
if count % 10 == 0:
    print(f"[Quality Gate] {count} files edited this session. Remember: browser-test checkpoints before claiming done. Verify sub-agent work before merging.", file=sys.stderr)
