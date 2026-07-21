# 03 — Multi-entity

10 scenarios covering 4 module focuses × 2 phrasings + 2 unknown-provider variants.

Each scenario asserts:

- Per-focus entity-count **recall** ≥ `config('fyn_eval.recall_floor')` (default 95%).
- Per-field **precision** ≥ `config('fyn_eval.precision_floor')` (default 95%).
- **Cross-entity consistency** — no field-bleed between entities in the same message (100% hard fail).
- **Value accuracy** — every monetary value round-trips exactly (100% hard fail).
- **Fabrication rate** — no field invented that the user did not state (0% hard fail).

Source: `fyn-rubrics.md §B` coverage table — "Multi-entity scenarios (4 focuses × 2 phrasings each + 2 unknown-provider)".
