# Development commissioning handoff: Google Drive marketing pipeline

This is the release-day checklist for the existing **Marketing Automation**
Shared Drive. It does not change that Drive's structure. Its direct folders
remain **Articles**, **Scripts**, and **Videos**.

Use this handoff with
`docs/pipeline/GOOGLE-DRIVE-SETUP-RUNBOOK.md`. The server administrator owns
the deployment and environment steps; a marketing team member owns the named
test assets and checks the visible Drive and tracker results.

## Release record

Fill in these placeholders before starting. Do not continue with a branch name
or an unreviewed branch tip in place of the full commit.

```text
Approved release commit: <APPROVED_RELEASE_COMMIT_SHA>
Previous approved commit: <PREVIOUS_APPROVED_COMMIT_SHA>
Deployment time: <UTC_TIMESTAMP>
Database backup: <PROTECTED_BACKUP_REFERENCE>
Server-change backup: <PROTECTED_SERVER_CHANGE_BACKUP_REFERENCE>
Build backup: <PROTECTED_BUILD_BACKUP_REFERENCE>
Named article test: <CONTROLLED_ARTICLE_FILENAME_OR_NONE>
Named video test: <CONTROLLED_VIDEO_FILENAME_OR_NONE>
Operator: <OPERATOR_NAME>
```

The development application root is:

```text
/home/u163-ptanegf9edny/www/csjones.co/fynla-app
```

All server commands below are run from that directory unless a step says it
is run on the local build machine.

## Stop conditions

Stop the commissioning and use the emergency-stop section if any of these are
true:

- the approved commit is not contained in `origin/dev`;
- the database backup is missing, empty, or cannot be verified;
- a server file would be overwritten or an existing server change has not
  been preserved;
- production has the pipeline enabled;
- development is not the sole runner named `csjones-development`;
- either social safety value is wrong;
- the Google preflight command returns a failure;
- a detector dry run lists an asset that has not been approved for processing;
- the queue, scheduler, migration, or application logs show a new error.

Never paste an environment file, a service-account key, a token, a Drive
identifier, a tracker identifier, or database credentials into deployment
notes, chat, screenshots, or source control.

## 1. Merge and identify the exact release

1. Merge the reviewed pull request into `dev` through the normal GitHub review
   process.
2. On the local build machine, update remote information and confirm the exact
   approved commit is now part of `origin/dev`:

   ```bash
   git fetch origin
   git merge-base --is-ancestor <APPROVED_RELEASE_COMMIT_SHA> origin/dev
   git show --no-patch --oneline <APPROVED_RELEASE_COMMIT_SHA>
   ```

3. Record the full 40-character release commit. A successful
   `git merge-base --is-ancestor` check has no output and returns status zero.
   If it fails, the change has not been merged into development; stop.

## 2. Build both development bundles locally

Use the supported Node.js 20 installation on the local build machine. Do not
build on the server.

```bash
git switch dev
git pull --ff-only origin dev
git rev-parse HEAD
./deploy/csjones-fynla/build.sh
test -s public/build/manifest.json
test -s public/m-build/manifest.json
```

The printed commit must be the approved release commit. The build is complete
only when both manifest checks pass. Record existing size warnings separately;
do not confuse a warning with a failed build.

## 3. Preserve the current server state and database

1. Inspect the current checkout before pulling anything:

   ```bash
   cd /home/u163-ptanegf9edny/www/csjones.co/fynla-app
   git status --short --branch
   git rev-parse HEAD
   git diff --check
   ```

2. Save the tracked diff and the list of untracked paths in an access-restricted,
   timestamped backup location outside the checkout. Do not put the backup in
   source control. Confirm every existing uncommitted server file is preserved.
   If an existing change overlaps the release, stop and resolve it before the
   pull.
3. Create a timestamped database backup using the host's approved backup tool.
   If the administrator uses the command line, use a protected client-options
   file so the password is not exposed:

   ```bash
   mysqldump --defaults-extra-file=<PROTECTED_CLIENT_OPTIONS_FILE> <DATABASE_NAME> > <PROTECTED_TIMESTAMPED_BACKUP_FILE>
   test -s <PROTECTED_TIMESTAMPED_BACKUP_FILE>
   ```

