# Tool-schema golden masters (CoALA Phase 4b)

These 8 `.json` files are the byte-for-byte assembled tool catalogue captured
from `AiToolDefinitions` **before** tool schemas were externalised to
`fyn-memory/procedural/tool_schema/`. `_pointer_baseline.json` records the live
`fetch_*` pointer-tool names at capture time (those are out of 4b scope).

They are IMMUTABLE for the duration of Phase 4b. `ToolSchemaGoldenMasterTest`
asserts the corpus-driven assembly reproduces them exactly. If a fixture needs
to change, the tool catalogue is changing — that is a separate, reviewed change,
not a 4b refactor.

Regenerate only via:

```bash
CAPTURE_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php
```
