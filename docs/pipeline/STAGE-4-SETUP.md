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