4. Record the protected backup reference and verification result. Do not record
   the database name, username, password, or backup contents in this handoff.
5. Preserve the existing `public/build` and `public/m-build` directories in a
   protected timestamped location. They are the asset rollback package. Do not
   delete old chunks yet because an in-flight browser session may still use
   them.

## 4. Enter maintenance mode and drain work

```bash
php artisan down
php artisan queue:restart
```

Wait for the existing worker to finish its current job. Confirm through the
host's worker control panel or process view that no pre-deployment pipeline job
is still running. Keep the application in maintenance mode until deployment,
migrations, cache rebuilding, preflight, and detector dry runs are complete.

## 5. Set the safe environment before deployment

The development environment file must contain the real values, but this
document must contain placeholders only:

```dotenv
PIPELINE_ENABLED=false
PIPELINE_RUNNER_NAME=csjones-development
GOOGLE_SERVICE_ACCOUNT_CREDENTIALS=/home/u163-ptanegf9edny/www/csjones.co/fynla-app/storage/app/private/google/<SERVICE_ACCOUNT_KEY_FILE>.json
PIPELINE_GOOGLE_DRIVE_FOLDER_ID=<MARKETING_AUTOMATION_SHARED_DRIVE_ID>
PIPELINE_GOOGLE_TRACKER_SHEET_ID=<NATIVE_TRACKER_SPREADSHEET_ID>
PIPELINE_POLL_FREQUENCY_MINUTES=5
PIPELINE_COMPOSE_AFTER_RENDER=false
PIPELINE_SOCIAL_DRY_RUN=true
PIPELINE_DRIVE_WEBHOOK_URL=
PIPELINE_DRIVE_WEBHOOK_TOKEN=
```

The key file must remain under `storage/app/private/google/`, outside the public
web root, readable only by the account running PHP. The path points to the file;
the JSON must not be copied into the environment file.

Clear stale configuration, then verify only non-secret settings:

```bash
php artisan config:clear
php artisan tinker --execute="echo 'runner='.(string) config('pipeline.runner_name').PHP_EOL; echo 'enabled='.(config('pipeline.enabled') ? 'true' : 'false').PHP_EOL; echo 'compose_after_render='.(config('pipeline.social.compose_after_render') ? 'true' : 'false').PHP_EOL; echo 'social_dry_run='.(config('pipeline.social.dry_run') ? 'true' : 'false').PHP_EOL;"
```

Expected development output is exactly:

```text
runner=csjones-development
enabled=false
compose_after_render=false
social_dry_run=true
```

Do not print the Google path, identifiers, key contents, or webhook values.

## 6. Verify production is disabled

On production, a production-authorised administrator must verify the master
switch without displaying any secrets:

```bash
php artisan tinker --execute="echo config('pipeline.enabled') ? 'FAIL pipeline enabled'.PHP_EOL : 'PASS pipeline disabled'.PHP_EOL;"
```

The only acceptable result is `PASS pipeline disabled`. Do not deploy this
development release to production and do not assign `csjones-development` to
production. If production cannot be checked, keep development disabled and
record the missing verification as a release blocker.

## 7. Pull the merged source and upload the local builds

Pull only the fast-forwarded development branch and verify the release:

```bash
git fetch origin
git pull --ff-only origin dev
git rev-parse HEAD
```

The printed commit must match the recorded approved release commit. If Git
reports that local changes would be overwritten, stop; do not reset or delete
the existing server changes.

Upload both locally built directories to these destinations using the approved
secure transfer method:

```text
public/build/   -> /home/u163-ptanegf9edny/www/csjones.co/fynla-app/public/build/
public/m-build/ -> /home/u163-ptanegf9edny/www/csjones.co/fynla-app/public/m-build/
```

Upload without deleting preserved old chunks. Confirm that both deployed
manifest files are non-empty. Do not run `npm install` or a front-end build on
the server.

## 8. Migrate and rebuild approved caches

Run only the normal forward migrations and the approved cache commands:

```bash
php artisan migrate --force
php artisan migrate:status
composer dump-autoload -o
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan config:cache
```

Review migration status and stop if any required migration is still pending.
Do not run any of these commands:

```text
php artisan optimize
php artisan route:cache
php artisan migrate:fresh
php artisan migrate:reset
php artisan db:wipe
```

