# Fynla Organisation Repository Migration Design

**Date:** 2026-07-20
**Status:** Design approved in principle — awaiting CSJ review of the saved specification
**Author flow:** Brainstormed with CSJ on 2026-07-20
**Source repository:** `Stoff73/fynla` (`/Users/CSJ/Desktop/fynla`)
**Target repository:** `Fynla/Fynla` (private)
**Default branch:** `main`

---

## 1. Problem and goal

Fynla's application source currently lives in Chris Slater-Jones's personal GitHub repository while the founders are moving the wider company knowledge and agent infrastructure into the `Fynla` organisation. The team needs one organisation-owned application repository that preserves the development record, contains every supported product surface, excludes secrets and local residue, and can be used safely by all three founders and their agents.

The goal is to create a private `Fynla/Fynla` repository that becomes the canonical home of the application code. It must preserve the useful Git history while permanently removing the explicitly approved sensitive files. It must contain the backend, web application, `/m` experience, Capacitor iOS project, native iOS project, tests, agents, skills, prompts and supporting development configuration.

The migration must not lose company knowledge. Knowledge-only material that does not belong in the current source tree may be removed from the current application branch only after its content has been verified in `fynlaBrain`.

## 2. Decisions approved in brainstorming

| Decision | Approved outcome |
|---|---|
| Repository owner | The `Fynla` GitHub organisation |
| Repository name | `Fynla` (`Fynla/Fynla`) |
| Visibility | Private |
| Migration method | Sanitised full-history mirror into a new repository |
| History | Preserve branches, tags, authors, dates, messages and relationships, except for approved removals |
| ProjectionLab export | Remove from every historical commit and ref |
| Unsafe environment files | Remove the three identified non-template files from every historical commit and ref |
| Safe environment templates | Retain files such as `.env.example` and `.env.production.example` |
| Root screenshots | Ignore root-level PNG files; retain legitimate nested application image assets |
| Product coverage | Backend, web, `/m`, Capacitor iOS, native iOS, tests, agents, skills and supporting tooling |
| Knowledge material | Verify in `fynlaBrain` before removing from the current application tree |
| Existing personal repository | Leave untouched until the organisation copy is verified |

## 3. Chosen approach

Use a disposable bare mirror of the existing repository, sanitise the mirror, validate it, and push it into a newly created empty private organisation repository.

This is preferred to transferring and rewriting the personal repository in place because it provides a clean rollback boundary: `Stoff73/fynla` remains available and unchanged throughout verification. It is preferred to uploading a fresh snapshot because a snapshot would discard valuable engineering history and branch context.

The migration will:

1. Fetch a fresh mirror of all normal branches and tags from `Stoff73/fynla`.
2. Record a before-migration inventory of refs, commits, tags and objects.
3. Rewrite the disposable mirror to remove only the approved historical paths.
4. Run structural, content and secret checks against the rewritten mirror.
5. Create the empty private `Fynla/Fynla` repository.
6. Push the sanitised branches and tags.
7. Set `main` as the default branch and configure team access.
8. Compare the organisation repository against the validated mirror.
9. Keep the personal repository available during a founder validation period; archiving or redirecting it is a later explicit decision.

## 4. History preservation and sanitisation

### 4.1 Historical paths approved for permanent removal

The following paths will be removed from every branch, tag and historical commit in the sanitised mirror:

```text
2026-02-27-projectionlab-account-data--slaterjoneschris-at-gmail-com.json
.env.development
deploy/csjones-fynla/.env.production
deploy/fynla-org/.env.production
```

No other historical path will be removed silently. A full-history secret scan will be performed before publishing. If it finds another credible secret, credential or private export, implementation pauses and reports the path and risk to CSJ without exposing the secret value.

### 4.2 Consequences of the rewrite

Removing historical content necessarily changes commit identifiers for affected commits and their descendants. Author names, author dates, commit messages, topology, branches and tags will otherwise be retained. Any cryptographic signature attached to a rewritten commit or tag can no longer validate against the new identifier; this will be reported if signed objects are found.

The original personal repository is not rewritten, force-pushed, deleted or archived during this migration. It is the rollback source until the organisation repository passes verification.

### 4.3 Future prevention

The current ignore rules will be strengthened or confirmed so that:

- Environment files are ignored at any depth.
- Deliberately safe environment templates remain trackable.
- Root-level PNG screenshots are ignored.
- Nested application assets such as icons and interface images remain trackable.
- Dependency directories, generated builds, local storage, caches, logs and worktrees remain ignored.

The intended environment pattern is an ignore-all rule with narrow template exceptions, for example:

```gitignore
.env*
!.env.example
!.env.*.example
```

The intended root screenshot rule remains:

```gitignore
/*.png
```

## 5. Canonical current-tree contents

The organisation repository is the source-code system of record, not a second Obsidian vault. Its current application branches must retain all files needed to develop, test, understand, build and deploy the product.

### 5.1 Required content

The current source tree must include, where present and in use:

- Laravel/PHP application code, bootstrap, configuration, database migrations and seeders.
- Web routes, API routes, console routes and test-only route configuration.
- Web UI source and supporting runtime assets.
- The `/m` mobile-web source and build configuration.
- Capacitor iOS source and configuration under `ios/`, excluding generated dependencies and bundled web output.
- Native iOS source, project configuration and tests under `ios-native/`.
- Backend, frontend, browser, E2E, integration, architecture and unit tests, plus fixtures.
- Composer, npm, Vite, Vitest, Playwright and other reproducible development manifests and lockfiles.
- GitHub Actions workflows, issue templates and `CODEOWNERS`.
- Agent definitions, skills, hooks, prompts, personas, memory architecture and tool definitions required by the product or development workflow.
- Deployment source, scripts and safe configuration templates.
- Code-adjacent documentation: README files, architecture specifications, API contracts, testing guidance and deployment instructions.
- Runtime templates, public assets and reference data that the application or tests demonstrably consume.

### 5.2 Content excluded from the current source tree

The following categories must not be uploaded as current source content:

- Real `.env` files, secrets, credentials, tokens and private keys.
- The ProjectionLab export.
- Root-level PNG screenshots and other temporary visual evidence already covered by ignore rules.
- Dependency directories such as `node_modules`, `vendor` and CocoaPods output.
- Generated web/mobile builds, caches, local storage links, test output, logs and coverage output.
- Local databases, dumps and machine-specific deployment state.
- Operating-system metadata, editor state, local worktrees and transient agent state.
- Generated Capacitor web bundles and other reproducible compiled output.

### 5.3 Dependency and reference guard

No tracked file is removed from the current tree merely because its name looks old or untidy. Before a cleanup candidate is removed, implementation checks whether it is referenced by application code, tests, build scripts, deployment scripts, documentation links or generated-file manifests. Referenced or runtime-required content remains in `Fynla/Fynla`.

## 6. `fynlaBrain` no-loss boundary

The existing monthly folders, Obsidian configuration and root-level working notes contain a mixture of valuable project knowledge and source-adjacent documentation. Their Git history remains available under the history-preservation decision, except for the approved sensitive removals.

For the current default/integration tree, knowledge-only candidates will be handled through an explicit manifest rather than a bulk deletion. Likely candidates include the monthly `April`, `May`, `June` and `July` working folders, `.obsidian` workspace state, meeting/session handovers, company/FCA working documents and other vault-oriented notes that are not consumed by the application.

For every cleanup candidate:

1. Calculate and record its source path and content hash.
2. Find the same content in `/Users/CSJ/Desktop/fynlaBrain` or its organisation repository.
3. If no equivalent exists, copy the complete content into the appropriate `fynlaBrain` location before proposing removal from the application tree.
4. If both locations contain different useful versions, retain both versions or merge them without discarding unique content.
5. Produce a reviewable cleanup manifest showing `kept in code`, `verified in fynlaBrain`, `copied to fynlaBrain`, or `needs decision`.
6. Apply current-tree removals in a normal cleanup commit or pull request, not through further history rewriting.

Code-adjacent documentation remains in `Fynla/Fynla`; founder knowledge, conversations, handovers and research live in `fynlaBrain`. History remains available for forensic reference, but the latest source tree becomes easier for developers and agents to navigate.

## 7. Branches, tags and collaboration model

- `main` remains the default and production-oriented branch.
- `dev` remains the integration branch.
- Existing active feature, fix, audit and documentation branches are preserved so work is not stranded.
- Existing tags are preserved subject to the unavoidable rewritten commit identifiers.
- The repository description and README must make the canonical role of `Fynla/Fynla` clear.
- The remote migration must not alter or delete any founder's local branch.
- Existing clones will require a deliberate remote change or fresh clone after migration because rewritten history must not be accidentally pushed back from an old clone.

Normal team work should use feature branches and pull requests. Force-push and branch-deletion protection should be enabled for `main`, and for `dev` where compatible with the team's workflow and GitHub plan. Required checks should be configured only after the current workflows have been observed passing in the new repository, preventing a broken copied workflow from locking the founders out.

## 8. Founder access

