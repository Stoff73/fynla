# Registry — Storage

**Status:** Drafted from discovery 2026-08-13. Shared drives partially unknown.
**Owner:** CSJ. Amendments gated.

Where things live on disk and in the cloud. **Credentials are never here** — see
`access.md`.

---

## 1. Code

| Location | Contents |
|---|---|
| `/Users/CSJ/Desktop/fynla` | The working checkout. Origin `github.com/Stoff73/fynla.git`. |
| `/Users/CSJ/Desktop/fynla/.worktrees/` | 7 registered worktrees, most **prunable** |
| `/Users/CSJ/Desktop/01 Fynla/Code and Worktrees/Linked Worktrees/` | 5 further worktrees. **Not referenced in `CLAUDE.md`** — an organised project folder outside the repo. |

**Housekeeping flagged:** 13 worktrees registered, most reporting prunable —
their directories are gone. Harmless but noisy, and it makes real worktrees hard
to see. `git worktree prune` is a maintenance item, not an emergency; confirm
nothing under `01 Fynla/Code and Worktrees/` is still wanted first.

**Git on this repository is slow.** `git status` alone can exceed two minutes.
Scope commands with pathspecs, expect timeouts, and never leave a lock you cannot
clear.

## 2. Knowledge

| Location | Contents |
|---|---|
| `/Users/CSJ/Desktop/fynlaBrain` | Obsidian vault, **1,514 documents**. The readable knowledge surface. |
| `workforce/core/` | The trunk — doctrine and registry |
| `workforce/branches/` | Derived work, parent-linked to the trunk |
| `workforce/ops/` | State: board, gates, gaps, logs, interviews |
| `.remember/` | `now.md`, `recent.md`, `archive.md`, `today-*.done.md`, `logs/` |

**The vault is a mirror, not a source.** The Archivist writes trunk digests into
it. Where the vault and the trunk disagree, the trunk wins — and the divergence is
a sweep finding.

**Dated update folders** — `April/`, `May/`, `June/`, `July/`, `August/` — hold
session handovers, plans and evidence in both the repo and the vault. Convention:
`<Month><Day>Updates/YYYY-MM-DD-<name>.md`.

## 3. Cloud

| Location | Contents | Access |
|---|---|---|
| **Google Drive** | Marketing pipeline source documents; Word docs become `InsightArticle` | **Google service account** (PR #691) — the workforce uses this, not a separate connector (`tools.md` §5) |
| **AWS S3** | Application file storage | `AWS_*` env keys |
| Google Workspace | **Being set up** (CSJ, 2026-08-13). Until live, everything runs on the personal account, so Meet recording is unavailable and Pattern B is the only meeting mechanism. |

**Unknown, needed:** which shared drives exist beyond the marketing pipeline's
folders, and where Meet recordings and transcripts will land once the Workspace is
live.

## 4. Evidence and artefacts

| Location | Contents |
|---|---|
| `workforce/branches/<type>/<slug>/evidence/` | **Merge evidence packs** (`08-process.md` §2) — permalinked from the PR |
| `.smoke-evidence/<env>-<date>/` | Console and error logs from smoke runs. Existing convention; reuse its shape. |
| `.playwright-mcp/` | Playwright MCP artefacts. Large — 1,600+ entries. |
| `docs/superpowers/{specs,plans}/` | Specs and plans, dated |
| `docs/diagrams/` + `fynlaBrain/Diagrams/` | Excalidraw, written to both |

## 5. Servers

Deploy paths are in `systems.md` §2. Production is a manual upload; csjones is a
git checkout tracking `origin/dev`.

## 6. Never stored in the repo

Live credentials of any kind · `.env` (gitignored, hook-protected) ·
`deploy/*/.env.production.example` are **templates only** — no agent writes a value
into either (`access.md` §5) · customer data · anything from a production database.
