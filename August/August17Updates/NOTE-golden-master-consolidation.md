---
type: note
status: needs doing — scoped, not started
date: 2026-08-17
---

# Consolidating the golden-master suites

## Why this is on the list

Editing one tool schema on 2026-08-17 required re-recording golden masters **twice**,
in two separate steps, because there are three suites behind three environment flags
and two of them cover overlapping ground with identically-named fixtures.

The second one was only found by running the wider suite after believing the work was
finished. That is the failure mode worth fixing: **re-recording one leaves the other
red, and nothing tells you the other exists.**

## What is actually there

| Suite | Fixtures | Capture flag |
|---|---|---|
| `tests/Feature/AI/ToolSchemaGoldenMasterTest.php` | `tests/fixtures/ToolSchema/` (9 files) | `CAPTURE_TOOL_SCHEMA_GOLDEN` |
| `tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php` | `tests/fixtures/XaiToolSchema/` (4 files) | `CAPTURE_XAI_TOOL_SCHEMA_GOLDEN` |
| `tests/Feature/AI/PromptOverlayGoldenMasterTest.php` | (prompt overlays) | `CAPTURE_PROMPT_OVERLAY_GOLDEN` |

Three filenames appear in BOTH fixture directories: `getTools_xai_live.json`,
`getTools_xai_preview.json`, `handoffTools_xai.json`. `_pointer_baseline.json` is in
both and is byte-identical.

## They are NOT redundant — measured

Do not "consolidate" by deleting one set. The overlapping files hold the same 45 tools
in **two different shapes**:

```
ToolSchema/getTools_xai_live.json      45 entries, keys: name, description, parameters
XaiToolSchema/getTools_xai_live.json   45 entries, keys: type, function
```

`ToolSchema/` captures the flat logical catalogue; `XaiToolSchema/` captures the xAI
wire format (the OpenAI function-calling wrapper, with `strict`). Both are real
assertions. 149 KB versus 181 KB for the same tools.

## What consolidation should mean

Not fewer assertions — fewer *mechanisms*:

1. **One capture flag**, or one command, that re-records everything. Today a schema
   edit needs two commands and there is nothing telling you so.
2. **One suite** parameterised over (provider, shape), rather than three files with
   copy-pasted encode/compare logic.
3. **Distinct fixture names.** Same-named files in two directories holding different
   content is a trap. Prefix by shape: `flat_getTools_xai_live.json` versus
   `wire_getTools_xai_live.json`, or nest under one root.
4. **A failure message that names the sibling.** If a schema edit invalidates one, the
   message should say the other needs re-recording too.

## Before touching it, read

`tests/fixtures/ToolSchema/README.md` — states the fixtures are IMMUTABLE for
Phase 4b, and that a fixture change means "the tool catalogue is changing — that is a
separate, reviewed change, not a 4b refactor". Both re-records on 2026-08-17 were
deliberate catalogue changes under that clause (CSJ-directed Rule 9 sweep and the
pension schema rewrite), not incidental drift. Any consolidation must keep that
distinction legible, or the gate stops meaning anything.

## Regenerate commands, for reference

```bash
CAPTURE_TOOL_SCHEMA_GOLDEN=1     ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php
CAPTURE_XAI_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php
CAPTURE_PROMPT_OVERLAY_GOLDEN=1  ./vendor/bin/pest tests/Feature/AI/PromptOverlayGoldenMasterTest.php
```

Always re-run without the flag afterwards to confirm the recording actually matches.
