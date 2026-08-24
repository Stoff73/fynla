# Session 2 — Landscape & Resources

**Onboarding session 2 of 9.** Produces `core/registry/`. Blocks almost
everything — agents without the registry work blind.
**Date:** 2026-08-13 · **Interviewer:** Chief of Staff · **For:** CSJ

---

## What I discovered before asking

Git remotes, branches and all 13 worktrees · `deploy/` in full · `.mcp.json` ·
`.claude/settings.json` · `.env.example` key names (names only, never values) ·
`config/` · commit history for Google and marketing work · `config/logging.php` ·
`config/analytics.php` · `.gitignore` vs tracked files

**Already drafted from that, ready for your correction:**
`registry/systems.md` · `registry/tools.md` · `registry/access.md`

---

## Part A — Three findings that change session 1 decisions

Discovery contradicted three things we settled last session. Flagging before
anything gets built on them.

### A1. Plausible analytics exists — Q10 was probably wrong

**Found.** `config/analytics.php`, with `ANALYTICS_ENABLED`, `PLAUSIBLE_DOMAIN`
and `VITE_PLAUSIBLE_DOMAIN` in `.env.example`.

In session 1 I told you the Intelligence lead had *no tooling*, and you agreed to
defer it to Phase 5. That was based on the `data:*` skills being Cowork plugin
skills. I missed that Fynla already runs Plausible, which has a stats API.

**Proposal: revisit.** Plausible covers traffic, funnels and conversion. It does
**not** cover churn, CAC, NRR or Paid Active Households — those need the
application database, which the `mysql` MCP already reaches locally.

So Intelligence may be viable now, with one caveat: Plausible is privacy-first and
deliberately does not do per-user tracking, so cohort retention has to come from
your own database rather than from analytics. **Ship Intelligence in Phase 1, or
hold to Phase 5 as ruled?**

### A2. Google Drive is already integrated — the provisioning request may be moot

**Found.** A Google service-account pipeline is **already merged** — PR #691,
plus `codex/google-drive-marketing-readiness`. It runs the marketing content
pipeline: Word doc in Drive → `InsightArticle`, with real-time Drive triggers,
polling, an approver step and cross-environment publishing.

In §8.5 I said Drive "not connected" and made it a provisioning request for
meeting-transcript ingestion. That was true of the *Claude connector* and false of
*Fynla's own access*.

**Question:** should the workforce read meeting transcripts through the **existing
service account** — no new authorisation, no new spend, consistent with how Fynla
already touches Drive — or through a **separate Claude Drive connector**?

**Proposal: the existing service account**, if its scope permits reading the folder
where Meet recordings land. It avoids a second credential for the same system.

### A3. A marketing pipeline with an approver already exists — Q11 overlaps it

**Found.** The pipeline has "approver sets the article publish date, with a
GA-informed recommendation" and a Stage 5 cross-environment publish.

In Q11 you ratified that **all** public output is gated until `04-voice.md` exists.
There is already a human approval step for marketing articles.

**Question:** is the existing approver step *the* publishing gate for articles — in
which case the workforce should route into it rather than build a parallel one — or
is it a separate editorial step that sits underneath the founder gate?

**Proposal:** route into it. Two approval mechanisms for one artefact is the Rule 20
disease.

---

## Part B — One thing to fix

### B1. `.env.production` files are tracked despite being gitignored

**Checked:** both contain placeholders. **No secret is committed. Not an incident.**

But `.gitignore:14` lists `.env.production` while `git ls-files` shows both files
tracked — ignore rules don't apply retroactively. The protection reads as present
and is not. Anyone filling in real values in place would have them staged.

**Proposal:** `git rm --cached` both, commit, rename to `.env.production.example`.
Until then I've written a standing rule into `access.md` §5: templates only, no
agent writes a value into either.

---

## Part C — What I can't discover

The registry's remaining files are things only you know.

### C1. Communications — **PARTLY ANSWERED 2026-08-13**

