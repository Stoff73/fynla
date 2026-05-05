# 05 — Cancel / timeout in capture

3 scenarios covering SSE abort, daily-token-limit cut-off, and provider timeout mid-capture.

Each scenario asserts:

- Mid-stream abort writes an `AiAbortEvent` row with `last_tool_call` + `partial_write_count` (BS-18 contract).
- Already-persisted entities remain on the DB (no rollback) — partial writes are kept, the user can resume.
- No fabricated success message when the abort happens before the assistant text streams.
- Token-limit cut-off emits the `usage_limit_reached` SSE class and frontend pins input disabled.

Source: `fyn-rubrics.md §B` coverage table — "Cancel / timeout in capture".
