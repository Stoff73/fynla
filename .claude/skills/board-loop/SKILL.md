---
name: board-loop
description: The loop for clearing the Fynla work board. Use whenever CSJ says "clear the board", "fix the board", "work the board", "board loop", or points at outstanding W-NNNN items and says fix them. One item at a time, verify-before-fixing, invoke superpowers:systematic-debugging on every live bug, test only the diff, update the board immediately, then move on. Never parallel agents, never a full suite per item, never a report before green.
---

# Board loop

**One item at a time. Serial. Never a subagent.** CSJ has ruled twice that
parallel fixers overwrite each other's code and cost more than they save. That
covers every kind of agent, including read-only triage. There is no exception to
find.

## The loop

Run these nine steps in order for every item. **Say each one out loud** — CSJ
must be able to read the loop in the transcript without opening the board. A
silent closure reads as no closure.

1. **Take the top item** off the outstanding board, highest severity first.
2. **State it:** `Now fixing bug W-NNNN — <title>.`
3. **Check whether it is already fixed.** Never skipped, never assumed — not
   even for an item filed yesterday. Open the cited code and look at the current
   line. A citation in the item, a test name, a completion note or a green suite
   is NOT evidence. **Only the code is.**
4. **State the finding out loud**, either:
   - `W-NNNN — VERIFIED FIXED` with the replacing code at `file:line`, or
   - `W-NNNN — CONFIRMED LIVE` with the defect quoted at its current line.
5. **If already fixed:** update the board *there and then* — status `done`, a
   note citing the replacing code at `file:line`, and the item's own tests
   re-run. Then say `W-NNNN is CLOSED. Board updated to done.` Go to step 1.
6. **If live: invoke `superpowers:systematic-debugging` and follow it.** This is
   not optional and not a judgement call. Any bug that survived a previous sweep
   survived it because someone fixed what they could see instead of finding the
   cause — read the failure history below. Diagnose to root cause with
   `file:line` evidence before touching anything, and where the item's
   acceptance names a prerequisite investigation, do that investigation FIRST
   and state its result.
7. **Fix the root cause**, not the symptom. If the same wrong thing exists in
   two places, consolidating them is part of the fix (Rule 20) — editing both in
   lockstep is a violation, not a fix.
8. **Test the diff, not the suite.** The targeted test file, the targeted
   `--filter`, the one browser check. A full suite per item is banned
   (CLAUDE.md #17); full suites belong at consolidation points only. State the
   pass count. If a test asserted the OLD behaviour, that test encoded the
   defect as a contract — correct it and put the reasoning at the line. Never
   delete it, and never weaken an assertion to get green.
9. **Update the board there and then** — status, the fix at `file:line`, the
   test that now covers it, and anything the acceptance asked for that you did
   NOT do. Say `W-NNNN is CLOSED. Board updated to done.` Then step 1 again.

Loop until every item is green, tested and closed. **Reports come after green,
never instead of it.**

## Hard rules inside the loop

- **`superpowers:systematic-debugging` on every live bug.** Step 6. No
  exceptions for "small" or "obvious" ones — obvious is how the last four sweeps
  closed this family without fixing it.
- **CLAUDE.md #14 — loop until correct.** Diagnose, fix, re-verify. No apology
  in place of a fix, no partial success, no stopping to write a report.
- **No parallel agents. Of any kind.**
- **No full test suite per item.**
- **Never `git checkout -- <file>` to undo a mutation test.** It reverts to HEAD
  and destroys uncommitted fixes. Copy the file first, or reverse the edit
  explicitly. This has cost real time twice.
- **Rule 19** — a user-facing fix is not done until it holds on web and `/m`.
  Where `/m` cannot be verified, say so on the board rather than implying it was.
- Commit when a fix is verified. Committing is the record, not the work.

## The failure this exists to stop

A sweep fixes what it noticed, writes a completion note saying the family is
closed, and builds no guard. The next regression is then invisible — and the
note is the thing that stops the next reader looking. W-0432 and W-0461 were
each declared closed twice while nine instances stood one directory over,
because every existing guard drove PHP and asserted on service output, so
re-hardcoding a Vue caption left the whole suite green.

**If an item's acceptance names a guard, the guard IS the item** and the fixes
are the easy half. Prove the guard by mutation: break the thing it protects and
watch it go red.
