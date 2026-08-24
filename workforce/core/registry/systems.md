# Registry — Systems

**Status:** Drafted from discovery 2026-08-13, session 2. Awaiting CSJ correction.
**Owner:** CSJ. Amendments gated.

---

## 1. Repository

| | |
|---|---|
| Origin | `https://github.com/Stoff73/fynla.git` |
| Working checkout | `/Users/CSJ/Desktop/fynla` |
| Protected branches | **None.** `CODEOWNERS` and branch protection removed, session 1 Q2. Merge authority is the evidence gate (`08-process.md`). |

### Branches

`main` → production. `dev` → staging. Feature branches off `dev`.
Contributor prefixes in `CLAUDE.md`: `feature/icecube/<task>` for `icecube-acc`;
none required for `Phailanx`.

**Observed but undocumented:** a large family of `codex/*` branches, indicating a
second agent toolchain (OpenAI Codex) working in parallel. Not described anywhere
in `CLAUDE.md`. See session 2 questions.

## 2. Environments

| | Production | Dev / staging |
|---|---|---|
| URL | `https://fynla.org` | `https://csjones.co/fynla` |
| Branch | `main` | `dev` |
| SSH | `ssh.fynla.org:18765` as `u2783-hrf1k8bpfg02` | `ssh.csjones.co:18765` as `u163-ptanegf9edny` |
| Server path | `~/www/fynla.org/public_html/` | `~/www/csjones.co/fynla-app/` |
| Deploy method | Manual upload | `git pull origin dev` + upload `public/build/` |
| Build script | `./deploy/fynla-org/build.sh` | `./deploy/csjones-fynla/build.sh` |

**Never cross them.** Different `VITE_BASE_PATH` / `RewriteBase`; the wrong
combination breaks routing silently. The `ssh-fynla` MCP tool is **production
only** — using it against csjones is forbidden and currently unenforced
(`charter.md` §8, moving to a hook in Phase 1).

## 3. Clients

| Surface | Source | Build |
|---|---|---|
| Desktop web SPA | `resources/js/` | `deploy/*/build.sh` |
| `/m` mobile web | `resources/mobile/` | same |
| iOS — Capacitor (legacy) | shared views | `./deploy/mobile/build-ios.sh` |
| iOS — native SwiftUI (successor) | — | `deploy/mobile-native/{build,archive,verify-archive}.sh` |

Rule 19: work covers web **and** `/m` unless CSJ excludes it. iOS cannot be
E2E-automated (`08-process.md` §3).

## 4. Stack

Laravel 10 · Vue 3 · MySQL 8 · Vite · Pest · Pint (PSR-12).
Local: `./dev.sh`. Tests: `./vendor/bin/pest`. Format: `./vendor/bin/pint`.

**Never** `migrate:fresh`, `migrate:refresh`, or `db:wipe` — hook-enforced.
Reseed with `php artisan db:seed`.

## 5. Worktrees — housekeeping flagged

13 registered. Most report **prunable**, i.e. their directories are gone.

Two locations are in use, only one of which was known:

- `/Users/CSJ/Desktop/fynla/.worktrees/` — 7 entries
- `/Users/CSJ/Desktop/01 Fynla/Code and Worktrees/Linked Worktrees/` — 5 entries,
  **an organised project folder not referenced anywhere in `CLAUDE.md`**

Also: the main checkout currently sits on `codex/psa-joint-interest-share`, not
`dev`. Noted, not judged.
