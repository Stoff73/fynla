# Google Drive Marketing Automation Readiness Implementation Plan

**Goal:** Make the existing Fynla marketing automation safe and straightforward to commission against the existing `Marketing automation` Shared Drive without changing its folder architecture.

**Base:** `origin/dev` at pull request 690 merge commit `9c9d2aa5f08e6d2a78f99f5094d6fd13abf2935a`.

## Global Constraints

- Keep the existing Shared Drive and its direct child folders named exactly `Articles`, `Scripts`, and `Videos`; do not refactor or replace that architecture.
- Never commit Google secrets, access tokens, webhook tokens, server credentials, or database credentials.
- Do not keep an environment-specific Drive folder identifier as a source-code default. Each environment must configure its root explicitly.
- Exactly one website may run the shared pipeline. Development (`csjones-development`) is the recommended initial runner; production remains disabled during commissioning.
- The preflight command must be read-only for Drive content and business data: it may not create, move, rename, update, or delete Drive files, spreadsheets, or business records. It may exchange a signed service-account assertion for a short-lived access token, but it may not mutate Drive content, business records, or the service-account credential file.
- The native tracker must be a Google spreadsheet containing a `Pipeline` sheet and these headers in order: `Timestamp`, `Article slug`, `Article title`, `Script link`, `Status`, `Video link`, `Notes`, `Assignee`.
- Excel workbooks stored in Drive are not valid trackers and must receive a clear remediation message.
- Shared Drive requests must retain `supportsAllDrives=true` and, for list operations, `includeItemsFromAllDrives=true`.
- Webhook authentication requires the configured private token plus a matching active channel identifier and resource identifier. Expired, stopped, unknown, or mismatched subscriptions are rejected.
- Repeated valid Drive notifications must be safe and must not create duplicate pipeline work.
- Polling remains available as the fallback even when webhook validation rejects a request.
- Social publishing remains safe during commissioning: `PIPELINE_COMPOSE_AFTER_RENDER=false` and `PIPELINE_SOCIAL_DRY_RUN=true`.
- Follow test-driven development for every behavior change: add a focused failing test, confirm the expected failure, implement minimally, then confirm it passes.
- Do not use Chromium for browser checks. Any required browser acceptance must use the installed Google Chrome.

## Task 1 — Configuration safety and single-runner identity

**Files:**

- Modify `config/pipeline.php`.
- Modify `.env.example`.
- Add or update focused pipeline configuration tests in the established test location.

**Behavior:**

1. Remove the obsolete hard-coded default for `PIPELINE_GOOGLE_DRIVE_FOLDER_ID` and default it to `null` or an empty value.
2. Add `PIPELINE_RUNNER_NAME`, defaulting to an empty value.
3. Ensure commands that require the root folder fail with a clear setup error when it is missing instead of querying Google with a stale or empty identifier.
4. Make the runner name visible to the future preflight command.
5. In `.env.example`, leave the Drive root and tracker identifiers blank and document `PIPELINE_RUNNER_NAME=csjones-development` as the recommended development value without enabling the pipeline.
6. Preserve existing Shared Drive flags.

**Tests and acceptance:**

- A missing root identifier produces a clear actionable error before any Drive request.
- A configured root identifier continues to work.
- No real environment identifier or secret is committed.
- The example settings keep `PIPELINE_ENABLED=false`.

## Task 2 — Read-only Google preflight and native tracker validation

**Files:**

- Add `app/Console/Commands/Pipeline/GooglePreflight.php`.
- Modify `app/Services/Pipeline/Google/GoogleDriveService.php` only as needed for read-only metadata and child-folder inspection.
- Modify `app/Services/Pipeline/Google/GoogleSheetsService.php` only as needed for read-only spreadsheet metadata and header inspection.
- Add `tests/Feature/Pipeline/GooglePreflightTest.php` and focused service tests if required.

**Command:**

```bash
php artisan pipeline:google-preflight
```

**Behavior:**

1. Check that the service-account credential path is configured.
2. Check that the service-account key can obtain an access token, without printing the credential contents, private key, or token.
3. Report the configured runner name and whether the pipeline is enabled.
4. Read the configured root folder metadata and confirm it is accessible.
5. Find `Articles`, `Scripts`, and `Videos` as direct children using read-only list operations. Do not use a find-or-create method.
6. Check that the tracker identifier is configured.
7. Read the tracker metadata and reject anything that is not a native Google spreadsheet.
8. Confirm the spreadsheet has a `Pipeline` sheet.
9. Confirm the required headers exist in the required order.
10. Report the notification address and the two social-publishing safety settings without exposing secrets.
11. Exit successfully only when every required check passes. Optional webhook configuration may be reported as not configured without failing initial polling-only commissioning.
12. Use concise `PASS`, `FAIL`, and `SAFE` or `WARNING` lines that a nontechnical administrator can understand.

