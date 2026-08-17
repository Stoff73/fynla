# Fynla Palette → Excalidraw Hex

Derived from `fynlaDesignGuide.md` v1.3.0. These are the **only** colours that should appear in Fynla-authored `.excalidraw` files. Never use Excalidraw's default pastel palette.

## Primary palette

| Token | Role | Stroke hex | Background hex (100-weight) |
|-------|------|-----------|------------------------------|
| `raspberry-500` | CTAs, primary emphasis, agents | `#E83E6D` | `#FCE7EF` |
| `horizon-500`   | Text, nav, controllers, models | `#1F2A44` | `#E4E8F0` |
| `spring-500`    | Success, data-flow, services   | `#20B486` | `#D6F3E8` |
| `violet-500`    | Warnings, focus, rules          | `#5854E6` | `#E1E0FB` |
| `savannah-100`  | Hover, subtle bg, notes         | `#C9C2B7` | `#FDFAF7` |
| `neutral-500`   | Muted text, secondary info      | `#717171` | `#F5F5F5` |
| `eggshell-500`  | Page background                 | `#D4D2CD` | `#F7F6F4` |

## Canvas

`appState.viewBackgroundColor` is always `#F7F6F4` (eggshell-500) so diagrams match Fynla's page chrome when rendered.

## Text

All body text is `#1F2A44` (horizon-500). Arrow labels take the arrow's stroke colour. Titles can optionally use `#E83E6D` (raspberry) for emphasis.

## What's banned

Never use in a Fynla diagram:
- `amber-*`, `orange-*` — banned tokens across the app
- `primary-*`, `secondary-*` — legacy token names
- `gray-*` for general UI — use `horizon-*` or `neutral-*`
- Excalidraw's default pastels (`#bac8ff`, `#b2f2bb`, `#ffc9c9`, etc.)

## Token → semantic kind → shape quick-reference

| Fynla semantic | Kind arg in `compose.py` | Shape | Stroke | Bg |
|----------------|--------------------------|-------|--------|-----|
| User-facing / view | `view` | rectangle | raspberry | raspberry-100 |
| HTTP / controller  | `controller` | rectangle | horizon | horizon-100 |
| Agent (orchestrator) | `agent` | diamond | raspberry | raspberry-100 |
| Service (domain)   | `service` | rectangle | spring | spring-100 |
| Model / DB         | `model` | ellipse | horizon | horizon-100 |
| Warning / rule     | `warning` | rectangle | violet | violet-100 |
| Context / note     | `note` | rectangle | savannah | savannah-100 |
| Highlight / CTA    | `highlight` | rectangle | raspberry | raspberry-100 |

## Font

Excalidraw font family codes (from the schema):

- `1` — hand-drawn (Virgil) — avoid; hard to read at small sizes
- `2` — normal (Helvetica) — fine
- `3` — code (Cascadia) — only for code snippets
- `5` — Helvetica (compose.py default) — clean, widely compatible

Use `5` (the helper's default) unless there's a specific reason.