Optimised route caching can make the application catch-all route hide the
server-rendered home page and the mobile `/m` route.

## 9. Run the read-only Google preflight

Keep `PIPELINE_ENABLED=false` and run:

```bash
php artisan pipeline:google-preflight
```

The command must exit with status zero. Its redacted summary must show:

- `PASS` for configured service-account credentials and authentication;
- `PASS` for runner `csjones-development`, with the pipeline disabled;
- `PASS` for the accessible Marketing Automation root;
- `PASS` for the direct `Articles`, `Scripts`, and `Videos` folders;
- `PASS` for a native Google spreadsheet, its `Pipeline` tab, and its headers
  in the required order;
- `SAFE` lines for the notification setting and social configuration;
- `SAFE` that the Drive webhook is not configured and polling remains
  available during initial commissioning.

The required header order is:

```text
Timestamp, Article slug, Article title, Script link, Status, Video link, Notes, Assignee
```

Record the PASS/SAFE result, command exit status, and time. Do not record any
identifier or credential value. A `FAIL` result blocks activation.

## 10. Verify the scheduler, queue, and logs

1. Confirm the host has exactly one Laravel scheduler entry for this
   development application and that it runs `artisan schedule:run` every
   minute.
2. Run `php artisan schedule:list` and confirm the four polling commands are
   present at the configured interval:
   `pipeline:detect-new-article-docs`, `pipeline:detect-new-articles`,
   `pipeline:detect-new-document-articles`, and
   `pipeline:detect-new-videos`.
3. Confirm the dedicated `pipeline` queue worker is healthy and no new failed
   job appeared during deployment.
4. Confirm the application can write its daily pipeline log under
   `storage/logs/pipeline*.log`. Record only times, status, and redacted error
   summaries.

Do not create a second scheduler or queue worker on production or another
development deployment.

## 11. Commission polling with controlled assets

Initial commissioning uses polling, not a webhook. Keep the application in
maintenance mode so the normal scheduler cannot race the dry runs.

1. Marketing names the exact approved copied asset or confirms that dry-run
   evidence alone is the test. Preserve all original legacy files.
2. The imported legacy video whose filename ends in `.mov_` is **invalid**. It
   does not end in `.mov` and must not be renamed or used as the controlled
   video test. A controlled video must be a separate approved copy named
   exactly `<ARTICLE_SLUG>.mp4` or `<ARTICLE_SLUG>.mov`.
3. Temporarily set development to `PIPELINE_ENABLED=true`, retain all three
   safety values below, and clear configuration:

   ```text
   PIPELINE_RUNNER_NAME=csjones-development
   PIPELINE_COMPOSE_AFTER_RENDER=false
   PIPELINE_SOCIAL_DRY_RUN=true
   ```

   ```bash
   php artisan config:clear
   php artisan pipeline:detect-new-article-docs --dry-run
   php artisan pipeline:detect-new-articles --dry-run
   php artisan pipeline:detect-new-document-articles --dry-run
   php artisan pipeline:detect-new-videos --dry-run
   ```

4. The dry runs must list only approved candidates. If any preserved legacy
   copy, incorrectly named file, or unexpected article is a candidate, use the
   emergency stop immediately and resolve the Drive contents without deleting
   originals.
5. If a mutating controlled test is approved, run only the detector relevant
   to that named test and let the dedicated queue process it. Check the tracker
   and Drive after each stage before continuing. Social delivery stays in dry
   run and automatic composition remains off.
6. Return the application to service with `php artisan up`, restore the single
   development scheduler and normal queue worker, and observe one polling
   interval. Confirm only the named asset is detected and each expected job is
   processed once.
7. Leave development enabled only when all evidence below is green. Otherwise
   set it back to false and keep polling stopped while investigating.

## 12. Evidence checklist

Store redacted evidence in the approved release record. Every item is required
unless marked optional:

- [ ] Approved release commit is contained in `origin/dev` and is deployed.
- [ ] Pre-deployment database backup is non-empty and recoverable.
- [ ] Existing uncommitted server files and both previous build directories
      are preserved.
