---
pointer_id: example-pointer
topic: Human-readable topic this pointer covers
triggers: [keyword, another-keyword]   # required for prefetch/both; query words that fire it
mode: both                             # prefetch | tool | both
handler: tax_allowance                 # MUST be a registered FetchHandler id (fail-closed if not)
source_label: TaxConfigService         # human-readable source, for provenance
version: 1
---

Plain-language "when to use" description. Doubles as the LLM tool description in
tool mode. The fetch CODE lives in the named handler — NEVER here. No values, no
numbers, no £ figures (those come from the live source via the handler).
