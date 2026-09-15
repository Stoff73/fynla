# <Section name> — application map

| | |
|---|---|
| Scope | <module / section / flow mapped, and what is explicitly out of scope> |
| Commit | `<short sha>` on `<branch>` |
| Mapped on | <YYYY-MM-DD> |
| Mapped by | <session or agent> |
| Supersedes | <previous map file, or "none"> |
| Issues raised | MB-NN, MB-NN (in `<Month>/<Month><D>Updates/mappingBugs<date>.md`) |

## 1. Overview

### In plain English

<Two to five sentences a non-technical reader can follow. What this section is for, who uses it, what they get out of it. No code identifiers.>

### How it fits together

<One paragraph on the shape: which surfaces reach it, which backend layers do the work, where the data lives, which other modules feed it or depend on it.>

### Flow diagram

- `docs/diagrams/map-<section>-request-flow.excalidraw` — <one sentence: what it shows>
- `docs/diagrams/map-<section>-<flow>.excalidraw` — <one sentence>

### Surfaces

| Feature | Web | `/m` | iOS | Notes |
|---|---|---|---|---|
| <feature> | Working / Unverified / Broken / Dead / Dead end / Not present | | | <evidence ref> |

### Depends on / depended on by

| Direction | Module or service | What crosses the boundary | Evidence |
|---|---|---|---|
| Consumes | | | `path:line` |
| Consumed by | | | `path:line` |

## 2. Detailed sections

<Repeat 2.x for every distinct part of the scope: each screen, each entity, each flow, each Fyn behaviour, each background process.>

### 2.x <Part name>

**In plain English.** <Two to five sentences.>

**Status:** Working / Unverified / Broken / Dead / Duplicate / Dead end — **Evidence:** <test command + result line, Playwright interaction, or `path:line`>

**What it looks like** (only if Playwright-driven)

![<caption>](screenshots/<section>/<surface>-<what>.png)

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Route | `routes/api.php:NN` | |
| 2 | Controller | | |
| 3 | Agent | | |
| 4 | Service | | |
| 5 | Model / DB | | |
| 6 | Response / UI | | |

**Diagram:** `docs/diagrams/map-<section>-<flow>.excalidraw` (only for flows with more than two steps)

**Form fields** (only if there is a form)

| Label shown | Field | Input type | Validation (Form Request line) | Column and type | Default | Surfaces |
|---|---|---|---|---|---|---|

**CRUD**

| Operation | Method and endpoint | Who may call it | Writes | Returns | Evidence |
|---|---|---|---|---|---|
| Create | | policy / tier / preview | | | |
| Read | | | | | |
| Update | | | | | |
| Delete | | | | | |

**Fyn touchpoints**

| Tool or intent | Prompt or catalogue file | What it reads or writes | Evidence |
|---|---|---|---|

**Background machinery**

| Kind | Name | Trigger | Effect | Evidence |
|---|---|---|---|---|
| Job / event / mail / command / schedule | | | | |

**Tests**

| Test file | Covers | Run this session | Result |
|---|---|---|---|
| | | yes / no | <summary line> |

Tests written in this run: <list, or "none">

## 3. Findings

| Id | Status | Where | What is wrong | Evidence |
|---|---|---|---|---|
| MB-NN | Broken / Dead / Duplicate / Dead end / Does not make sense | `path:line` | | |

None found in this run: <state which categories came up empty>

## 4. Coverage and gaps

| Area | Checked | I COULD NOT VERIFY |
|---|---|---|
| Files in perimeter | <n read of n listed> | <what was not read and why> |
| Tests | <n run> | |
| Playwright | <flows driven> | |
| Surfaces | Web / `/m` / iOS | |

## 5. Glossary

| Term | Meaning in this section |
|---|---|
