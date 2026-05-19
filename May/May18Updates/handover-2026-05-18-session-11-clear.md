---
type: handover
mode: context-clear
date: 2026-05-18
session: 11
branch: fynPromptRework
trigger: context-handover skill (tripwire ~93% of 250k budget)
---

# Context Clear Handover — 2026-05-18, Session 11

## Immediate state

All requested work is COMPLETE, browser-verified GREEN, committed, and
pushed. Tree is clean, `fynPromptRework` is 0/0 vs origin. Nothing in
flight. PR #335 now carries 4 new bug-fix/doc commits on top of where
session 10 left it. Session was a Fyn-chat bug-fix loop (Rule #15),
interrupted only by the context tripwire after everything was done.

## The thread

- Auto-resumed handover-10 → ran vault-sync (Haiku subagent). Subagent
  left `May18.md` stale at 2 commits (actual 13); I corrected it +
  reconciled monthly index/Home.md (table total 257→268). Flagged a
  pre-existing index-methodology gap (raw git 350 vs curated 268) — NOT
  fixed, just reported.
- CSJ interrupted with a real Fyn bug (3-message conversation pasted).
  Ran systematic-debugging. **Root cause (exactly CSJ's hypothesis):**
  client-side `chatNavigationRouter.js` hijacked a *question* to Fyn
  ("so why cant you give me a personalised view when I ask about the
  protection for my savings?") because it contained bare substring
  "view" + keyword "protection" → `AiChatPanel.vue:1045` `return`ed
  before `sendMessage()`; the LLM never saw the message.
- Fixed at the router (kept it — `navigate_to_page` is stripped
  server-side in advice mode per `AdviceFyn.php:175`, so removing the
  router would regress advice-mode nav). Rejected approach: ripping out
  the router (over-complication, regresses nav).
- CSJ then asked for 3 follow-ups: (1) fix CLAUDE.md:101 wording,
  (2) fix the FSCS-vs-life-cover misinterpretation ("it is not
  working"), (3) fold into #335 + push. All done.
- FSCS root cause: `QueryClassifier` pattern `/\bam\s+i\s+(insured|
  covered|protected)\b/i` (QuerySchemas:274) greedily claimed
  "am i protected for my savings"; PROTECTION_COVER defined before
  SAVINGS_ACCOUNTS so it won primary. Fixed via negative-lookahead +
  new FSCS/deposit patterns on SAVINGS_ACCOUNTS.

## Files touched this session

- `resources/js/utils/chatNavigationRouter.js` — META_CONVERSATIONAL
  guard + word-boundary trigger matching
- `tests/frontend/utils/chatNavigationRouter.test.js` — NEW, 15 cases
- `app/Constants/QuerySchemas.php` — FSCS classification (2-part fix)
- `tests/Unit/Services/AI/QueryClassifierTest.php` — +7 cases (26 total)
- `CLAUDE.md` — line 101 3-part dispatch predicate
- Vault (not git): `Git History/May2026/May18.md`, `May2026 Commits.md`,
  `Home.md` reconciled during vault-sync

## WIP commit

- None. No uncommitted changes — everything is in proper feature
  commits (no `wip:` snapshot was needed).

## Commits added this session (all pushed to origin/fynPromptRework)

- `8cdbd16` fix(fyn-chat): stop client nav router hijacking questions
- `24bbf47` fix(fyn-chat): word-boundary match nav triggers
- `556fe2d` docs(canonical): CLAUDE.md 3-part dispatch predicate
- `b402112` fix(fyn-chat): 'protected for my savings' → FSCS not life cover
- (this handover commit will land on top)

## Open decisions

None outstanding. CSJ's 3 follow-ups all actioned. Two items were
*reported, not actioned* (correctly, per scope discipline) and CSJ did
NOT ask to fix them — leave unless CSJ raises:
1. Vault index methodology gap (raw git 350 vs curated table 268) —
   default: leave as internally-consistent 268, don't force to 350.
2. Secondary observation: msg-1 life-cover-vs-FSCS — now FIXED via the
   classifier change (b402112), so this is closed.

## Pick up from here (auto-continue contract)

The Fyn bug work is DONE and verified. The carried backlog from
handover-10 still stands — next session should:
1. **`tech-debt-session` on the PR #335 diff** (still not done — was
   interrupted by this bug both session 10 and 11). Run it against the
   full #335 delta now including the 4 new commits.
2. Standing: **PR #335 awaits CSJ review/admin-merge** → dev. Do NOT
   self-approve (`feedback_no_self_approval`,
   `feedback_main_via_dev_only`). PR #317 (release dev→main) stays
   parked on the freemium refactor (memory
   `project_pr317_gated_on_freemium_refactor`).
3. Carried doc backlog: KycGateChecker delta doc-fix; pre-existing
   `fynlaFeatu*Modules` rename fate (not ours — decide or leave).

## What the next Claude needs to know

- **Do NOT re-verify the 4 fixes — they are browser-verified GREEN.**
  Each tested end-to-end as chris@fynla.org (local; MFA from DB per
  CLAUDE.md local-dev rule). Evidence in this session's transcript.
- 41 tests green: 15 vitest (`chatNavigationRouter.test.js`, run with
  `npx vitest run --environment node` — jsdom env is broken by a
  pre-existing whatwg-url/Node incompat, NOT our code) + 26 Pest
  (`QueryClassifierTest.php`).
- `john@example.com` still has a £2,300/mo ExpenditureProfile from
  session-10 (harmless, db:seed-restorable). Tested as chris this
  session, not john.
- Dev servers running (:8000 Laravel, :5173 Vite, `public/hot` fresh).
  Do NOT `pkill -f vite` (kills sibling project).
- Worktree `.claude/worktrees/tender-bassi-375ee8` on `freemium` —
  leave intact (sub-project 2).
- vault-sync WAS run this session (overdue 5+ sessions cleared). The
  `VAULT-SYNC-PENDING.md` flag status was updated (Delta-1 confirmed on
  disk); flag retained as audit record, safe to delete now.
- The nav-router fix deliberately keeps the client router. The router
  exists because advice-mode strips `navigate_to_page`
  (`AdviceFyn.php:175`, BS-14). Don't "simplify" by deleting it.

## Branch / deploy state

- Branch: `fynPromptRework`
- Behind origin: 0
- Ahead of origin: 0 (all pushed; handover commit will be +1 until its
  own push in Phase 7)
- Deploy status: Not deployed. PR #335 open → dev, awaiting CSJ
  admin-merge. Nothing built or uploaded this session.
