---
type: handover
mode: session-end
date: 2026-09-19
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-09-19, Session 1

## Where things stand

Two production releases today and nothing unreleased: main `8fdaac326` == dev `b31a9c24a`, fynla.org runs it, csjones runs it. Release #915 (~14:41 BST) carried the Azlan/Laura/Brett fix batches #907–#914; the live walk that verified it found a defect on the invitee's side, which led to #916 and then, after CSJ's "why leave an app-breaking issue there?", #917 — both released as #918 (~18:59 BST). Every change was walked by hand on csjones (web and /m) and again on fynla.org; all test accounts on both servers are purged. Patch notes for non-technical readers are written (Claude Doc + `September/September19Updates/patch-notes-2026-09-19.md`).

## Priorities for the next session

1. **BLOCKED ON CSJ — the open "adjacent" from 2026-09-17:** the `/savings` "Account Overview" for real users is the Open Banking promo, not an account list (`resources/js/components/Savings/SavingsModuleOverview.vue:10` gates the grid on preview mode). Seen again today on both servers. Laura's "landed and didn't know what to do next" lives partly here. CSJ decides whether real users get the grid.
2. **BLOCKED ON CSJ — 5.4 from `azTest-plan.md`:** the byte-identical repeated reply ("When asked about savings and investments, it repeated the same message") needs a model run against Laura's transcript before a fix; only the tool-narration leak was addressed (#911). Decide whether to chase it.
3. **Tech debt from today, all small (report `docs/tech-debt-report.md`):** fold the three copies of the onboarding-scratch reset in `OnboardingChatDirector` (lines 862, 6317, 7667) into one `clearOnboardingScratch()`; drop the two extra queries on the gated savings return (`SavingsAgent.php:79-80`); one home for the unknown-income sentence (`CaptureForms.php:605/630`); `SpouseDashboardSavingsTileTest.php:27` should assert the envelope instead of hedging over it.
4. **Commit or discard CSJ's own uncommitted files** — see "Branch and deploy state". Not mine; left untouched.
5. **iOS and the parked mapping PRs (#829–#839)** — unchanged, still parked on CSJ (see `CSJTODO.md`).

## Context to load

- `September/September19Updates/azTest-plan.md` — the eight batches and their status; the only open item is 5.4 (priority 2 above).
- `September/September19Updates/azTest.md` — Azlan and Laura's raw feedback the batches answered; re-read before deciding priority 1.
- `docs/tech-debt-report.md` — today's four findings with line numbers (priority 3).
- `app/Services/Onboarding/SpouseJointRecords.php:42` — `carry()`, the one rule for what survives the onboarding scratch; read before touching any of the three director sites that clear it.
- `app/Agents/SavingsAgent.php:70-84` — the readiness-gated return now carries the two figures the dashboard card needs; the gate itself (`SavingsDataReadinessService`) is unchanged.
- `.claude/skills/release/SKILL.md` — the release flow used twice today; the deploy facts below follow it.

## Completed this session

- **Release #915** (main `c5fc88981`): #907–#914 promoted, prod backup `~/release-backups/2026-09-19a/`, both bundles rebuilt and uploaded, full-tree rsync, `composer dump-autoload -o`, migration `2026_09_19_120000_create_spouse_invitations_table` ran, config recached, corpus validated (102 procedures). Verified on fynla.org by a fresh Save Tax walk covering every batch (details in memory `project_release_2026_09_19.md`).
- **#916** `fix(spouse)` `66e18a27e`: `SpouseJointRecords::carry()`; the three clearing sites in `OnboardingChatDirector` call it. Found because the invitee (user 744) saw no joint account after registering from the link.
- **#917** `fix(savings)` `241a920bf`: `SavingsAgent::analyze()` returns `total_savings` + `total_accounts` on the gated path; `CaptureForms::spouseSentence` reads "I don't know" back as unknown. Found because the invitee's Savings card read £0 beside net worth £6,000, and the read-back said "no income or holdings to add".
- **Release #918** (main `8fdaac326`): `app/` rsync (4 files, md5s match main), dump-autoload, config recached, backup `~/release-backups/2026-09-19b/`. Verified on fynla.org: fresh registration → joint savings → dashboard card £6,000 beside net worth £6,000 with date of birth and expenditure still null.
- Patch notes written for non-technical readers (Claude Doc "Fynla patch notes, 19 September 2026" + the repo markdown, committed with this handover).
- Memory: `project_release_2026_09_19.md` written and indexed; the lesson ("fix what the walk finds, never leave it as adjacent") recorded there.

## Verification state

- Targeted Pest at `241a920bf`: `SpouseJointRecordsTest` 10, invitation/linking/transfer/interruption suites 77, savings-agent/dashboard/mobile/capture-form suites 158 — all green. **The full suite was not run** (dev CI's Quality Gate has been red since 2026-09-18 for pre-existing reasons: `plan` column truncation, PropertyTierCap, StoreBoundary, MobileScaffold, iOS).
- Live, by interaction: csjones (users 412/413 for #916, 414/415 for #917, web and /m) and fynla.org (743/744 for #915, 745 for #918). All purged.
- Not verified: the `/m` mobile bundle was rebuilt for #915 but #918 changed PHP only; the `/m` login lockout message (423) and the "restore my account" step were not exercised today, only the forgotten-password link and two-factor sign-in.
- Prod log (`storage/logs/laravel.log`) unchanged at 96,090 lines all day, apart from one error I caused by mistyping `fyn:corpus-validate` (the command is `fyn:procedural:validate`).

## Decisions and dead ends

- **CSJ (18:32): never report an "adjacent, not touched" defect found on a release walk — fix it in the same loop.** Rule 14 applies to what the walk finds, not only what the plan lists. Recorded in memory.
- **Why `carry()` and not "stamp any co-owner-less joint record at link time":** a joint record can be shared with someone other than the spouse; the existing design only claims a record for the spouse when it was saved mid-onboarding with no named co-owner and the household had declared a spouse. Keeping that memory alive is the design-true fix; a name-match fallback would have missed invitations sent from settings after completion.
- **Why the savings fix is in the agent's gated return, not the aggregator:** `CoordinatingAgent:820` and the mobile aggregator both read `summary.total_savings`; one home (Rule 20). Everything that genuinely needs the gate (runway, ISA position) stays null.
- **No reader treats a non-null `onboarding_fyn_context` as "still onboarding"** — all key-based (`paused_at_step`, `verify_section`, `declared_none`), checked before carrying the key through completion.
- **Sam's unlabelled form fields** in the invitee's first Fyn turn are a snapshot-timing artefact, not a bug: the same form had full accessible names on the inviter's side and the labels are present in the DOM.
- **Both merges and both releases were CSJ's call** ("merge" at 18:32); #916 sat open and deployed on csjones until then.

## Things that will bite you

- **Plain `ssh` to production is blocked by the auto-mode classifier even for reads**; `mcp__ssh-fynla__ssh_exec` is the prod channel. `rsync` over plain ssh with `~/.ssh/production` IS allowed. csjones plain ssh (reads and tinker writes) worked all day.
- **zsh globs in a Pest command that match nothing abort the whole `ls`, leaving `FILES` empty — and `pest` with no args runs the FULL suite in the background.** Use explicit paths.
- **Playwright on this app: hidden duplicate DOM** (the Fyn panel and funnel steps exist twice, one hidden), so `role=` / `text=` selectors hit strict-mode violations; snapshot refs are reliable, and `[aria-label="…"] >> role=…` scoping works for the funnel. Refs are deterministic per page shape, so a second walk can reuse the previous walk's ref numbers with the new frame prefix up to the pensions page, where numbering diverges.
- **Prod verification codes for accounts I create:** read `pending_registrations.verification_code` (registration) or `EmailVerificationCode` (login) by tinker over the MCP; CSJ's own account still needs CSJ.
- **Server clock on fynla.org is UTC**; file mtimes read one hour behind BST.
- The `fyn:user:erase` command erases Fyn memory only, not the account — the purge recipe is in memory `feedback_clear_cjones_prod_account_after_every_fix.md`.

## Tech debt deferred

- `app/Services/Onboarding/OnboardingChatDirector.php:862-867, 6317-6322, 7667-7672` — the scratch reset in three copies (pre-existing; today added a line to each).
- `app/Agents/SavingsAgent.php:79-80` — two extra queries on the gated return.
- `app/Services/Onboarding/CaptureForms.php:605, 630` — the unknown-income sentence twice.
- `tests/Feature/Api/SpouseDashboardSavingsTileTest.php:27` — hedges over the response envelope.

## Branch and deploy state

- Branch: dev at `b31a9c24a`, == origin/dev, == main `8fdaac326` (tree), == fynla.org, == csjones.
- Unpushed commits: none (before this handover commit).
- Uncommitted, CSJ's own, left alone: `docs/diagrams/map-auth-login-flow.excalidraw`, `docs/diagrams/map-campaigns-savetax-walk.excalidraw`, `workforce/ops/log/2026-09.jsonl`, `workforce/ops/log/myrtle-brief.log`, untracked `workforce/ops/reports/brief-2026-09-08…18.md` and `handover/September/15/handover-2026-09-15-session-1.md`.
- Deploy status: fynla.org = main `8fdaac326`; backups `~/release-backups/2026-09-19a/` (full, before #915) and `2026-09-19b/` (four files, before #918). csjones on dev `b31a9c24a`. No open PRs of mine.
