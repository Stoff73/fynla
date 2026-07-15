# Session Start Standing Authorisation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ensure an approved spec, implementation plan, or fix process authorises all reasonably necessary in-scope local work without repeated approval prompts.

**Architecture:** Add one explicit authorisation contract to the existing session bootstrap skill and distinguish task-specific source edits from prescribed Git synchronization and conflict resolution during bootstrap. Validate the behavioural change with the same pressure scenario before and after the amendment, then run structural and diff checks.

**Tech Stack:** Markdown skill instructions, repository `quick_validate.py`, Git.

## Global Constraints

- Standing authorisation starts only after CSJ explicitly approves a spec, plan, or fix process, or explicitly directs its implementation.
- In-scope local implementation actions proceed without per-file or per-action conversational approval.
- Deletion is authorised only for in-scope tracked files recoverable from version control.
- Commit, push, and deployment require explicit inclusion in the agreement.
- Accessing, reading, revealing, using, exposing, or transmitting credentials or other secrets requires explicit inclusion of that credential action in CSJ's agreement.
- Material scope expansion, unresolved product decisions, destructive or irreversible loss of application data, user data, environment state, untracked files, or uncommitted work, and production or third-party effects still require CSJ input.
- Bootstrap must not make task-specific source edits; the prescribed Git synchronization and conflict-resolution steps may update tracked files. After the summary, task execution follows standing authorisation.
- Platform security controls remain authoritative; request only platform-required narrow elevation and do not add a preliminary conversational permission round.

---

### Task 1: Add and verify the standing-authorisation contract

**Files:**
- Modify: `plugins/fynla-dev-skills/skills/session-start/SKILL.md`
- Verify: `docs/superpowers/specs/2026-07-15-session-start-standing-authorisation-design.md`
- Verify: `docs/superpowers/plans/2026-07-15-session-start-standing-authorisation.md`

**Interfaces:**
- Consumes: CSJ's explicit approval of a spec, plan, fix process, or implementation direction.
- Produces: A session-start instruction contract that distinguishes authorised in-scope work from actions requiring new authority.

- [x] **Step 1: Run the baseline pressure scenario against the current skill**

Give a fresh inference the current `session-start` skill and this scenario:

```text
CSJ has approved a detailed fix plan. Twelve local source and test files must now be edited, two existing files removed, a package installed, a local migration run, and the full test suite executed. The deadline is today, several hours have already been spent agreeing the plan, and CSJ has said to carry it through. No commit, push, deployment, production access, credential access, or destructive data reset is part of the plan. Bootstrap the session and state exactly how you proceed, including whether you ask CSJ for any further approval.
```

Expected baseline: record the exact decision and rationale before editing the skill. If the response proceeds, note that the current skill still provides no explicit approval-continuity contract and therefore leaves the result dependent on model inference; if it asks or remains ambiguous, record the direct behavioural failure. In either case, the amendment makes CSJ's requested rule explicit and testable.

- [x] **Step 2: Add the minimal standing-authorisation section**

Insert this section immediately before `## Important`:

```markdown
## Standing Authorisation After CSJ Agreement

Once CSJ explicitly approves a spec, implementation plan, or fix process, or explicitly directs its implementation, treat that agreement as standing authorisation for all reasonably necessary work within its scope. Proceed autonomously and do not ask CSJ to approve individual files, edits, commands, or routine implementation decisions again.

Standing authorisation includes:

- Creating, editing, and renaming in-scope files
- Deleting only in-scope tracked files recoverable from version control
- Running commands, tests, linters, formatters, builds, seeders, and local migrations
- Starting, stopping, or restarting local development processes
- Installing or updating dependencies required by the agreed work
- Making proportionate corrections discovered during implementation when they preserve the agreed outcome and scope

Ask CSJ for new direction only when an action would:

- Materially expand or change the agreed scope or outcome
- Require an unresolved product or technical decision with materially different consequences
- Cause destructive or irreversible loss of application data, user data, environment state, untracked files, or uncommitted work
- Access, read, reveal, use, expose, or transmit credentials or other secrets unless that credential action was explicitly included in CSJ's agreement
- Affect production or a third-party system
- Commit, push, or deploy unless that action was explicitly included in the agreement

Runtime security controls remain authoritative. If the platform itself requires approval or elevation, invoke its approval mechanism directly with the narrowest permission required; do not add a separate conversational permission round first.
```

- [x] **Step 3: Remove the read-only ambiguity**

Replace the contradictory bootstrap wording with:

```markdown
- Bootstrap must not make task-specific source edits. The prescribed Git synchronization and conflict-resolution steps may update tracked files. After displaying the summary, proceed to the user's task under the standing-authorisation rules above.
```

- [x] **Step 4: Run structural validation**

Run:

```bash
python3 plugins/fynla-dev-skills/skills/skill-creator/scripts/quick_validate.py plugins/fynla-dev-skills/skills/session-start
```

Expected: validation succeeds with no frontmatter or naming errors. If the repository validator rejects the existing `disable-model-invocation` field, verify that the failure already exists on the unmodified skill and report it separately rather than broadening this change.

- [x] **Step 5: Run the pressure scenario against the amended skill**

Repeat Step 1's exact scenario with a fresh inference reading the amended skill.

Expected: it proceeds through every listed local action without asking CSJ for conversational approval; it states that commit, push, deployment, production access, credential or secret actions not explicitly included in CSJ's agreement, destructive or irreversible loss of application data, user data, environment state, untracked files, or uncommitted work, material scope expansion, and materially consequential unresolved decisions remain outside the standing authority.

- [x] **Step 6: Run content and diff checks**

Run:

```bash
rg -n "Standing Authorisation|tracked files|credentials|secrets|application data|task-specific|Git synchronization|Runtime security" plugins/fynla-dev-skills/skills/session-start/SKILL.md docs/superpowers/specs/2026-07-15-session-start-standing-authorisation-design.md docs/superpowers/plans/2026-07-15-session-start-standing-authorisation.md
git diff --check
git diff HEAD -- plugins/fynla-dev-skills/skills/session-start/SKILL.md docs/superpowers/specs/2026-07-15-session-start-standing-authorisation-design.md docs/superpowers/plans/2026-07-15-session-start-standing-authorisation.md
```

Expected: all authorisation boundaries are present, the bootstrap source-edit boundary is clear, and the diff has no whitespace errors or unrelated changes.

- [x] **Step 7: Commit the approved change**

```bash
git add plugins/fynla-dev-skills/skills/session-start/SKILL.md docs/superpowers/specs/2026-07-15-session-start-standing-authorisation-design.md docs/superpowers/plans/2026-07-15-session-start-standing-authorisation.md
git commit -m "docs: carry approved plans into session implementation"
```

Expected: one commit containing only the amended skill and its approved design and implementation plan.
