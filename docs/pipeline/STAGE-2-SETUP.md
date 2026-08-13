# Marketing Pipeline — Stage 2 Setup

Stage 2 = *"turn a published Insight article into a 60-second video script,
save it to Google Drive, log the row on a tracker Sheet, and email
marketing@fynla.org with the link."*

This file walks through the one-time setup. All the code exists on the
`marketing` branch — this is purely operational.

---

## What you need to have ready

| Item | Where you get it | Storage |
|---|---|---|
| Google Cloud project + service account | Google Cloud Console | Google Cloud |
| Downloaded service-account JSON key | Google Cloud → Service Accounts → Keys | Private server file; never Git |
| Anthropic API key with Opus access | console.anthropic.com | `.env` |
| Marketing Automation Drive folder ID | Drive URL — `/folders/<id>` | already in defaults |
| Tracker sheet ID | Created by `pipeline:setup-tracker` | `.env` (paste after step 3) |

---

## Step 1 — Store the service-account key

Create a private folder outside the public web root and upload the downloaded
JSON key there. A recommended server location is:

```
storage/app/private/google/fynlaautomarketing.json
```

Restrict the file so only the server account can read it. Do not put it in
`vendor/` because dependency deployments may replace that directory. Do not
commit it to Git.

Open `.env` and add the absolute path plus the Anthropic settings:

```
GOOGLE_SERVICE_ACCOUNT_CREDENTIALS=/absolute/server/path/storage/app/private/google/fynlaautomarketing.json

ANTHROPIC_API_KEY=<sk-ant-...>
ANTHROPIC_OPUS_MODEL=claude-opus-4-7
```

Then:

```bash
php artisan config:clear
```

---

## Step 2 — Give the service account Shared Drive access

Open the JSON key and copy its `client_email` value. In Google Drive, add that
email address as a **Content manager** of the Marketing Automation Shared Drive.
The pipeline then authenticates automatically; there is no browser login,
callback URL, refresh token, or recurring reauthorisation.

Run `php artisan config:clear` after changing the key path.

---

## Step 3 — Create the tracker Sheet

```bash
php artisan pipeline:setup-tracker
```

Creates a native Google Sheet directly inside the Marketing Automation Shared
Drive folder with:

- Header row (Timestamp, Article slug, Article title, Script link, Status,
  Video link, Notes, Assignee) — bold, frozen
- Status column dropdown: Script Ready / Video In Progress / Video Ready /
  Published / Rejected

Prints the sheet ID. Copy it into `.env`:

```
PIPELINE_GOOGLE_TRACKER_SHEET_ID=<the printed ID>
```

Then:

```bash
php artisan config:clear
```

---

## Step 4 — Enable the pipeline + smoke test

Flip the master switch in `.env`:

```
PIPELINE_ENABLED=true
```

Then clear config and trigger a run manually:

```bash
php artisan config:clear
php artisan pipeline:detect-new-articles
```

If a published `InsightArticle` exists that hasn't been through the pipeline,
you should see:

1. Row appears in `pipeline_articles` with status `detected`
2. `ProcessInsightArticleJob` dispatched to the `pipeline` queue
3. When the queue runs it: script is generated (Opus call), Google Doc appears
   in Drive, tracker sheet gets a new row, email arrives at
   marketing@fynla.org

To run the queue in local dev:

```bash
php artisan queue:work --queue=pipeline --once
```

---

## Cost caps

| Guardrail | Default | Overrideable via |
|---|---|---|
| Per-request | £0.30 | `PIPELINE_COST_PER_REQUEST_GBP` |
| Per-day | £2.00 | `PIPELINE_COST_PER_DAY_GBP` |

Actual expected spend for 1 article every 2–3 days ≈ **£0.03/day** —
the caps are ~40× headroom. Once the daily cap is hit, `ProcessInsightArticleJob`
throws `RuntimeException: Pipeline daily cost cap reached` and the article
moves to `failed` state. Re-run tomorrow.

---

## Rollback / disable

Set `PIPELINE_ENABLED=false` in `.env` and clear config. The scheduler
entry becomes a no-op. Existing rows in `pipeline_articles` are untouched.

To remove Google access, set `PIPELINE_ENABLED=false`, remove the service
account from the Shared Drive, and delete its private JSON key from the server.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| `GOOGLE_SERVICE_ACCOUNT_CREDENTIALS is not set` | Add the absolute JSON-key path to `.env`, then run `config:clear` |
| Credentials file is not readable | Confirm the path and file permissions for the server account |
| `RuntimeException: Pipeline daily cost cap reached` | Expected once you hit £2/day — wait for tomorrow |
| `RuntimeException: ANTHROPIC_API_KEY is not set` | Missing / typo in `.env`; check + `config:clear` |
| Google returns 403 on Drive upload | Add the service-account `client_email` as a Content manager of the Shared Drive |
| Email doesn't arrive at marketing@fynla.org | Check `storage/logs/pipeline*.log`; queue worker running? |

Logs live at `storage/logs/pipeline-YYYY-MM-DD.log` (30-day retention).
