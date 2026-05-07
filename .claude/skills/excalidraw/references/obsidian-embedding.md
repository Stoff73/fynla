# Obsidian ↔ Excalidraw integration

How `.excalidraw` files inside `fynlaBrain/Diagrams/` behave when opened from Obsidian, and how to embed them in vault notes.

## Prerequisite

The user must have the **Obsidian Excalidraw plugin** installed in the `fynlaBrain` vault. Without it, Obsidian treats `.excalidraw` files as opaque JSON and won't render them.

Check: `fynlaBrain/.obsidian/plugins/obsidian-excalidraw-plugin/` — if the directory exists, the plugin is installed.

## Embedding a diagram in a vault note

Inside any `.md` file in the vault:

```markdown
![[Diagrams/architecture]]
```

That renders the Excalidraw canvas inline inside the note, at the size set by the plugin's default. Add a width spec:

```markdown
![[Diagrams/architecture|800]]
```

## Wikilinks inside a diagram

`compose.py`'s `node(..., link="[[Target]]")` writes the link to the Excalidraw element's `link` field. Inside the Obsidian plugin:
- Clicking the node navigates to `Target.md` in the vault
- Obsidian's backlinks pane records the diagram as a source of the link
- Broken wikilinks are flagged in red (same behaviour as in markdown)

HTTP URLs, `file://` paths, and external links all work too — the plugin opens them in-app or in the default browser depending on type.

## Linking to a diagram from a markdown note

Standard Obsidian wikilink to the `.excalidraw` file:

```markdown
See the [[Diagrams/architecture]] for the whole-app request flow.
```

Clicking it opens the diagram inside Obsidian (embedded canvas if the plugin is active, raw JSON otherwise).

## Diagrams Index pattern

`fynlaBrain/Diagrams/Diagrams Index.md` is the MOC for all diagrams. Format:

```markdown
---
tags:
  - moc
  - diagrams
---

# Diagrams

Back to [[Home]]

## Architecture

- [[Diagrams/architecture|architecture]] — Whole-app request flow
- [[Diagrams/modules|modules]] — 7 modules + their orchestrators

## Flows

- [[Diagrams/fyn-ai-pipeline|fyn-ai-pipeline]] — Fyn chat classifier → renderers → agents
```

Link this index from `Home.md` under a `## Diagrams` section.

## Non-Obsidian use

If the user opens a `.excalidraw` file directly in the local Excalidraw web app (e.g. `http://localhost:3000` served from `/Users/CSJ/Desktop/excalidraw-master`), wikilinks render as plain text in the label — they don't become clickable because Excalidraw on its own has no vault to resolve them against.

That's OK. The canonical viewer for Fynla diagrams is Obsidian with the Excalidraw plugin. The standalone Excalidraw web app is a fallback for quick viewing without linkthrough.
