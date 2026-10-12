---
type: handover
mode: context-clear
date: 2026-10-12
session: 1
branch: dev
trigger: context-handover skill (CSJ asked to clear context after T1)
---

# Context Clear Handover — 2026-10-12, Session 1

## Immediate state

T1 of the fix plan was finished to its Dev walk and recorded. T2 is next.

## The thread

- **The walk and the plan.** CSJ asked for a code-grounded plan for Brett's production walk of 11 October (`brettTest/2026-10-11-prod-fyn-gpt6-luna-walk.docx`, 63 findings). The plan is `October/October12Updates/walk-2026-10-11-fix-plan.md`:
  - section 0: CSJ's NON-NEGOTIABLE rules R1–R5;
  - section 0b: the task list T0–T43 with six boxes each, and the task log with evidence;
  - sections 2–8: the root causes (RC1–RC9), a verdict and evidence for every finding, the batches, and the two onboarding changes (section 6: normal registration opens on the asset bubbles; section 6b: the Save Tax report only for Save Tax arrivals).
- **Rejected by CSJ, do not re-raise:**
  - "Fact plus question: answer then form?". A question always goes to Advice Fyn's answer process: QueryClassifier → QuerySchemas REQUIRED_TOOLS → FynContextAssembler → read-only tools.
  - "Wait for the in-flight release?". No ordering questions; make it all work.
  - Every "decision" the code or a ruling answers (W-0140, W-0154, W-0467, W-0501, ruling 50, S0.5.r).
- **CSJ's answers this session:**
  - The name: use the registered first name, never ask for it.
  - The three funnel questions go after the bubbles, in today's order.
  - The Save Tax report only for Save Tax arrivals; others see their actions.
  - Fyn's money is exact: £100 when whole, £100.15 when it has pence, never rounded.
  - R5: no full review or suite during the plan; only the changes.