> **CSJ:** Slack is `fynla.slack.com`. Only `#all-fynla` and `#social` exist.
> Create the channels, or reduce the number to simplify as much as possible.
>
> **Taken as simplify — hard.** The original design proposed twelve channels for a
> three-person company; most would have carried one message a week. **Written to
> `registry/comms.md`: two new channels, not twelve** — `#fyn-brief` for everything
> the workforce says, `#fyn-decisions` for everything that needs a reply.
> Per-workstream separation comes from threading inside `#fyn-brief`.
>
> **Cannot create them yet** — the Slack connector is unauthorised, and OAuth needs
> an interactive session. Raised as **`ops/provisioning/PR-0001-slack.md`**.
>
> **Resolved 2026-08-13:** `#social` → **renamed `#marketing`**, so it is
> marketing output — in scope for triage and for the publishing gate. WhatsApp
> group **"Fynla 500"** already exists. Google Workspace **being set up**; until it
> is, Pattern B is the only meeting mechanism.
>
> **Still open:** who is on `ADMIN_EMAILS`.
>
> **New finding while drafting:** a bug pipeline already exists —
> `GITHUB_BUG_ISSUE_*` keys plus a commit titled *"mobile bug reporter → GitHub
> issue → autonomous Claude fix loop"*. **A second autonomous fix loop is already
> running.** Same reasoning as the marketing approver: route into it, don't build
> alongside. Needs mapping.

### C1 (original) — what was unknown

I found a Laravel Slack **log** driver (`config/logging.php:76`, env var absent so
almost certainly unconfigured). That's a log sink, not an integration. Everything
else is unknown:

- Does a Slack workspace exist? What are its real channels and what is each for?
- Which channels should the Chief of Staff triage, and which are off-limits?
- What email addresses matter? `ADMIN_EMAILS` exists as a key — who's on it?
- Is there an existing WhatsApp group with the three of you, or is that new?
- Is there anywhere the team currently reports bugs? `GITHUB_BUG_ISSUE_REPO`
  suggests bugs already flow to GitHub issues — is that the channel?

### C2. Storage — `registry/storage.md`

- `/Users/CSJ/Desktop/01 Fynla/Code and Worktrees/Linked Worktrees/` holds 5
  worktrees and is referenced nowhere in `CLAUDE.md`. What else lives under
  `01 Fynla`, and should the workforce know about it?
- Which shared drives exist beyond the marketing pipeline's Drive folders?
- Where do Meet recordings and transcripts land today, if anywhere?

### C3. Meetings — `registry/meetings.md`

Monday 09:00 and Friday 16:00 are ratified. Remaining:

- Which calendar? The connected Google Calendar is `slaterjoneschris@gmail.com` —
  is there a separate Fynla workspace account?
- Are Monday/Friday CSJ-only for now, or do Azlan and Brett join once activated?
- **Meet recording needs Workspace Business Standard or above.** Do you have that?
  If not, Pattern A (recorded Meet → Drive → ingest) isn't available and Pattern B
  (live working session) is the only option — which is what I proposed anyway.

### C4. The `codex/*` toolchain

A large family of `codex/*` branches shows OpenAI Codex working alongside Claude
Code. `CLAUDE.md` doesn't mention it.

**This matters more than it looks.** The workforce is being designed as if it is
the only thing touching this repo. If Codex is opening PRs and pushing branches
concurrently, the Chief of Staff will see work it didn't assign, on items it
doesn't own, and classify it as drift or an anomaly.

- Is Codex still in active use, or is this history?
- If active: does it come under the workforce, run beside it, or should the
  workforce simply be told to expect it?
- Does the evidence gate (`08-process.md`) apply to Codex-authored PRs too?

### C5. Worktree housekeeping

13 registered, most **prunable** — their directories are gone. Harmless but noisy,
and it makes real worktrees hard to see.

**Proposal:** `git worktree prune` as a maintenance item. Confirm nothing under
`01 Fynla/Code and Worktrees/` is still wanted first.

---

## Completeness check

**Drafted and awaiting correction:** `systems.md`, `tools.md`, `access.md`.

**Cannot be drafted without you:** `comms.md` (nothing discoverable),
`storage.md` (partial), `meetings.md` (partial).

**What I'll assume meanwhile:** no Slack workspace is reachable, so triage and the
gate queue stay on the board and in the control centre; Pattern B for meetings;
and Codex activity is treated as expected rather than anomalous, so the Chief of
Staff won't flag every `codex/*` branch as drift.

**Blocking:** C1 blocks Phases 3–4 entirely. A1–A3 should be settled before Phase 1
builds on decisions that discovery has undermined.
