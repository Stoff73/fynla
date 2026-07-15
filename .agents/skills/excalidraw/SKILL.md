---
name: excalidraw
description: Produce Fynla architecture, module, and flow diagrams as `.excalidraw` JSON files using the Fynla design palette. Writes to both `docs/diagrams/` in the repo and `fynlaBrain/Diagrams/` in the vault, keeps an index, and uses Obsidian-compatible wikilinks so diagrams connect back to vault docs. Use whenever the user says "draw this in excalidraw", "make a diagram", "map the X flow", "diagram the Y module", "visualise Z", "update the excalidraw", "architecture diagram", "flow chart", or when an investigation or planning conversation produces a spatial/relational output that would read better as a diagram than as prose. Also use when the user asks to present or view a flow on their local Excalidraw instance. Diagrams are always written to disk (never inline in the chat) so the user can drag-and-drop them into Excalidraw at `http://localhost:<port>` or open via File → Open.
---

# Excalidraw (Fynla)

Generate `.excalidraw` JSON files that open cleanly in the user's local Excalidraw instance (source at `/Users/CSJ/Desktop/excalidraw-master`). Diagrams use the Fynla design palette so they visually tie to the app, and embed `[[wikilinks]]` in labels so Obsidian backlinks work when opened inside the vault.

**Why a skill:** without one, every diagram is re-derived — palette drift, filename drift, location drift, different shape conventions. The skill locks the conventions so future sessions produce consistent, navigable diagrams.

---

## When to use

Obvious triggers:
- "Draw / diagram / map / visualise / chart / illustrate X"
- "Make an architecture diagram", "flow chart", "sequence diagram", "state machine"
- "Update the excalidraw for …"
- User pastes a spatial description ("A talks to B, B talks to C and D …") — that's a diagram
- User asks to "present this back in excalidraw" or "show this in excalidraw"

Non-obvious triggers (use judgement):
- A long investigation that produced a module graph → offer to diagram it
- A planning conversation that listed phases + dependencies → offer a sequence diagram
- An explanation of Fyn's pipeline / an agent flow / a CRUD path → offer a flow chart
- A bug post-mortem that traced data through 4+ services → offer a trace diagram

Don't diagram: trivial 2-node relationships, code snippets, pure text content, anything where prose is clearer.

---

## Output locations

Always write to **both**:

1. **Repo (source of truth, git-tracked):** `/Users/CSJ/Desktop/fynla/docs/diagrams/<kebab-name>.excalidraw`
2. **Vault mirror (Obsidian-browsable):** `/Users/CSJ/Desktop/fynlaBrain/Diagrams/<kebab-name>.excalidraw`

File names are kebab-case and match the subject:
- `architecture.excalidraw` — the whole-app request flow
- `modules.excalidraw` — the 7 modules + their orchestrators
- `fyn-ai-pipeline.excalidraw` — Fyn chat classifier → renderers
- `auth-flow.excalidraw`, `deploy-pipeline.excalidraw`, `preview-isolation.excalidraw`, etc.
- Per-module detail: `module-protection.excalidraw`, `module-estate-iht.excalidraw`

Update `/Users/CSJ/Desktop/fynlaBrain/Diagrams/Diagrams Index.md` with every new file (one line, with a `[[wikilink]]` and a short description).

Never inline the JSON in the chat. The deliverable is the file.

---

## Palette (Fynla design system v1.3.0)

| Semantic kind | Excalidraw shape | Stroke | Background | Meaning |
|--------------|------------------|--------|------------|---------|
| `view`       | rectangle        | `#E83E6D` raspberry | `#FCE7EF` raspberry-100 | Vue components, user-facing surfaces |
| `controller` | rectangle        | `#1F2A44` horizon   | `#E4E8F0` horizon-100   | HTTP / API layer |
| `agent`      | diamond          | `#E83E6D` raspberry | `#FCE7EF` raspberry-100 | Module orchestrators (ProtectionAgent, etc.) |
| `service`    | rectangle        | `#20B486` spring    | `#D6F3E8` spring-100    | Domain calculators under `app/Services/{Module}/` |
| `model`      | ellipse          | `#1F2A44` horizon   | `#E4E8F0` horizon-100   | Eloquent models / DB tables |
| `warning`    | rectangle        | `#5854E6` violet    | `#E1E0FB` violet-100    | Warnings, rules, non-negotiables |
| `note`       | rectangle        | `#C9C2B7` savannah  | `#FDFAF7` savannah-100  | Context / side notes |
| `highlight`  | rectangle        | `#E83E6D` raspberry | `#FCE7EF` raspberry-100 | Emphasis / CTAs |

Full mapping lives at `references/fynla-palette.md`.

Page background is always `#F7F6F4` (eggshell-500) so diagrams match the Fynla page chrome.

---

## Workflow