- [ ] Production reports `PASS pipeline disabled`.
- [ ] Development is the sole runner, named `csjones-development`.
- [ ] `PIPELINE_COMPOSE_AFTER_RENDER=false` and
      `PIPELINE_SOCIAL_DRY_RUN=true` are confirmed without displaying secrets.
- [ ] Preflight exits zero with only the expected PASS/SAFE summary.
- [ ] Marketing Automation root and direct `Articles`, `Scripts`, and `Videos`
      folders are accessible.
- [ ] Tracker is a native Google spreadsheet with a `Pipeline` tab and the
      required ordered headers.
- [ ] Legacy assets are copied into the agreed folders and originals remain
      preserved; nothing was moved or deleted for commissioning.
- [ ] The invalid `.mov_` legacy file is excluded from the controlled video
      test.
- [ ] Detector dry runs list only approved candidates.
- [ ] Exactly one development scheduler and the dedicated pipeline queue worker
      are healthy; no unexpected failed job is present.
- [ ] `storage/logs/pipeline*.log` contains no new unexplained error.
- [ ] The controlled polling result and tracker/Drive changes match the named
      test; no duplicate work is visible.
- [ ] Browser smoke is recorded only if the release materially changes browser
      user interface behavior. If required, use the installed Google Chrome;
      otherwise record `not required — no user-interface change`.
- [ ] Emergency-stop values and operator access were verified without actually
      processing another asset.

Screenshots must hide account addresses, identifiers, filenames that identify
the service account, tokens, key paths, and database/server credentials.

## 13. Emergency stop

Disable dispatch before doing anything else:

1. Set development `PIPELINE_ENABLED=false`.
2. Run:

   ```bash
   php artisan config:clear
   php artisan config:cache
   php artisan queue:restart
   ```

3. Pause the host-managed pipeline worker and, if necessary, the single
   development scheduler. Wait for any current job to stop at its normal safe
   boundary.
4. Confirm the disabled state without exposing other environment values:

   ```bash
   php artisan tinker --execute="echo config('pipeline.enabled') ? 'FAIL pipeline enabled'.PHP_EOL : 'PASS pipeline disabled'.PHP_EOL;"
   ```

5. Record the stop time, last known job, redacted error, and tracker state. Do
   not delete Drive files, tracker rows, queue records, or original assets.

Polling is sufficient for recovery. Do not register or repair a webhook while
the pipeline is stopped.

## 14. Optional webhook follow-up

This is not part of initial commissioning. Consider it only after polling is
green and a stable public HTTPS webhook address plus a private random token are
stored in the development environment.

```dotenv
PIPELINE_DRIVE_WEBHOOK_URL=<PUBLIC_HTTPS_WEBHOOK_ADDRESS>
PIPELINE_DRIVE_WEBHOOK_TOKEN=<PRIVATE_RANDOM_TOKEN>
```

After clearing configuration, the administrator may run
`php artisan pipeline:drive-watch`. Confirm the active channel and resource
matching behavior without recording their values. Polling remains enabled as
the fallback. If notification registration fails, clear both optional settings
and continue with polling.

## 15. Code rollback, after the pipeline is disabled

The emergency-stop steps above must complete **before** code or database
rollback.

1. Keep the application in maintenance mode and preserve the incident-time
   database, logs, current server diff, and current build directories.
2. Revert the release through a reviewed Git commit merged into `dev`; do not
   reset the server checkout or discard uncommitted server files.
3. On the server, deploy the approved revert commit:

   ```bash
   php artisan down
   git fetch origin
   git pull --ff-only origin dev
   git rev-parse HEAD
   php artisan migrate:status
   composer dump-autoload -o
   php artisan cache:clear
   php artisan config:clear
   php artisan view:clear
   php artisan route:clear
   php artisan config:cache
   ```

4. Restore the preserved `public/build` and `public/m-build` asset package that
   matches the previous approved commit.
5. Do not run `migrate:rollback` automatically. If the reverted code cannot use
   the new schema, use only a separately reviewed down migration or restore the
   verified pre-deployment database backup through the host's approved process.
6. Reconfirm `PIPELINE_ENABLED=false`, the production-disabled result, routes,
   queue health, and logs. Run `php artisan up` only when the disabled rollback
   is stable.
7. Commissioning after a rollback starts again at the read-only preflight; it
   is never resumed from the failed step.
