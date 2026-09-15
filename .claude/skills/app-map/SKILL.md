---
name: app-map
description: Use when CSJ asks to map, document or explain how a section, module, feature, flow or surface of Fynla actually works — "map the X module", "map the application", "/app-map <scope>", "overview map", "what does this section actually do", "update the map for X" — or when a report must describe real code behaviour for a reader who may be non-technical. Also use when re-mapping after a change to keep docs/app-map/ current.
---

# App Map

Evidence-only documentation of one scope of Fynla at a time. **Every statement in a map traces to a file and line you read in this run, a test you ran in this run, or a Playwright interaction you performed in this run. Anything else is written as "I COULD NOT VERIFY".**

The reader may be a non-technical outsider. The map must let them understand what the section does, what it looks like, how data moves, and what is broken, dead or duplicated — without trusting anything the author did not check.

## When to use

- CSJ says map / document / explain a section, module, flow, surface or the whole app
- `/app-map <scope> [focus]` — focus narrows to `forms`, `crud`, `api`, `fyn`, `surfaces`, `jobs`, `tests`
- A map exists in `docs/app-map/` and the code has changed since its stamped commit

Not this skill: codebase-wide debt (`tech-debt-full`), the capability registry (`cartographer` agent), diagram mechanics (`excalidraw` skill, which this skill calls).

## Order of work

**The overview map comes first** (`docs/app-map/00-overview.md`) and is the baseline every section map links back to. Sections follow in the order listed in `docs/app-map/INDEX.md`.

## Process

### 0. Preflight

1. `vault-context <module>` — load prior knowledge. It is **context, not evidence**. Nothing from the vault goes into the map unless re-confirmed in code this run.
2. Read `docs/app-map/INDEX.md` and any existing map for the scope. Read the scope's entry in `workforce/core/registry/capabilities.md`.
3. Record the stamp: `git rev-parse --short HEAD`, branch, today's date. Every report carries it.

### 1. Enumerate the perimeter — from the routes outward

Build the file list before reading anything in depth. Every layer, every surface:

| Layer | Where to look |
|---|---|
| Routes | `routes/api.php`, `routes/web.php`, mobile and native route files; `php artisan route:list --path=<endpoint>` |
| Backend | Controller → Form Request → Agent → Services → Models → migrations → Resources/projections |
| Auth and tiers | Policies, middleware, `PreviewWriteInterceptor::EXCLUDED_ROUTES`, `TierResolver`, `PremiumEntitlementResolver`, tier caps in `tier_configurations` |
| Web SPA | `resources/js/router` → views → components → API service → Vuex store |
| `/m` | `resources/mobile/` router → views → api → store (isolated bundle, check separately) |
| iOS | `ios-native/` views and the `/api/v1/native/*` endpoints they call |
| Fyn | tool catalogues, prompts, context assembler, capture paths, recommendation services touching this scope (load `fyn-architecture` first) |
| Background | Jobs, listeners, events, notifications, mail, scheduled tasks, `php artisan list` custom commands |
| Data | Tables, columns, relationships, seeders; existing ERDs in `docs/diagrams/erd-*.excalidraw` |
| Tests | `tests/Unit`, `Feature`, `Integration`, `Browser/scenarios/BS-NN-*`, Vitest specs, `ios-native` tests |

Command starters (adapt the path):

```bash
git ls-files 'app/**/*Protection*' 'app/Services/Protection/**' 'resources/js/**/protection/**' 'resources/mobile/**/protection/**'
grep -rn "Protection" routes/ | grep -v "^//"
php artisan route:list --path=protection
grep -rln "protection" app/Services/AI/ fyn-memory/ | head
```

### 2. Read every file in the perimeter

Read, do not skim. For each file record: what it does (from the code, not the class name), what calls it, what it calls, which surfaces reach it. Claims cite `path:line`. Sub-agents (`Explore`) may build the perimeter list; **the map author reads the files that back each claim.**

### 3. Reverse sweep — what the forward trace did not reach

For every file under the scope's folders that step 2 never reached:

1. Grep for usages: class name, import path, route name, component name, Vue async import string, artisan signature, config key, Fyn tool name, event name.
2. Nothing found → candidate **Dead**. Check the dynamic paths before confirming: route model binding, `app()->make`, config-driven class maps, Vue `defineAsyncComponent` strings, Livewire/Blade includes, tool catalogues, seeders.
3. Two implementations of one job (two calculators, two formatters, a web and `/m` copy of Fyn behaviour) → **Duplicate**, with both paths cited and which one the routes actually use.
4. A route, button, link, emitted event or Fyn intent that leads nowhere → **Dead end**, with the exact break point.

### 4. Classify with evidence

| Status | Earned only by |
|---|---|
| **Working** | A named test passed in this run (paste the command and result), or you drove it in Playwright this run (click, fill, submit, verify) |
| **Unverified** | Code read and traced end to end, but not executed. Say why not. |
| **Broken** | A failing test, a Playwright failure, a runtime error, or a trace that provably cannot complete (cite the break) |
| **Dead** | Reverse sweep found no reachable path after the dynamic-path checks |
| **Duplicate** | Two implementations of one job, both cited |
| **Dead end** | Reachable, but the flow stops short of its outcome |

