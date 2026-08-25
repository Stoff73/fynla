# Session Start Standing Authorisation Design

## Objective

Make approval of a spec, implementation plan, or agreed fix process carry forward as standing authorisation for the resulting work. The implementing inference must not repeatedly ask CSJ to approve individual file changes, commands, tests, or other actions that are already within the agreed scope.

## Skill change

Add a dedicated **Standing Authorisation After CSJ Agreement** section to both session-start discovery paths:

- `plugins/fynla-dev-skills/skills/session-start/SKILL.md`, the marketplace plugin source
- `.claude/skills/session-start/SKILL.md`, the active project-local skill loaded by the next Fynla inference

The section will define:

- **Trigger:** CSJ explicitly approves a spec, plan, or fix process, or explicitly directs implementation of it.
- **Active Phase 5 gate:** `start session` begins bootstrap but is not, by itself, approval of a proposed implementation. The active skill may auto-continue mutating implementation only when the handover or current context records the trigger; otherwise it continues safe investigation and asks before task-specific changes.
- **Default behaviour:** Continue autonomously through all reasonably necessary in-scope local work without asking for per-action or per-file approval.
- **Included actions:** Creating, editing, and renaming in-scope files; deleting only in-scope tracked files recoverable from version control; running commands, tests, linters, formatters, builds, seeders, and local migrations; managing local development processes; and installing dependencies required by the agreed work.
- **Approval continuity:** Routine implementation discoveries and proportionate corrections remain authorised when they preserve the agreed outcome and scope.
- **Credential boundary:** Standing authorisation does not cover accessing, reading, revealing, using, exposing, or transmitting credentials or other secrets unless that credential action was explicitly included in CSJ's agreement.
- **Exceptions:** Ask CSJ only when work would materially expand or change the agreed scope, require an unresolved product decision, cause destructive or irreversible loss of application data, user data, environment state, untracked files, or uncommitted work, affect production or third-party systems, or perform a commit, push, or deployment that was not explicitly included in the agreement.
- **Platform enforcement:** A skill cannot override runtime security controls. If the platform itself requires elevation, use its approval mechanism directly and request only the narrow permission required; do not add a separate conversational permission round.

## Existing wording adjustment

Clarify in both skills that bootstrap must not make task-specific source edits. The prescribed Git synchronization and conflict-resolution steps may update tracked files. After the session summary, task execution follows the standing-authorisation rules. In the active project skill, gate Phase 5 on recorded approval or implementation direction and reconcile every decision, blocker, report-template, hard-rule, and “What NOT to do” instruction with the same exceptions. This prevents later legacy wording from bypassing the trigger or contradicting the prescribed Git bootstrap and implementation mandate.

## Validation

Compare both current discovery paths with scenarios in which CSJ has approved a plan and the next step requires file edits, tests, dependency installation, a local migration, or deletion of an in-scope tracked file recoverable from version control. Each amended skill passes when the inference proceeds without conversational approval prompts and still stops for credential actions not explicitly included in CSJ's agreement and for destructive or irreversible loss of application data, user data, environment state, untracked files, or uncommitted work. Confirm that the next Fynla project inference resolves `.claude/skills/session-start/SKILL.md` rather than relying on the marketplace source alone.
