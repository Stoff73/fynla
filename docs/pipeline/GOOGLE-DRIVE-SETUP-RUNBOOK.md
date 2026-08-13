# Google Drive marketing pipeline: setup and commissioning runbook

Use this runbook with a server administrator. It deliberately keeps the
existing Google Drive arrangement unchanged: the Shared Drive is **Marketing
Automation**, and its direct children are **Articles**, **Scripts**, and
**Videos**. Do not rename, move, duplicate, or replace these folders. It is
separate from the externally owned legacy folder in an individual's My Drive;
leave that folder and its original files alone.

## What the terms mean

- A **service account** is a Google identity used by the application rather
  than by a person. It lets the server access the Shared Drive unattended.
- A **Shared Drive** is the team-owned Google Drive space. Here it is Marketing
  Automation, with the three existing folders above.
- The **tracker** is the native Google spreadsheet used to record pipeline
  progress. An Excel workbook is not a tracker for this pipeline.
- **Polling** means the server checks Drive for changes at a regular interval.
  The interval is configurable; it defaults to five minutes.
- A **webhook** is an optional public web address to which Google sends a quick
  change notification. It makes detection quicker but does not replace polling.
- A **dry run** reports what a command would do without creating records,
  sending jobs, or changing Drive content.
- The **runner** is the one deployment permitted to operate this Shared Drive
  pipeline. The initial runner is `csjones-development`.

## Non-negotiable safety rules

Only one website may run the shared pipeline. During commissioning, set
`PIPELINE_RUNNER_NAME=csjones-development` only on the development deployment
and keep `PIPELINE_ENABLED=false` in production. Do not start a second worker
or scheduler for this Drive on another environment.

Archive the legacy Excel tracker and old/test assets; never delete them. Treat
imported legacy assets as preserved copies: do not move or delete their
originals in either Drive location. Do not use destructive database reset
commands such as `migrate:fresh`, `migrate:reset`, or `db:wipe`.

This integration uses a service account only. There is no Google sign-in,
OAuth client, client secret, callback address, browser authorisation, refresh
token, or recurring reauthorisation. The optional webhook has a separate
public HTTPS address and private token; it is not an authentication callback.

## 1. Prepare Google and the server

1. In Google Cloud, create or select the service account and download its JSON
   key through the normal restricted administrator process. Enable the Google
   Drive API and Google Sheets API in that project.
2. Put the key under `storage/app/private/google/` on the server, outside the
   public web root, using a non-identifying JSON filename. Restrict it so only
   the account running PHP can read it (for example, owner-read only). Never
   commit, email, paste, or log the file contents.
3. Add the service account's email address to the **Marketing Automation**
   Shared Drive with the least access that allows the required Drive and Sheets
   actions (normally Content manager). Do not grant access by signing in as the
   service account; there is no browser approval step.
4. Confirm the existing direct children are named exactly `Articles`,
   `Scripts`, and `Videos`. This is a read-only check; do not create replacement
   folders.

In the development deployment's `.env`, use placeholders only while preparing
the values:

```dotenv
PIPELINE_ENABLED=false
PIPELINE_RUNNER_NAME=csjones-development
GOOGLE_SERVICE_ACCOUNT_CREDENTIALS=<absolute path to storage/app/private/google/<service-account-key>.json>
PIPELINE_GOOGLE_DRIVE_FOLDER_ID=<Marketing Automation Shared Drive root ID>
PIPELINE_GOOGLE_TRACKER_SHEET_ID=
PIPELINE_COMPOSE_AFTER_RENDER=false
PIPELINE_SOCIAL_DRY_RUN=true
PIPELINE_POLL_FREQUENCY_MINUTES=5
PIPELINE_DRIVE_WEBHOOK_URL=
PIPELINE_DRIVE_WEBHOOK_TOKEN=
```

The credential path is the only key setting in `.env`; do not put JSON into an
environment variable. The Drive and tracker identifiers are environment
settings, never source-code defaults. After each `.env` change, run:

```bash
php artisan config:clear
```

## 2. Validate before creating anything

With `PIPELINE_ENABLED=false`, run the read-only readiness check:

```bash
php artisan pipeline:google-preflight
```

Before the tracker exists, a failure that says the tracker identifier is not
configured is expected. The command checks required identifiers before it
contacts Google, so complete the full permission and folder validation only
after the native tracker has been created. The command must not create, rename,
move, or delete Drive files, spreadsheets, or business records.

## 3. Archive the old tracker and create the native tracker

**Safe-state check before this mutating command:** archive the legacy Excel
workbook in its current location (or an approved archive location) and confirm
the production pipeline is still disabled. Do not delete the workbook or use
`--force`; `--force` creates another tracker.

**Mutating command — creates one native Google spreadsheet:**

```bash
php artisan pipeline:setup-tracker
```

Copy the printed identifier into `PIPELINE_GOOGLE_TRACKER_SHEET_ID` in the
development `.env`, then run `php artisan config:clear`. The required native
spreadsheet has a `Pipeline` sheet with these headers, in order:

`Timestamp`, `Article slug`, `Article title`, `Script link`, `Status`, `Video
link`, `Notes`, `Assignee`.

Now run the read-only check again. It must report `PASS` for service-account
authentication, the root folder, all three folders, the native tracker,
`Pipeline`, and the header order. It should also report that the runner is
`csjones-development`, the pipeline is disabled, and social publishing is in
safe mode.

```bash
php artisan pipeline:google-preflight
```

If it reports that the tracker is an Excel workbook, leave it archived and run
the tracker creation step above; do not convert the workbook in place.

## 4. Check article and video inputs

Marketing uploads only `.docx` files or creates native Google Docs in
`Marketing Automation/Articles`. A document filename becomes the article slug.
Use Word **Heading 1** for the article title. Use **Heading 2** and
**Heading 3** for nested sections. Links, bold, italic, bullet lists, and
numbered lists are retained.

Images, comments, tracked changes, footnotes, nested tables, text boxes,
WordArt, embedded charts, and nested list levels are not retained as authored.
Simple tables are flattened into text. Heading 4 and deeper formatting is
treated as normal paragraph text. Add unsupported visual content in the CMS
after import if required.

For a video, the filename must end **exactly** in `.mp4` or `.mov`, and the
basename must exactly match the article slug. Examples:

- Article slug `isa-allowance` accepts `isa-allowance.mp4` and
  `isa-allowance.mov`.
- `isa-allowance.mov_` is invalid because it does not end in `.mov`.
- `isa-allowance-final.mp4` is a different slug and will not match
  `isa-allowance`.

Place valid videos in `Marketing Automation/Videos`; scripts are written to
the existing `Marketing Automation/Scripts` folder. Never move or delete the
original legacy assets to make a test fit these names—use approved copies.

## 5. Development dry runs and controlled processing

**Safe-state check:** production remains `PIPELINE_ENABLED=false`; development
has the one runner name above; `PIPELINE_COMPOSE_AFTER_RENDER=false` and
`PIPELINE_SOCIAL_DRY_RUN=true` are still set. Keep a backup/archive of any
legacy test asset before using a copy for a controlled test.

The detectors exit early while the master flag is false. On the development
runner only, make this temporary enabling change, clear config, and immediately
run dry runs:

```dotenv
PIPELINE_ENABLED=true
```

```bash
php artisan config:clear
php artisan pipeline:detect-new-article-docs --dry-run
php artisan pipeline:detect-new-articles --dry-run
php artisan pipeline:detect-new-document-articles --dry-run
php artisan pipeline:detect-new-videos --dry-run
```

Each command should list candidates but make no changes. If the results are not
the intended approved copies, return `PIPELINE_ENABLED` to `false`, clear the
configuration, and investigate.

