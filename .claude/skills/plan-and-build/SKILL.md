---
name: plan-and-build
description: Full feature lifecycle with quality gates. Use when starting any new feature, enhancement, or multi-step task. Wraps brainstorming → planning → implementation with mandatory browser test checkpoints, sub-agent verification, and double-check passes against the plan. Use when the user says "build", "create", "implement", "add feature", "new feature", or describes work that will take more than a single edit.
disable-model-invocation: true
---

# Plan and Build — Feature Lifecycle with Quality Gates

A disciplined workflow that prevents the plan→execute→broken→rage cycle. Enforces browser testing checkpoints, sub-agent verification, and implementation audits at every stage.

## Overview

```
BRAINSTORM → PLAN → IMPLEMENT → CHECKPOINT → VERIFY → NEXT TASK → ... → FINAL AUDIT → DONE
     ↑                              ↓
     └──── if checkpoint fails ─────┘
```

Every feature goes through ALL phases. No skipping. No "this is too simple."

---

## Phase 1: Brainstorm

Invoke the `superpowers:brainstorming` skill. Follow it completely:
1. Explore context
2. Ask clarifying questions (one at a time)
3. Propose 2-3 approaches
4. Present design, get approval
5. Write spec document
6. Spec review loop
7. User reviews spec

**Addition for Fynla:** During the design phase, explicitly identify:
- Which forms will be created/modified (list every field)
- Which API endpoints are needed
- Which existing components will be reused vs created new
- Browser test checkpoints (what should be testable after each implementation group)

Write these test checkpoints into the spec document as a dedicated section:

```markdown
## Browser Test Checkpoints

### Checkpoint 1: [After tasks 1-3]
- [ ] Navigate to [URL]
- [ ] Fill form: [field1]=X, [field2]=Y, [field3]=Z
- [ ] Submit and verify [expected result]
- [ ] Check sidebar shows [expected item]

### Checkpoint 2: [After tasks 4-6]
- [ ] ...
```

**Transition:** When the user approves the spec, invoke `superpowers:writing-plans`.

---

## Phase 2: Plan

The `superpowers:writing-plans` skill creates the implementation plan. Follow it completely.

**Addition for Fynla:** After the plan is written, insert checkpoint markers between task groups:

```markdown
### Task 3: [Component]
...

---
### 🔒 CHECKPOINT 1 — Browser Test Required
Before proceeding to Task 4, you MUST:
1. Start the dev server (`./dev.sh`)
2. Open the browser (Playwright)
3. Test everything from Checkpoint 1 in the spec
4. Take snapshots as evidence
5. Report results to the user
6. Only proceed if the user confirms the checkpoint passes

**If the checkpoint fails:** Fix issues, re-test from Step 1 of the flow, then re-attempt the checkpoint.
---

### Task 4: [Next Component]
...
```

Place checkpoints:
- After every 2-3 implementation tasks
- After any task that creates user-facing UI
- After any task that modifies forms or data flow
- Before the final task

---

## Phase 3: Implement with Checkpoints

Use `superpowers:executing-plans` or `superpowers:subagent-driven-development` to implement.

### Sub-Agent Rules

When dispatching sub-agents for implementation:

1. **Always use worktree isolation:** `isolation: "worktree"` on every Agent call
2. **Never allow server commands in agent prompts:** Add to every prompt: "Do NOT run artisan, composer, npm, or any server commands. Only edit files."
3. **After each agent completes, verify their work:**

```
AGENT VERIFICATION CHECKLIST (do this for EVERY sub-agent result):
□ Read the agent's result summary
□ Spot-check 2-3 of the files they changed (Read tool)
□ Verify the changes match what was asked for
□ Check they didn't modify files outside their scope
□ Check for console.log, hardcoded values, TODO stubs
□ If the agent claims "all done" — verify at least one specific claim
```

Do NOT merge agent work until verification passes. If verification fails, send the agent back with specific corrections.

### Checkpoint Execution

When you reach a checkpoint marker in the plan:

**STOP ALL IMPLEMENTATION.** Do not proceed to the next task.

