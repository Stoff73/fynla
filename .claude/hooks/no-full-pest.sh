#!/usr/bin/env bash
#
# PreToolUse guard — matcher: Bash.
# CSJ 2026-09-27: NEVER run a full test suite locally. Every pest / phpunit /
# paratest / `artisan test` invocation must name the test FILES it runs
# (tests/...php). Blocked: no file argument, a directory argument only,
# --testsuite, or a loop over suites. CI runs the full suite.
# Deterministic: python3 parses the command; any match emits "deny".

python3 -c '
import json, re, sys

try:
    cmd = json.load(sys.stdin).get("tool_input", {}).get("command", "") or ""
except Exception:
    sys.exit(0)

runner = re.compile(r"vendor/bin/(pest|phpunit|paratest)\b|\bartisan\s+test\b|^\s*(pest|phpunit|paratest)(\s|$)")

for segment in re.split(r"&&|\|\||;|\||\n", cmd):
    if not runner.search(segment):
        continue
    files = re.findall(r"tests/\S+\.php\b", segment)
    if "--testsuite" in segment or not files:
        print(json.dumps({"hookSpecificOutput": {
            "hookEventName": "PreToolUse",
            "permissionDecision": "deny",
            "permissionDecisionReason": (
                "BLOCKED (CSJ 2026-09-27): never run a full or directory-wide test suite locally. "
                "Name the test files the change touches, e.g. ./vendor/bin/pest tests/Unit/Foo/BarTest.php. "
                "CI runs the full suite."
            ),
        }}))
        sys.exit(0)
'