**Mutating controlled test:** only after the dry runs are correct, use one
approved copied article and one matching video. Run the appropriate detector
once, then let the dedicated queue process that one job:

```bash
php artisan pipeline:detect-new-article-docs
php artisan pipeline:detect-new-articles
php artisan pipeline:detect-new-document-articles
php artisan pipeline:detect-new-videos
php artisan queue:work --queue=pipeline --once
```

Use only the commands relevant to the approved test path. Check the tracker,
the generated script, and the video result before proceeding. Do not enable
automatic social composition: the two safe values above ensure that rendered
clips are not automatically composed for social publishing and that any
schedule remains a dry run.

## 6. Activate polling before webhook notifications

Once controlled checks are satisfactory, keep the same safe social values and
leave production disabled. Configure the normal Laravel scheduler on the one
development runner. The four detector commands run every
`PIPELINE_POLL_FREQUENCY_MINUTES`; the default is every five minutes:

- `pipeline:detect-new-article-docs`
- `pipeline:detect-new-articles`
- `pipeline:detect-new-document-articles`
- `pipeline:detect-new-videos`

Polling is the ongoing fallback and works with no webhook settings. Confirm a
new approved test copy is seen on the next polling interval before considering
the optional webhook.

## 7. Optional webhook, after polling is proven

**Safe-state check before this mutating command:** polling has worked on the
development runner; the public HTTPS webhook address and its private token are
stored only in that environment's `.env`; no secret is displayed in shell
history or documentation. Google cannot reach a local development address.

Set these two optional settings, clear configuration, then register the
channel:

```dotenv
PIPELINE_DRIVE_WEBHOOK_URL=<public HTTPS webhook address>
PIPELINE_DRIVE_WEBHOOK_TOKEN=<private random token>
```

```bash
php artisan config:clear
php artisan pipeline:drive-watch
```

**Mutating command:** `pipeline:drive-watch` creates or renews the Google
notification channel. The application validates the private token, channel
identifier, and resource identifier before accepting a notification. Repeated
valid notifications are coalesced safely. The scheduler renews the webhook
channel daily at 05:00; that is renewal, not detector polling. If webhook setup
fails, clear the two webhook settings and continue using polling.

## 8. Monitoring and emergency stop

Monitor the dedicated `pipeline` queue and the pipeline log files under
`storage/logs/`. Watch the tracker for the expected row sequence and inspect
failed jobs before retrying them. The preflight command is safe to rerun after
configuration or permission changes:

```bash
php artisan pipeline:google-preflight
```

**Emergency stop — safe-state action:** set `PIPELINE_ENABLED=false` on the
development runner, run `php artisan config:clear`, and stop the host-managed
pipeline worker/scheduler according to the server's normal service procedure.
Do not delete Drive files, the tracker, queue records, or original assets as a
response. Record the time and reason, then investigate the logs before a
controlled restart. Production remains disabled until a separately approved
release.

## 9. Deploying the approved development commit

Build the development front end locally, never on the server:

```bash
./deploy/csjones-fynla/build.sh
```

**Safe-state check before deployment:** record the approved commit SHA, take a
database backup through the host's approved backup facility or with a protected
credential-file command such as the following, then verify the backup
completed. Put the site into its normal maintenance state and drain the
existing worker. Never put database credentials on the command line or in this
document.

```bash
mysqldump --defaults-extra-file=<protected-client-options-file> <database-name> > <timestamped-backup-file>
```

On the server, fetch and deploy the recorded approved commit rather than an
unreviewed branch tip. Then run these commands in this order:

```bash
git fetch origin
git checkout --detach <approved-commit-sha>
git rev-parse HEAD
php artisan migrate --force
php artisan migrate:status
composer dump-autoload -o
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan config:cache
```

Upload the locally built assets as part of the deployment package. Do not run
front-end builds on the server. Do not run `php artisan optimize`,
`php artisan route:cache`, or destructive database reset commands. Restore
normal service only after migration status and the required checks succeed.
