# Marketing Pipeline — Stage 4 Setup

Stage 4 = *"clips are ready → compose two caption variants × three platforms → email marketing@ with an approval link → after approval, schedule to Buffer at optimum times → weekly performance report."*

Prerequisites: **Stages 1 + 2 + 3 already set up** (see `STAGE-2-SETUP.md`, `STAGE-3-SETUP.md`).

Governed by the `.claude/skills/social-media-posts/SKILL.md` skill.

---

## What you need

| Item | Where you get it |
|---|---|
| Buffer account with Instagram, Facebook, TikTok profiles connected | https://publish.buffer.com/ |
| Buffer API access token | publish.buffer.com → Settings → Integrations → API |
| Buffer profile IDs (one per platform) | `curl -H "Authorization: Bearer $TOKEN" https://api.bufferapp.com/1/profiles.json` |
| GA4 Property ID | analytics.google.com → Admin → Property Settings (looks like `530097849`) |
| Re-consent Google OAuth | Analytics readonly scope was added — see below |

---

## 1. Re-consent Google OAuth (adds Analytics scope)

Stage 4 adds `https://www.googleapis.com/auth/analytics.readonly` to the OAuth grant so we can read GA4. Re-run:

```bash
php artisan pipeline:authorise-google
```

Visit the URL, sign in as `marketing@fynla.org` (or whichever account owns the GA property), click **Advanced → Go to Fynla Marketing Pipeline (unsafe)** if you see the "unverified app" warning, then **Allow**. Google will show the new scopes list including "See Google Analytics data".

The refresh token is stored encrypted in `pipeline_oauth_credentials` — replaces the previous grant.

---

## 2. Buffer setup

Sign up at https://buffer.com and:

1. **Add each social account** (Instagram Business, Facebook Page, TikTok Business).
2. **Grab your access token**: Settings → Integrations → API → **Access Token**.
3. **Get your profile IDs**:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" https://api.bufferapp.com/1/profiles.json
```

Response has an array of profiles. For each connected platform, note the `id` field (24-char hex) and its `service` value (`instagram`, `facebook`, `tiktok`).

4. **Paste into `.env`**:

```
BUFFER_ACCESS_TOKEN=1/abcd1234...
BUFFER_PROFILE_INSTAGRAM=5f9a...
BUFFER_PROFILE_FACEBOOK=5f9b...
BUFFER_PROFILE_TIKTOK=5f9c...
```

5. `php artisan config:clear`

**Free trial**: 14 days. Costs £15/mo after (Essentials plan) which covers up to 6 channels. If you outgrow it, the `BufferClient` service is designed to be swapped for `PostizClient` (self-hosted OSS) with ~1 day of work.

---

## 3. Google Analytics 4

Paste your GA4 property ID (numeric, no `properties/` prefix):

```
PIPELINE_GA_PROPERTY_ID=530097849
```

Then `php artisan config:clear`.

---

## 4. First-time smoke test

1. `PIPELINE_ENABLED=true` in `.env`, `config:clear`
2. Confirm at least one InsightArticle has been through Stages 1–3 (has clips in `storage/app/social/video/{slug}/`)
3. Manually trigger the composer:
   ```bash
   php artisan tinker
   ```
   ```
   $article = App\Models\Pipeline\PipelineArticle::latest()->first();
   App\Jobs\Pipeline\ComposePostsJob::dispatch($article);
   ```
4. Check:
   - 6 rows in `pipeline_posts` (2 variants × 3 platforms per clip)
   - Email arrived at marketing@fynla.org with an approval link
   - Approval queue at http://localhost:8000/admin/pipeline/posts shows them
5. Approve one post via the UI
6. Run the scheduler:
   ```bash
   php artisan pipeline:schedule-ready-posts
   ```
7. Check Buffer's queue at https://publish.buffer.com — should see your scheduled post

---

## Daily/weekly flow (production)

The Kernel schedule now runs:

| Time | Command | Purpose |
|---|---|---|
| 07:00 daily | `pipeline:detect-new-articles` | Stage 1 |
| 07:30 daily | `pipeline:detect-new-videos` | Stage 3 |
| Every hour | `pipeline:schedule-ready-posts` | Stage 4 — push approved posts to Buffer |
| Monday 06:00 | `pipeline:recalculate-optimal-times` | Stage 4 — refresh best_times from GA |
| Monday 09:00 | `pipeline:weekly-social-report` | Stage 4 — email marketing@ |
| Quarterly | `pipeline:audit-social-videos` | Stage 3 retention |

---

## Static best-times (starting point before GA data)

These live in `config/pipeline.php` → `social.best_times`. They're industry-benchmark GMT windows from Sprout Social / Hootsuite averages. Once `pipeline:recalculate-optimal-times` has enough GA data (20+ sessions per platform), it overrides these via cache.

```
Instagram: Tue/Thu 12:00, Sat 09:30
Facebook:  Wed/Fri 13:00
TikTok:    Tue 06:00, Wed 07:00, Fri 19:30
```

---

## Post rules recap (from social-media-posts skill)

Read [.claude/skills/social-media-posts/SKILL.md](../../.claude/skills/social-media-posts/SKILL.md) before adding any post-related code. The rules:

1. Every post links back via `UtmLinkBuilder` (never raw fynla.org URLs)
2. Approval-time destination override: article / custom URL / campaign
3. 2–5 relevant hashtags via `HashtagPicker` (banned list filters junk)
4. Playful, credible UK tone via `PostComposer` (shares `BrandVoicePrompt` with the video)
5. Optimum-time scheduling with weekly GA-informed recalibration + A/B variants
6. Weekly Monday report to marketing@fynla.org

---

## Approval UI

`http://localhost:8000/admin/pipeline/posts` (admin login required).

Filter by status (default: `awaiting_approval`) and platform. Per post:
- Edit caption
- Edit hashtags (space-separated)
- Pick destination: article / custom / campaign
- Approve or reject with a reason

Once approved, the hourly `schedule-ready-posts` cron picks it up.

---

## Campaigns

Create via `POST /api/admin/pipeline/campaigns` (any admin auth). Fields:
- `name` (display)
- `slug` (used as `utm_campaign`)
- `landing_url` (destination when picked)
- `active_from`, `active_to` (optional date range)

Reviewer picks a campaign at approval time — the UtmLinkBuilder switches the destination and `utm_campaign` accordingly.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| Approval email arrives but /admin/pipeline/posts is empty | Check status filter (default shows `awaiting_approval` only) |
| `BUFFER_ACCESS_TOKEN is not set` | Missing / typo in `.env`; `config:clear` after fixing |
| `GA4 report failed: HTTP 403` | Re-run `pipeline:authorise-google` to re-consent with the analytics.readonly scope |
| `Pipeline daily cost cap reached` | Expected at £1/day — waits until midnight |
| Buffer schedule 401 | Access token expired or revoked; regenerate in Buffer settings |
| Weekly report has empty data | GA + Buffer both down; check `storage/logs/pipeline*.log` |

---

## Rollback

Same as previous stages: `PIPELINE_ENABLED=false` in `.env` + `config:clear`. The scheduler stops dispatching, existing pipeline data is preserved.

To disable ONLY Stage 4 composition (keep Stages 1-3 running):
```
PIPELINE_COMPOSE_AFTER_RENDER=false
```
Then clips still generate for marketing to post manually, but no auto-compose to Buffer.
