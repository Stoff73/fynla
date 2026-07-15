# Session Start Standing Authorisation Design

## Objective

Make approval of a spec, implementation plan, or agreed fix process carry forward as standing authorisation for the resulting work. The implementing inference must not repeatedly ask CSJ to approve individual file changes, commands, tests, or other actions that are already within the agreed scope.

## Skill change

Add a dedicated **Standing Authorisation After CSJ Agreement** section to `plugins/fynla-dev-skills/skills/session-start/SKILL.md` immediately before its final safeguards.

The section will define:

- **Trigger:** CSJ explicitly approves a spec, plan, or fix process, or explicitly directs implementation of it.
- **Default behaviour:** Continue autonomously through all reasonably necessary in-scope local work without asking for per-action or per-file approval.
- **Included actions:** Creating, editing, and renaming in-scope files; deleting only in-scope tracked files recoverable from version control; running commands, tests, linters, formatters, builds, seeders, and local migrations; managing local development processes; and installing dependencies required by the agreed work.
- **Approval continuity:** Routine implementation discoveries and proportionate corrections remain authorised when they preserve the agreed outcome and scope.
- **Credential boundary:** Standing authorisation does not cover accessing, reading, revealing, using, exposing, or transmitting credentials or other secrets unless that credential action was explicitly included in CSJ's agreement.
- **Exceptions:** Ask CSJ only when work would materially expand or change the agreed scope, require an unresolved product decision, cause destructive or irreversible loss of application data, user data, environment state, untracked files, or uncommitted work, affect production or third-party systems, or perform a commit, push, or deployment that was not explicitly included in the agreement.
- **Platform enforcement:** A skill cannot override runtime security controls. If the platform itself requires elevation, use its approval mechanism directly and request only the narrow permission required; do not add a separate conversational permission round.

## Existing wording adjustment

Clarify that bootstrap must not make task-specific source edits. The prescribed Git synchronization and conflict-resolution steps may update tracked files. After the session summary, task execution follows the standing-authorisation rules. This prevents the final safeguard from contradicting the prescribed Git bootstrap or the new implementation mandate.

## Validation

Compare the current and amended skill with scenarios in which CSJ has approved a plan and the next step requires file edits, tests, dependency installation, a local migration, or deletion of an in-scope tracked file recoverable from version control. The amended skill passes when the inference proceeds without conversational approval prompts and still stops for credential actions not explicitly included in CSJ's agreement and for destructive or irreversible loss of application data, user data, environment state, untracked files, or uncommitted work.