The initial team is:

- Chris Slater-Jones — CTO.
- Azlan Raj — Chief Marketing Officer.
- Brett Isenberg — CFO.

All three must be organisation members before acceptance is complete. A `Founders` team should provide one manageable access point for company repositories. The recommended repository permission is `Maintain` for the founders' team, with organisation owners retaining `Admin`; this permits day-to-day repository and issue management without unnecessarily distributing organisation-level administration.

Pending invitations must be reported as pending rather than treated as successful access. Acceptance is complete only when each founder can see the private repository and perform the action appropriate to their assigned role.

## 9. Validation and acceptance

Before `Fynla/Fynla` is declared canonical, implementation must provide evidence for all of the following:

### History and refs

- All intended source branches exist in the target.
- All intended tags exist in the target.
- The rewritten history has a coherent commit graph.
- Author, date and message spot checks match the source.
- The four approved paths cannot be found in any target branch, tag or commit.
- The ProjectionLab filename and identified environment paths are absent from packed history, not merely deleted at the latest commit.

### Security and cleanliness

- A full-history secret scan reports no unresolved credible credentials.
- `.env` and representative environment variants are ignored.
- Safe example templates remain tracked.
- A representative root PNG is ignored while a nested legitimate PNG remains trackable.
- Dependencies, generated builds, caches and local state are absent from the tracked current tree.
- No secret values appear in validation output or migration logs.

### Product completeness

- Backend and all route files are present.
- Web and `/m` source trees and build configuration are present.
- Capacitor iOS and native iOS source and test projects are present.
- Tests, fixtures, agents, skills, prompts, personas, workflows and deployment source are present.
- Manifest and lockfile coverage is complete enough for a fresh developer checkout.
- The source tree passes repository-level integrity checks; code tests are run in proportion to any current-tree cleanup that could affect code or builds.

### Knowledge safety

- Every knowledge-only current-tree removal has a manifest entry.
- Every removed item is verified or copied into `fynlaBrain` without loss of unique content.
- Code-referenced documentation and runtime assets remain in the application repository.

### Team access

- The target is private and organisation-owned.
- `main` is the default branch.
- Chris, Azlan and Brett can access the repository after accepting their organisation invitations.
- The founders' access level and branch rules are documented.

## 10. Rollback and cutover

The migration is additive until verification finishes. If creation, sanitisation, push or validation fails, delete or correct only the incomplete organisation copy; do not alter `Stoff73/fynla` or the working desktop checkout.

After verification:

1. Share the target URL and clone instructions with all founders.
2. Update each maintained checkout to use the organisation remote deliberately.
3. Update automation, deployment and integration references only after their credentials and permissions are confirmed.
4. Observe at least one normal pull-request/check cycle in the organisation repository.
5. Decide separately whether the personal repository should be archived, renamed or retained as a read-only fallback.

Deletion or archival of the original repository, credential rotation, production deployment changes and external integration cutover are not implied by this design and require explicit execution decisions.

## 11. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Rewritten commit identifiers confuse existing clones | Keep the original untouched, publish clear fresh-clone/remote-change instructions, and block force pushes to canonical branches |
| A hidden secret remains in history | Full-history scan before publish; pause and report credible findings without printing values |
| Knowledge is removed from the code tree before being centralised | Hash-based manifest and `fynlaBrain` verification before each current-tree removal |
| Over-cleaning removes a runtime asset or build input | Dependency/reference scan plus targeted build/test checks before cleanup merge |
| Branches or tags are omitted | Before/after ref inventory and automated comparison |
| Founders cannot access the private repository | Verify organisation invitation state and repository access for each founder |
| Copied CI configuration initially fails | Observe checks before making them mandatory |

## 12. Out of scope

- Rewriting Fyn's in-app CoALA procedural, semantic or episodic memory architecture.
- Moving `fynlaBrain` back into the application repository.
- Deleting, archiving or force-rewriting `Stoff73/fynla`.
- Rotating or changing live production credentials without separate approval.
- Changing production hosting or deployment targets.
- Refactoring application behaviour merely to make the repository look smaller.
- Removing additional historical material without an explicit security finding and CSJ decision.

## 13. Success criteria

The migration succeeds when `Fynla/Fynla` is a private, organisation-owned, founder-accessible repository with the intended branches and tags; the useful engineering history is intact; the ProjectionLab export and three unsafe environment files are absent from all history; the current source tree contains every supported product surface, test and agent asset; excluded local/generated content is not tracked; and any knowledge-only cleanup is demonstrably preserved in `fynlaBrain`.