**Excel remediation:**

When the configured tracker is an Excel workbook, the command must explain that it should be archived and that `php artisan pipeline:setup-tracker` creates the required native spreadsheet.

**Tests and acceptance:**

- All-success output and successful exit status.
- Missing client settings, connection, root identifier, child folder, tracker identifier, sheet, or header produces a failed exit status and an actionable line.
- Native spreadsheet accepted; Excel workbook rejected.
- No creation or mutation service method is called.
- Tokens and client secrets never appear in output or logs.
- Shared Drive query flags are retained.

## Task 3 — Harden Drive webhook validation and duplicate delivery safety

**Files:**

- Modify `app/Http/Controllers/Pipeline/DriveWebhookController.php`.
- Modify the Drive watch subscription model/service only where required to expose active channel state.
- Expand `tests/Feature/Pipeline/DriveWebhookTest.php`.

**Behavior:**

1. Continue requiring the configured private token.
2. Require `X-Goog-Channel-ID` to match the stored active subscription.
3. Require `X-Goog-Resource-ID` to match the stored active subscription.
4. Reject unknown, expired, stopped, or mismatched subscriptions without dispatching pipeline work.
5. Accept Google's initial `sync` handshake for a valid active subscription without dispatching work.
6. Accept a valid change notification and dispatch the existing change-sync job.
7. Make repeated delivery safe by coalescing or uniquely locking equivalent pending work using established Laravel queue/cache patterns; do not remove polling.
8. Do not log the private token.

**Tests and acceptance:**

- Missing or wrong private token rejected.
- Unknown channel rejected.
- Wrong resource rejected.
- Expired and stopped subscriptions rejected.
- Valid sync handshake acknowledged with no job.
- Valid change notification dispatches work.
- Repeating the same valid notification does not create duplicate pending work.
- Existing polling behavior is unchanged.

## Task 4 — Correct and add operator documentation

**Files:**

- Add `docs/pipeline/GOOGLE-DRIVE-SETUP-RUNBOOK.md`.
- Update relevant existing files under `docs/pipeline/` where their schedule, Word formatting, tracker, webhook, or deployment instructions conflict with current code.
- Keep the existing `Fynla-Article-Formatting-Guide.docx`; do not regenerate it.

**Content requirements:**

1. Use plain language and define technical terms.
2. Name the existing `Marketing automation` Shared Drive and its `Articles`, `Scripts`, and `Videos` folders without proposing a refactor.
3. Explain the one-runner rule and recommend `csjones-development` initially.
4. Archive, never delete, the legacy Excel tracker and old test assets.
5. State the exact article/video filename matching rules, including that `.mov_` is invalid.
6. Describe Word Heading 1 as the title and Heading 2/3 as sections; document links, bold, italic, bullets, and numbered lists.
7. Include Google permission setup, callback addresses, safe server settings, authorisation, native tracker creation, dry runs, controlled article/script/video tests, polling-first activation, webhook activation after hardening, monitoring, and emergency stop.
8. Correct stale daily schedules to the configurable polling interval, default five minutes.
9. Include pull request 690 deployment requirements: local front-end build, database backup, `php artisan migrate --force`, migration status, and the approved cache commands. Warn against `php artisan optimize` and `php artisan route:cache`.
10. Keep production disabled during initial commissioning and social publishing in safe mode.

**Acceptance:**

- The runbook is sufficient for a nontechnical team member and a server administrator to complete setup together.
- Existing stage documents no longer contradict current detector schedules or Word mappings.
- No secret is present.

## Task 5 — Focused and whole-branch verification

**Verification:**

1. Run the new configuration, preflight, tracker, and webhook tests.
2. Run all pipeline feature and unit tests.
3. Run PHP syntax and changed-file formatting checks.
4. Run the complete PHP test suites required by the repository quality command, using the local test database.
5. Run the front-end build with the supported Node.js version because the deployment package includes locally built assets.
6. Run `git diff --check` and inspect the complete branch diff against this plan.
7. Produce a development commissioning handoff listing the exact settings and commands, with secrets represented only by placeholders.

**Acceptance:**

- All new and existing relevant tests pass.
- The complete required test suite is green or any unrelated pre-existing failure is evidenced and explicitly separated from this branch.
- The front-end build succeeds.
- No tracked secret, generated dependency directory, local settings file, or test database file is included in the diff.
- Every Global Constraint has corresponding code, test, documentation, or commissioning evidence.