1. Merge any pending worktree branches
2. Seed the database: `php artisan db:seed`
3. Start/verify dev server: `./dev.sh`
4. Open browser via Playwright
5. Execute every test item from the checkpoint list
6. For each test item:
   - Navigate to the URL
   - Fill EVERY field (not just some)
   - Submit the form
   - Verify the result page/dashboard
   - Take a snapshot
7. Report results to the user in this format:

```markdown
### Checkpoint [N] Results

| Test | Status | Evidence |
|------|--------|----------|
| Navigate to /savings | PASS | Snapshot 1 |
| Fill account form | PASS | Snapshot 2 — all 8 fields filled |
| Submit and verify | FAIL | Error: "amount required" — field was hidden |

**Blocking issue:** [description]
**Fix needed:** [what to change]
```

8. Wait for user confirmation before proceeding
9. If ANY test fails: fix the issue, then re-test from Step 1 of the ENTIRE flow (not just the failing test)

---

## Phase 4: Final Audit (Double-Check Pass)

After ALL tasks are implemented and ALL checkpoints pass, run TWO verification passes against the plan.

### Pass 1: Plan Compliance Audit

Read the implementation plan. For EVERY task:

```
□ Task 1: [description] — Check file exists/was modified as specified
□ Task 2: [description] — Check implementation matches what was planned
□ Task 3: [description] — Check tests were written (if TDD was specified)
...
```

Report any deviations:

```markdown
### Plan Compliance — Pass 1

| Task | Status | Notes |
|------|--------|-------|
| Task 1: Create UserForm | DONE | File created at correct path |
| Task 2: Add API endpoint | DONE | Route added, controller method exists |
| Task 3: Write tests | MISSING | No test file found |
| Task 4: Update sidebar | PARTIAL | Item added but icon is wrong |
```

Fix any MISSING or PARTIAL items before Pass 2.

### Pass 2: Full Browser Test

After Pass 1 fixes are applied:

1. Seed the database
2. Open the browser
3. Walk through the ENTIRE feature end-to-end as a user would:
   - Register/login if needed
   - Navigate to the feature
   - Fill every form, click every button
   - Verify every result
   - Check every page the feature touches
4. Report with snapshots

```markdown
### Full Browser Test — Pass 2

**Flow tested:** [describe the full user journey]

| Step | Action | Result | Snapshot |
|------|--------|--------|----------|
| 1 | Login as chris@fynla.org | Dashboard loads | [snap] |
| 2 | Navigate to /savings | Page loads, 3 accounts shown | [snap] |
| 3 | Click "Add Account" | Form opens | [snap] |
| 4 | Fill all 8 fields | All fields accepted | [snap] |
| 5 | Submit | Success, redirected to list | [snap] |
| 6 | Verify new account in list | Shows with correct data | [snap] |
```

### Completion Gate

Only declare "DONE" when:
- [ ] Pass 1 shows ALL tasks complete
- [ ] Pass 2 shows ALL browser tests passing
- [ ] No TODO stubs or placeholder functionality
- [ ] No console.log statements left in changed files
- [ ] No hardcoded hex values in changed Vue files
- [ ] All user-facing text uses British spelling
- [ ] All acronyms spelled out (except ISA)

If ANY of these fail, fix and re-run Pass 2.

---

## Phase 5: Wrap Up

1. Commit all changes
2. Push to remote
3. Generate deploy notes (if deploying)
4. Run `/vault-sync` to update fynlaBrain
5. Report final summary to user

---

## Quick Reference: When to Use What

| Situation | Skill |
|-----------|-------|
| "I want to build X" | This skill (`/plan-and-build`) |
| "Fix this bug" | `/systematic-debugging` |
| "Review this code" | `/code-review` |
| "End the session" | `/session-end` |
| "Update the vault" | `/vault-sync` |
| Simple one-file edit | No skill needed — just do it |

## Important

- NEVER skip checkpoints. They exist because the alternative is 3-5x rework time.
- NEVER self-approve sub-agent work. Verify at least 2-3 files per agent.
- NEVER say "done" without Pass 1 AND Pass 2 completing.
- NEVER proceed past a failing checkpoint. Fix first, re-test the whole flow, then continue.
- Checkpoints feel slow. They are. They're still faster than discovering everything is broken at the end.
