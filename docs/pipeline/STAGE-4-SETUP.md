# Marketing Pipeline — Stage 4 Setup

Stage 4 = *"clips are ready → compose two caption variants × three platforms → email marketing@ with an approval link → after approval, schedule to Buffer at optimum times → weekly performance report."*

Prerequisites: **Stages 1 + 2 + 3 already set up** (see `STAGE-2-SETUP.md`, `STAGE-3-SETUP.md`).

Governed by the `.claude/skills/social-media-posts/SKILL.md` skill.

---

## What you need

| Item | Where you get it |
|---|---|
| Buffer account with Instagram, Facebook, TikTok profiles connected | https://publish.buffer.com/ |
| Buffer **Personal Key** (Bearer token for the GraphQL API) | https://publish.buffer.com/developers/api |
| Buffer channel IDs (one per platform) | GraphQL query — see below (Buffer calls them "channels" in v2, they were "profiles" in v1) |
| GA4 Property ID | analytics.google.com → Admin → Property Settings (numeric identifier) |
| Service account has Viewer access to GA4 | analytics.google.com → Admin → Property access management |

---

## 1. Give the service account Analytics access

The service account automatically requests the Analytics read-only scope. Copy
the `client_email` value from the private service-account JSON key, then add
that email address as a **Viewer** on the GA4 property:

1. Open Google Analytics → **Admin**.
2. Open **Property access management** for the configured property.
3. Add the service-account email with the **Viewer** role.

No browser consent or refresh-token step is required.

---

## 2. Buffer setup

Buffer split their API in 2024: the **legacy REST v1** at `api.bufferapp.com/1/*` only
accepts old-style access tokens, and the **new GraphQL API** at `api.buffer.com/graphql`
only accepts **Personal Keys**. Fynla targets GraphQL — Personal Keys are the only
credential you need.

Sign up at https://buffer.com and:

1. **Add each social account** (Instagram Business, Facebook Page, TikTok Business).
2. **Create a Personal Key**: https://publish.buffer.com/developers/api → **Personal Key** → **Create**. Copy the token (single-use display).
3. **Find your organisation ID and channel IDs**. Post to `https://api.buffer.com/graphql`:

```bash
curl -X POST https://api.buffer.com/graphql \
  -H "Authorization: Bearer YOUR_PERSONAL_KEY" \
  -H "Content-Type: application/json" \
  -d '{"query":"query { account { organizations { id name channels { id service serviceId name isDisconnected } } } }"}'
```

Response gives every organisation and every channel. For each connected platform, note the channel `id` (24-char hex) alongside its `service` (`instagram`, `facebook`, `tiktok`). Skip anything with `isDisconnected: true`.

4. **Paste into `.env`**:

```
BUFFER_ACCESS_TOKEN=<personal-key>
BUFFER_PROFILE_INSTAGRAM=<instagram-channel-id>
BUFFER_PROFILE_FACEBOOK=<facebook-channel-id>
BUFFER_PROFILE_TIKTOK=<tiktok-channel-id>    # leave blank if TikTok not connected — scheduler skips it
```

The env var name still says `BUFFER_PROFILE_*` for backwards compatibility. Buffer's v2 vocabulary calls the same 24-char hex a "channel ID" — same string, different label.

5. `php artisan config:clear`

**Free trial**: 14 days. Costs £15/mo after (Essentials plan) which covers up to 6 channels. If you outgrow it, the `BufferClient` service is designed to be swapped for `PostizClient` (self-hosted OSS) with ~1 day of work.

### Video handling

`BufferClient::schedule()` passes videos to Buffer as a **public URL**, not as a
multipart upload. The URL points at our own signed clip route
(`pipeline.clip.download`, 30-day TTL) — Buffer fetches it, transcodes it, and
schedules the post. This means:

- The clip must remain on the fynla server for the full window between compose and
  publish. Don't delete `storage/app/social/video/{slug}/clip-*.mp4` until Buffer
  has confirmed the post as `sent`.
- If the signed URL expires before Buffer fetches it, the post fails with
  `Buffer schedule failed (InvalidInputError)`. Regenerate the sheet row to bump
  the TTL.

---

## 3. Google Analytics 4

Paste your GA4 property ID (numeric, no `properties/` prefix):

```
PIPELINE_GA_PROPERTY_ID=<GA4-property-ID>
```

Then `php artisan config:clear`.

---

## 4. First-time smoke test

Before enabling anything, confirm this is the one development runner named
`csjones-development`, production still has `PIPELINE_ENABLED=false`, and
development retains `PIPELINE_COMPOSE_AFTER_RENDER=false` and
`PIPELINE_SOCIAL_DRY_RUN=true`. If any condition is not confirmed, stop and do
not run this smoke test. See `GOOGLE-DRIVE-SETUP-RUNBOOK.md` for the full
commissioning sequence.

1. On that development runner only, set `PIPELINE_ENABLED=true` in `.env`, then run `php artisan config:clear`.
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

## Polling/weekly flow (production)

The four detector commands poll every `PIPELINE_POLL_FREQUENCY_MINUTES`
(default five minutes). The optional Drive webhook is renewed daily at 05:00;
that daily renewal is not detector polling.

| Interval | Command | Purpose |
|---|---|---|
| Configurable; default every 5 minutes | `pipeline:detect-new-article-docs` | Stage 5 import |
| Configurable; default every 5 minutes | `pipeline:detect-new-articles` | Stage 1 |
| Configurable; default every 5 minutes | `pipeline:detect-new-document-articles` | CMS article detection |
| Configurable; default every 5 minutes | `pipeline:detect-new-videos` | Stage 3 |
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
| `GA4 report failed: HTTP 403` | Confirm the service-account email has Viewer access to the configured GA4 property |
| `Pipeline daily cost cap reached` | Expected at £1/day — waits until midnight |
| `Public API tokens are not accepted for REST API access` (401) | Personal Key hitting the legacy `api.bufferapp.com/1/*` endpoint — you're on an old `BufferClient`; pull latest |
| Buffer schedule 401 | Personal Key expired or revoked; regenerate at publish.buffer.com/developers/api |
| `Buffer schedule failed (UnauthorizedError)` | Channel disconnected in Buffer, or channel ID belongs to a different organisation |
| `Buffer schedule failed (InvalidInputError)` | Video URL 4xx'd — check the signed URL is still in TTL and the file exists |
| Weekly report has empty data | GA + Buffer both down; check `storage/logs/pipeline*.log` |

---

## Rollback

Same as previous stages: `PIPELINE_ENABLED=false` in `.env` + `config:clear`. The scheduler stops dispatching, existing pipeline data is preserved.

To disable ONLY Stage 4 composition (keep Stages 1-3 running):
```
PIPELINE_COMPOSE_AFTER_RENDER=false
```
Then clips still generate for marketing to post manually, but no auto-compose to Buffer.