Don't hand-write the JSON — use the `scripts/compose.py` helper. It produces deterministic, valid Excalidraw JSON (stable IDs, correct bindings, palette baked in).

### 1. Identify the subject

Decide what you're diagramming. Pick the closest archetype:

| Archetype | Template | Use for |
|----------|----------|---------|
| Layered architecture | `architecture-layers` | Vertical request flow (Vue → API → Controller → Agent → Service → Model) |
| Hub and spoke | `hub-spoke` | Coordinating agent with module agents around it |
| Sequence / flow | `sequence-flow` | Numbered left-to-right steps, auth, onboarding, deploy |
| State machine | `state-machine` | Subscription states, preview mode, feature flags |
| Entity-relationship | `erd` | Database tables and their relationships |

If none fit, compose from scratch — the helper's `Diagram` class is flexible.

### 2. Compose in Python

Prefer writing a short Python block that imports `compose.py` from the skill and emits the `.excalidraw` file:

```python
import sys
sys.path.insert(0, '.Codex/skills/excalidraw/scripts')
from compose import Diagram

d = Diagram(title="Fynla Architecture")
d.label("title", "Fynla Request Flow", x=40, y=40, font_size=24)
d.node("vue", "Vue Component",  x=120, y=120, kind="view")
d.node("api", "API Service",    x=120, y=240, kind="service")
d.node("ctl", "Controller",     x=120, y=360, kind="controller")
d.node("agn", "Module Agent",   x=120, y=480, kind="agent")
d.node("svc", "Domain Service", x=120, y=600, kind="service")
d.node("mdl", "Eloquent Model", x=120, y=720, kind="model")
d.arrow("vue", "api", label="HTTP")
d.arrow("api", "ctl")
d.arrow("ctl", "agn")
d.arrow("agn", "svc")
d.arrow("svc", "mdl", label="Eloquent")
d.save("docs/diagrams/architecture.excalidraw")
```

Then copy the file to the vault mirror:

```bash
cp docs/diagrams/architecture.excalidraw \
   /Users/CSJ/Desktop/fynlaBrain/Diagrams/architecture.excalidraw
```

### 3. Embed wikilinks (for Obsidian backlinks)

When a node represents something that has a vault doc, pass `link="[[Home]]"`-style Obsidian links via the `link=` argument. They render as clickable links in Excalidraw AND fire Obsidian's backlink index when the file is inside the vault.

```python
d.node("protection", "ProtectionAgent", x=200, y=300, kind="agent",
       link="[[Protection]]")
```

### 4. Update the Diagrams Index

After writing a new diagram, append a line to `/Users/CSJ/Desktop/fynlaBrain/Diagrams/Diagrams Index.md`:

```markdown
- [[Diagrams/architecture|architecture]] — Whole-app Vue → Agent → Service → Model request flow
```

### 5. Report back

Tell the user:
- The two paths (repo + vault)
- How to open it: "Drag-and-drop into your local Excalidraw at `http://localhost:<port>`, or File → Open"
- If they run the vault-based Obsidian Excalidraw plugin, the file is already browsable from `fynlaBrain/Diagrams/Diagrams Index`

---

## Templates

`templates/` contains five archetype starters as plain `.excalidraw` files. Use them as reference reading; don't treat them as "the diagram" — always regenerate fresh via `compose.py` so the result reflects the actual Fynla state, not stale template content.

- `architecture-layers.excalidraw` — vertical stack
- `hub-spoke.excalidraw` — coordinating hub with satellites
- `sequence-flow.excalidraw` — numbered left-to-right
- `state-machine.excalidraw` — nodes + transitions with labels
- `erd.excalidraw` — boxes with cardinality

---

## References

- `references/excalidraw-schema.md` — JSON element types, bindings, frames, grouping, minimum required fields
- `references/fynla-palette.md` — hex values for every token in the Fynla design system
- `references/obsidian-embedding.md` — how to embed an `.excalidraw` in an Obsidian markdown note (Obsidian Excalidraw plugin syntax) and how wikilinks inside diagrams behave

---

## Rules

- **One diagram per file.** Don't stuff multiple concerns into one canvas — users lose the thread.
- **Always write to both locations.** Repo first (source of truth), vault second (Obsidian browsing).
- **Palette discipline.** No raw hex outside the Fynla palette. No Excalidraw pastel defaults.
- **Stable IDs.** The helper derives deterministic IDs from the `id` you pass to `.node()`. Reuse the same id in a future diagram edit to preserve Excalidraw's internal version tracking.
- **Don't hand-edit the JSON.** Use `compose.py`. Hand edits lose binding integrity and the diagram renders with broken arrows.
- **Don't diagram code syntax.** Flow, relationships, phases — yes. Function bodies, configuration dumps — no.
- **Update the index.** Every new file gets a line in `Diagrams/Diagrams Index.md`. Otherwise the graph stays hidden.
- **No emojis in labels.** Excalidraw renders most of them as tofu boxes in the default font.
