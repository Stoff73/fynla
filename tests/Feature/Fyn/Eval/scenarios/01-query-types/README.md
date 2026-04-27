# 01 — Query types

22 scenarios covering one canonical example per `QuerySchemas` query-type constant.

Each scenario asserts:

- The `QueryClassifier` chose the correct query type.
- `AdviceFyn::classifyResponseMode` returns the expected mode (`factual` / `recommendation` / `out_of_remit`).
- `AdviceFyn::engineCallLevel` returns the expected level (`holistic` / `module` / `factual`).
- The SSE event sequence matches the mode (factual → no `advice_response`; recommendation → exactly one `advice_response`).
- No write tools are called.

Source: `fyn-rubrics.md §B` coverage table — "Query types (22 types × one per)".
