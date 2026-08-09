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
| Google Cloud project + OAuth client | Google Cloud Console (see below) | `.env` |
| Client ID + Secret | Google Cloud → Credentials | `.env` |
| Anthropic API key with Opus access | console.anthropic.com | `.env` |
| Marketing Automation Drive folder ID | Drive URL — `/folders/<id>` | already in defaults |
| Tracker sheet ID | Created by `pipeline:setup-tracker` | `.env` (paste after step 4) |

Redirect URI on the OAuth client MUST be exactly:

```
http://localhost:8000/pipeline/oauth/google/callback
```

(For production later, add another OAuth client with `https://fynla.org/pipeline/oauth/google/callback`.)

---

## Step 1 — Paste the Google + Anthropic keys into `.env`

Open `.env` and fill in these five values (leave others as-is):

```
GOOGLE_OAUTH_CLIENT_ID=<the *.apps.googleusercontent.com string>
GOOGLE_OAUTH_CLIENT_SECRET=<the GOCSPX-... string>
GOOGLE_OAUTH_REDIRECT_URI=http://localhost:8000/pipeline/oauth/google/callback

ANTHROPIC_API_KEY=<sk-ant-...>
ANTHROPIC_OPUS_MODEL=claude-opus-4-7
```

Then:

```bash
php artisan config:clear
```

---

## Step 2 — Authorise Google

Make sure the dev server is running (`.\dev.ps1`). Then:

```bash
php artisan pipeline:authorise-google
```

It prints a URL. Open it in a browser signed in as **the account that owns
the Marketing Automation folder** (e.g. chris@fynla.org). Click **Allow**.
The browser redirects to `http://localhost:8000/pipeline/oauth/google/callback`
and shows a confirmation page. Back in the terminal you'll see:

```
✓ Authorised as chris@fynla.org
```

Refresh token is now stored encrypted in `pipeline_oauth_credentials`.

**Not required again** unless you revoke the app at
[myaccount.google.com/permissions](https://myaccount.google.com/permissions)
or the token goes unused for 6+ months.

---

## Step 3 — Create the tracker Sheet

```bash
php artisan pipeline:setup-tracker
```

Creates a new Google Sheet inside the Marketing Automation folder with:

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

To fully remove the OAuth grant, go to
[myaccount.google.com/permissions](https://myaccount.google.com/permissions),
find "Fynla Marketing Pipeline", click Remove Access. Also delete the row
from `pipeline_oauth_credentials` if you want to invalidate the stored
refresh token.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| `RuntimeException: No Google OAuth credential stored` | Re-run `pipeline:authorise-google` |
| `RuntimeException: Google did not return a refresh token` | Revoke at myaccount.google.com/permissions, re-run authorise |
| `RuntimeException: Pipeline daily cost cap reached` | Expected once you hit £2/day — wait for tomorrow |
| `RuntimeException: ANTHROPIC_API_KEY is not set` | Missing / typo in `.env`; check + `config:clear` |
| Google returns 403 on Drive upload | Confirm the OAuth account has Editor access to the Marketing Automation folder |
| Email doesn't arrive at marketing@fynla.org | Check `storage/logs/pipeline*.log`; queue worker running? |

Logs live at `storage/logs/pipeline-YYYY-MM-DD.log` (30-day retention).
