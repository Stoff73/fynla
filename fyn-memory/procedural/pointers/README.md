# Pointer Registry (v0.5)

This directory is the heart of procedural memory for the CoALA pointer model.

## What lives here

Each `.md` file in this directory is a **pointer** — a routing declaration that tells
Fyn which live data source answers a given topic. Pointers are markdown-with-frontmatter;
they contain ROUTING ONLY. No fetch code, no values, no numbers, no figures.

## What does NOT live here

Fetch logic and live values belong in code. The frontmatter `handler` field names a
registered `FetchHandler` id. The loader is **fail-closed**: if the named handler is not
registered in code, `PointerRegistry::all()` THROWS and `php artisan fyn:pointers:reindex`
exits non-zero. A pointer that references a not-yet-built handler does NOT sit inert — it
HARD-BREAKS corpus load, which degrades the whole chat tool catalogue. Therefore you must
add the code handler (and bind it in `AppServiceProvider`) BEFORE shipping a markdown
pointer that references it.

## The three modes

| Mode | Behaviour |
|------|-----------|
| `prefetch` | The pointer's triggers are matched against the incoming query; on a match the handler is called and its result is injected into a `<live_data>` context block before the LLM sees the turn. |
| `tool` | The pointer is exposed to the LLM as a callable tool (description = the pointer body text). The LLM calls it on demand. |
| `both` | Both behaviours apply. |

## How to contribute

- **Add or change routing** — edit or add a `.md` file in this directory; submit a markdown PR.
  No code change required.
- **Add NEW fetch capability** — register the `FetchHandler` in code and bind it in
  `AppServiceProvider` FIRST (a dev PR), then add the markdown pointer that references it.
  Shipping the markdown pointer before its handler exists is fail-closed: corpus load throws
  and `fyn:pointers:reindex` fails (non-zero exit). The pointer is not inert — it breaks load.

## Template

Use `_TEMPLATE.md` as the starting point for any new pointer.

## Frontmatter fields

| Field | Required | Notes |
|-------|----------|-------|
| `pointer_id` | yes | Unique kebab-case identifier |
| `topic` | yes | Human-readable description |
| `triggers` | yes (prefetch/both) | List of query keywords that fire this pointer |
| `mode` | yes | `prefetch`, `tool`, or `both` |
| `handler` | yes | Registered FetchHandler id — loader fails closed if unrecognised |
| `source_label` | yes | Human-readable source label for provenance |
| `version` | yes | Integer; increment on breaking changes |
