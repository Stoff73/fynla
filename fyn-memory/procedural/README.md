# Procedural memory corpus

CoALA Phase 4 procedural memory. Each `.md` file is one **version** of one
procedure. Files live at:

```
fyn-memory/procedural/{kind}/{module}/{name}.md
```

`pointers/` is a separate subsystem (the pointer registry) and is NOT part of
this corpus — the procedural loader ignores it.

## Kinds

| kind | purpose |
|------|---------|
| `system_prompt_overlay` | per-tier / per-module prompt overlays (Phase 4c) |
| `fca_block` | FCA / house-view narrative blocks (Phase 4c) |
| `tool_schema` | one LLM tool definition, JSON in a fenced block (Phase 4b) |
| `workflow` | onboarding state-machine transition tables (Phase 4d) |

## Frontmatter

```yaml
---
procedure_id: retirement.tool.create_dc_pension   # unique logical id
kind: tool_schema           # system_prompt_overlay | workflow | tool_schema | fca_block
module: retirement          # module slug, or 'global' — MUST match the path
version: 1                  # integer >= 1
active: true                # exactly ONE active version per procedure_id
effective_from: 2026-06-02  # date
# effective_to: 2027-04-05  # optional
---
<markdown body>
```

`module` and `kind` must match the file's directory. Validate locally with:

```bash
php artisan fyn:procedural:validate
```

The corpus is empty until Phase 4b–4d author content. The loader degrades to an
empty/last-good corpus at runtime and never breaks a chat turn; the validate
command is the only place that hard-fails.
