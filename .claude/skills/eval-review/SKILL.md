---
name: eval-review
description: Triggered by the stop hook eval-gate. Dispatches the eval-reviewer agent to independently review all changes before allowing completion. RIGID — follow exactly, no shortcuts.
---

# Evaluation Review

You have been stopped by the eval-gate hook because you attempted to finish your work. Before you can finish, an independent evaluation MUST be performed.

**This is a RIGID skill. Follow it EXACTLY. Do not skip steps. Do not summarise. Do not ask the user questions. EXECUTE.**

## What You Must Do

1. **Dispatch the eval-reviewer agent** using the Agent tool with `subagent_type: "superpowers:code-reviewer"`. The agent will independently review every changed file, run convention checks, and perform browser testing if frontend files changed.

2. **Include this context in the agent prompt:**
   - The list of changed files (from `git diff --name-only HEAD` + untracked)
   - The diff stats
   - What the user originally asked for (summarise the task from conversation)
   - Whether Vue/JS files changed (agent must browser test)
   - Whether PHP files changed (agent must run Pest tests)

3. **Wait for the agent's report.**

4. **Act on the report:**
   - If PASS: report the result to the user. You may now complete your work.
   - If FAIL: fix every issue the agent identified. Do NOT ask the user. Just fix them. Then the next stop attempt will trigger another evaluation cycle.

## Agent Dispatch Template

```
Dispatch the eval-reviewer agent with this prompt:

"You are the evaluation reviewer for Fynla. Review all changes made in this session.

TASK CONTEXT: [what the user asked for]

CHANGED FILES:
[output of git diff --name-only HEAD + untracked]

DIFF STATS:
[output of git diff --stat HEAD]

FILE TYPES: [PHP: yes/no] [Vue/JS: yes/no] [Migration: yes/no] [Seeder: yes/no]

BROWSER TESTING REQUIRED: [yes if Vue/JS changed]
BACKEND TESTING REQUIRED: [yes if PHP changed]

Follow your evaluation process exactly. Read every changed file. Run every check. Test in browser if required. Report your findings."
```

## Critical Rules

- You MUST dispatch the agent. Do not do the evaluation yourself — you are biased towards your own work.
- You MUST wait for the agent's full report before responding to the user.
- You MUST fix issues the agent finds — do not argue with the agent's findings.
- The agent's verdict is final for this cycle. If FAIL, fix and try again.