Never write Working from a green suite you did not run, a vault note, a commit message, or a class name.

### 5. Tests per section

1. List the existing tests that cover this scope, by file. Listing is not judging.
2. Where a section's contract has no test, write one: Pest for backend behaviour, a Playwright goal for user-visible flows. Follow `tests/CLAUDE.md` and load `test-failure-forensics` first. Tests for different sections will differ; that is expected.
3. Run only this scope's tests (`./vendor/bin/pest <path>`), never the full suite. Paste the summary line into the report.

### 6. Playwright and screenshots

Only for sections whose verification involves a Playwright run-through (forms, wizards, dashboards, surfaced recommendations, Fyn turns). Log in with the local verification-code command in `CLAUDE.md`. For `/m`, load `verify-m` — a cold navigation does not authenticate.

Screenshot every form as the user sees it, every validation state you trigger, and every surfaced recommendation or chart. Save to `docs/app-map/screenshots/<section>/<surface>-<what>.png` and embed with a one-line caption. A screenshot is evidence for "what it looks like" only; the interaction log is evidence for "it works".

### 7. Diagrams

**All overview diagrams and all workflow or flow diagrams are Excalidraw**, produced with the `excalidraw` skill (its palette, its file locations, its index). Name them `map-<section>-<flow>.excalidraw`. The report links each file and states in one sentence what it shows. Tables stay tables; do not diagram a field list.

Each section map has at least: one request-flow diagram (surface → route → controller → agent → services → models) and one diagram per user flow with more than two steps. The overview map has the whole-app flow, the module graph, and the cross-module dependency graph.

### 8. Write the report

Copy `report-template.md` from this skill's folder. Every heading is REQUIRED; a heading with nothing to say gets "None found in this run" or "I COULD NOT VERIFY: <why>". Save as `docs/app-map/<NN>-<section>.md`.

Writing rules for the non-technical reader:

- Each section opens with **In plain English** — two to five sentences, no code identifiers.
- Spell out every acronym on first use in each report. No scores or ratings (Rule 12).
- Technical detail follows in tables. Prose stays short; identifiers go in tables or code spans.
- Forms: one row per field — label as shown, field name, input type, validation rule with its Form Request line, column and type, default, which surfaces show it.
- CRUD: one row per operation — endpoint, method, who may call it (policy, tier, preview), what it writes, what it returns.
- Surface parity: one row per feature with Web, `/m`, iOS columns; each cell is a status from step 4.

### 9. Raise what does not make sense — do not fix it

Anything Broken, Dead, Duplicate or Dead end, and anything that traces cleanly but makes no sense, goes to the day's updates folder:

```
<Month>/<Month><D>Updates/mappingBugs<YYYY-MM-DD>.md     e.g. September/September14Updates/mappingBugs2026-09-14.md
```

Append, never overwrite. One entry per issue:

```
### MB-<NN> — <one-line title>
Map: docs/app-map/<file>.md § <section>
Status: Broken | Dead | Duplicate | Dead end | Does not make sense
Evidence: path:line, test output, or screenshot path
What is wrong: <two or three sentences>
Suspected impact: <who or what is affected>
Decision needed: yes/no — <the question if yes>
```

The map links each finding to its MB id. Fixing is a separate task CSJ assigns; the mapping run reports.

### 10. Close the run

1. Add or update the row in `docs/app-map/INDEX.md`: section, file, status (`overview` / `mapped` / `stale` / `not started`), commit stamp, date, diagram links, MB ids raised.
2. Run `vault-sync` at session end so the vault mirror and the Diagrams Index pick up the new files.

## Rationalisations that produce invented maps

| Thought | Reality |
|---|---|
| "The class is called `X`, so it does X" | Names lie. Read the body and cite the line. |
| "The vault doc describes it" | Vault is context. Re-confirm in code this run. |
| "The pattern elsewhere is X, so this module is X" | Modules drift. Trace this one. |
| "A test file exists, so it works" | Existing ≠ run ≠ passing ≠ covering the claim. Run it, paste the result. |
| "Grep found no callers, so it is dead" | Check the dynamic paths in step 3 first. |
| "I'll fix this while I'm here" | Raise an MB entry. Report, do not fix. |
| "Web works, so `/m` works" | Isolated bundle. Trace and test `/m` separately. |
| "Mermaid is quicker for this flow" | Flow diagrams are Excalidraw. |
| "This section is too big; I'll summarise the rest" | Split the section into two maps and say the second is not started. |

## Quick reference

| Need | Where |
|---|---|
| Report shape | `report-template.md` in this folder |
| Reports | `docs/app-map/<NN>-<section>.md`, index `docs/app-map/INDEX.md` |
| Screenshots | `docs/app-map/screenshots/<section>/` |
| Diagrams | `excalidraw` skill → `docs/diagrams/map-<section>-<flow>.excalidraw` |
| Issues | `<Month>/<Month><D>Updates/mappingBugs<YYYY-MM-DD>.md`, ids `MB-NN` |
| Login code | the tinker one-liner in the root `CLAUDE.md` |
| `/m` auth | `verify-m` skill |