- **T0 (dev's R19–R39 fixes):**
  - Both bundles were built from a clean copy of dev `f00076906` and deployed to csjones.
  - R20's caption was walked (the only R19–R39 change not seen on csjones before).
  - Ticked to Dev walk.
- **T1 (Fyn receives exact words):**
  - `UserContentSanitiser::message()` is used at `FynContextAssembler.php:347`.
  - PR #1209 was merged to dev (`cc2ee4437`) and deployed to csjones.
  - Walked on web and /m: the stored turns show "?" intact, and the answers came back with the working.
  - Ticked to Dev walk.
- **T5's baseline, seen on the T1 walk:** gpt-6-luna on csjones hands data-shaped questions to capture. The csjones log shows "[FynLoop] LLM-fallback write-intent caught (classifier miss)". This is RC4 (the `FynSystemPrompt` TOP-PRIORITY handoff rule) and was there before T1. Details are in the plan's task log.

## Files touched this session

- `app/Services/AI/Prompts/UserContentSanitiser.php` and `app/Services/AI/Fyn/FynContextAssembler.php`, plus 2 test files (PR #1209, merged).
- `October/October12Updates/walk-2026-10-11-fix-plan.md` (the plan, rules, task list and log), `October/October12Updates/shots/` (T0 and T1 screenshots).
- `todoCurrent/TODO.md` (item 18: the Found line, rules, Where we stopped).
- Memory (outside the repo):
  - new: `feedback_fyn_currency_exact_pence`, `project_savetax_report_only_for_savetax_arrivals`, `feedback_plan_task_list_and_change_only_testing`;
  - updated: `feedback_no_subagents_without_csj_asking`, `feedback_never_walk_headless_use_chrome`, `feedback_only_real_decisions_in_plain_words`;
  - `MEMORY.md` compacted to about 14.6KB.

## WIP commit

- Docs-only: the plan, shots, TODO and this handover, on `docs/context-handover-2026-10-12-s1`, merged to dev by PR. The SHA is in that PR.
- Not committed, as they are not this session's: the `brettTest/` screenshot deletions, `brettTest/2026-10-11-prod-fyn-gpt6-luna-walk.docx` (Brett's walk document) and its `~$` lock file, and `workforce/ops/*`. CSJ decides on those.

## Added after the handover was first written

- **T1 security follow-up, shipped to dev:** an automated security review found the single tag pass could rebuild `</user_message>` from `<</user_message>>`. It now repeats until nothing changes: PR #1211, merged `7be2bc5c3`, on csjones and walked on /m. Evidence is in the plan's task log. The next release carries it with T1.
- **New task T44 (CSJ 2026-10-12, screenshot `/Users/CSJ/Desktop/remove.png`):** remove the suggested prompts from Fyn's chat window: the /m "Tell me more about: …" pills (`resources/mobile/views/Dashboard.vue:324-325`) and the web chat panel's "Suggestions" list (`AiChatPanel.vue:342-365`). Native has none. It is in the plan's task list, in the chat-surfaces group.
- csjones is now on dev `7be2bc5c3`.

## Open decisions

- **T42:** clearing the "declared none" flags and the unasked goal 1681 on Brett's production account 812 is a database change. Only with CSJ's OK.
- **R9/R20 (older, 9 October walk file):** which pot figure leads on the Retirement chart, and its heading. Not part of this plan.

## Pick up from here (auto-continue contract)

Current TODO item: **18**; see "Where we stopped (2026-10-12, session 1, context clear)". The order of work is the plan's task list (section 0b). Rules R1–R5 in section 0 apply to every step.

1. **T2: one question test in front of every gate that runs before the model.**
   - **Use the existing, uncalled `QuerySchemas::isAdviceShaped`** (`app/Constants/QuerySchemas.php:851`). Add the phrasings it misses: "give me", "show (me) the working", "work it through", "which wins", "figures please", "is that right", "nothing to record", "rules question".
   - **Call it first in each gate:**
     - `AdviceFyn::recordDeclaredNone` (`:658`);
     - `WriteIntentClassifier::classify`, replacing `looksLikeQuestion` (`:348`);
     - `AdviceFyn::captureContinuationIntent` (`:737`);
     - `proposalAcceptanceIntent`;
     - `offerTypedChangeForm` (`:634`).
   - **Negate "record", "save" and "add"** when written as "nothing to record" or "don't save".
   - **Red-first tests:** Brett's exact production messages, in the plan's section 8 table, with the route each should take. Replay scripts showing how to run messages through the gates were in this session's scratchpad (gone after clear). The table in section 8 has the messages and results.
2. **Then T3** (the `QueryClassifier` bypass types and patterns, section 2 RC3), **T4, T5, and on.**
3. **Each task follows the same loop:**
   1. Make a worktree off origin/dev in the scratchpad (`git worktree add -b <branch> <path> origin/dev`).
   2. `cp -R` vendor from the main checkout, copy `.env`, symlink `node_modules`, and run `composer dump-autoload -o`.
   3. Write the red-first test; run only the touched test files.
   4. Commit, push, open a PR to dev, and `gh pr merge --merge --admin`.
   5. On csjones: `git pull origin dev`, then route, config and view clear and `config:cache` (never `cache:clear`). Rebuild both bundles from a clean dev copy if the frontend changed.
   6. Walk as a user (R2) on web 1440 and /m 390, take screenshots, and log the evidence in the plan.
   7. Remove the worktree.

## What the next Claude needs to know

- **csjones SSH:**
  - The alias is `ssh -F ~/.ssh/config.fynlaDev csjones-dev`.
  - The key must be in the agent. CSJ ran `ssh-add ~/.ssh/fynlaDev` and `ssh-add ~/.ssh/production` this session; check with `ssh-add -l`.
  - The app lives at `~/www/csjones.co/fynla-app`.
- **Merge first, then deploy:** checking a feature branch out on csjones was refused by the session's permission check (remote write). Merge first (R3 order), then `git pull origin dev` on csjones, which is allowed.
- **Production reads:** use the `ssh-fynla` MCP. Plain-ssh downloads from production were refused. The production log level is `error`, so warnings such as `handoff_error` are not logged there; csjones logs at debug.
- **Walk account:** csjones Rory Retest, user 504, `slaterjoneschris+r1-retest@gmail.com` / `Password1!`. Sign-in codes come from `php zz_code.php 504` on csjones (the R2 exception; log each use).
- **Desktop to /m:** typing the /m front door while signed in on desktop carries the session across, and then rotates the desktop token (Q3/T33). Expect the desktop tab to drop to /login after a /m visit.
- **Fyn's model on csjones** is `AI_PROVIDER=openai` (gpt-6-luna). Data-shaped questions get handed to capture (RC4); questions about plan figures are answered.
- **Other agents' worktrees:** a mapping agent works in `scratchpad/wt-appmap` (session 7a7b18b4) and is read-only. Three old `.claude/worktrees/agent-*` worktrees from 1 October exist. Leave all of them alone.

## Branch / deploy state

- Branch: dev, at `7be2bc5c3` (includes T1 and its security follow-up #1211), then the docs PRs.
- fynla.org: main `f45bc67fd` (release ab), unchanged this session. T0 and T1 go out in CSJ's next release, carrying R19–R39 and #1209. No migration beyond `2026_10_09_140000` (already listed for that release). Both bundles are needed.
- csjones: dev `7be2bc5c3`; both bundles rebuilt today from `f00076906`. T1 and #1211 are server-only, so they are current.
