# 08 — Provider parity

4 scenarios — same conversation, run on Anthropic AND xAI, must produce equivalent behaviour.

Each scenario asserts:

- Identical tool-call set (name + argument shape subset).
- Identical SSE event sequence (types + order; payload values may differ on natural-language fields).
- Identical DB state after the turn (same rows persisted, same field values).
- Equivalent classification (`QueryClassifier` returns the same query type for both providers).

Provider parity is the regression backstop for switching default model or upgrading either provider.

Source: `fyn-rubrics.md §B` coverage table — "Provider parity (same conversation run on Anthropic AND xAI, must match)".
