---
id: GATE-0001
workstream: chief-of-staff
action: Decide the status and gating of the existing autonomous bug-fix loop
raised: 2026-08-13
decided_by: CSJ
severity: high
decided_at: 2026-08-14T12:14:27+00:00
decision: approve
status: resolved
---
## What is being asked
`.github/workflows/claude.yml` runs an autonomous fix loop: bug report -> GitHub issue
-> checkout dev -> six-step prompt -> open PR to dev -> **attempts `gh pr merge --auto --squash` itself**.
No human approves before it acts. Scope is unbounded within the repo (bypassPermissions,
contents/pull-requests/issues write). It cannot reach main or prod.

It also fires on `issue_comment` containing `@claude` from any OWNER/MEMBER/COLLABORATOR —
so an agent commenting on an issue can START an autonomous run.

There is NO claim marker. Labels are set at issue creation, never on claim. An open issue
with no PR is indistinguishable from mid-flight, failed, and never-fired.

## Cannot be determined from the repo
Whether it is ENABLED in production. Config defaults false; local .env has no token;
both deploy templates say true; June10 handover says prod stays off pending CSJ.

## Interaction with CODEOWNERS removal (ab339ebc5, today)
CODEOWNERS may have been part of what stopped that self-merge succeeding.
GitHub branch protection is still in place and may still hold — unverified.

## The `@claude` trigger contradicts charter §13 (CSJ, 2026-08-13)

**Founders must never type a trigger word to get agents to act.** The workforce is
ambient: it reads its channels and decides what needs it. `claude.yml` is the
opposite — summoned, keyword-invoked — and it is therefore a *second* way to
invoke an agent, which is the Rule 20 disease at the invocation layer.

It also inverts: an agent commenting *"@claude already has this"* on an issue
**starts an autonomous run**. The trigger cannot distinguish a human request from
an agent talking to itself.

## Decision needed

1. **Is the loop enabled in production?** Config defaults false; local `.env` has no
   token; both deploy templates say true; the June 10 handover says prod stays off
   pending CSJ. Undetermined in both directions.
2. **Self-merge, or open a PR and stop?** It currently attempts
   `gh pr merge --auto --squash` on itself with no human in between.
3. **What claim marker** do the workforce and the loop share, so they cannot both
   take one issue? There is none today.
4. **Remove the `@claude` mention trigger?** Proposed change to
   `.github/workflows/claude.yml`:

   - **Drop the `issue_comment` trigger entirely** (`:73-78`). It is the summoned
     model and it is the self-triggering vector.
   - **Keep the `issues` event**, so newly filed bugs still reach the loop — that
     path is ambient and correct.
   - Add a claim marker on start: apply an `agent:in-progress` label and assign,
     so the Build lead can tell a live run from a stalled one.

   **Not applied.** The workforce does not edit this workflow before this gate is
   answered — its production status is unknown and it carries unbounded repository
   write plus self-merge. One approval here and it is a five-minute change.


## Decision — 2026-08-14T12:14:27+00:00

**CSJ: APPROVE**
