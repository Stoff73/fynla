# Marketing Pipeline — Stage 3 Setup

Stage 3 = *"a landscape video appears in the Marketing Automation ▸ Videos folder → it gets transcribed, highlight-picked (if long), centre-cropped to 9:16, captions burned in, and emailed to marketing@ ready to post to Reels/TikTok."*

Prerequisites: **Stages 1 + 2 already set up** (see `STAGE-2-SETUP.md`).

---

## What you need

| Item | Where you get it |
|---|---|
| FFmpeg on PATH | `winget install Gyan.FFmpeg` |
| Python 3.10+ on PATH | `winget install Python.Python.3.12` |
| `whisper` CLI on PATH | `pip install openai-whisper` (~1.5 GB with torch) |
| A "Videos" subfolder in your Marketing Automation Drive folder | Create it manually in Drive |

The `whisper` CLI downloads its model (~500 MB for `small`) on first invocation. That first run adds ~2 min of one-off delay.

---

## Recording standards for the marketing team

For the pipeline to produce good clips, source videos should:

1. **Be recorded landscape** (16:9, 1920×1080 or higher)
2. **Keep the presenter in the middle third of the frame** — the crop takes a centre-vertical band. If the presenter drifts left/right, they'll be off-centre in the clip.
3. **Filename must match the article slug exactly.** For an article at `/insights/isa-allowance-2025-26`, the file must be `isa-allowance-2025-26.mp4`.
4. **Length**: 2–20 minutes ideal. ≤75 sec videos are posted whole. >20 min is fine but transcription slows.
5. **Upload to**: `Marketing Automation ▸ Videos ▸ {slug}.mp4` in Drive.

The video should have already had a script generated for it (Stage 2), meaning the InsightArticle is in the `scripted` pipeline state. If it isn't, the detect command skips it.

---

## First-time smoke test

1. Set `PIPELINE_ENABLED=true` in `.env`, `php artisan config:clear`
2. Upload a short landscape MP4 named `{some-published-article-slug}.mp4` to Marketing Automation ▸ Videos
3. Run:
   ```
   php artisan pipeline:detect-new-videos
   ```
   You should see: `→ queue {slug}.mp4 → pipeline_article #N`
4. Run the queue worker:
   ```
   php artisan queue:work --queue=pipeline --once
   ```
5. First run downloads the Whisper model — takes ~2 min extra.
6. Check:
   - `storage/app/social/video/{slug}/clip-1.mp4` exists
   - Tracker sheet has a new row with signed download URLs in Column G
   - Email arrived at marketing@fynla.org

---

## Daily flow (production)

The Kernel schedule now runs:

| Time | Command | Purpose |
|---|---|---|
| 07:00 | `pipeline:detect-new-articles` | Stage 1 — find new published Insight articles |
| 07:30 | `pipeline:detect-new-videos` | Stage 3 — find new videos in Drive, dispatch processing |
| 08:00 on the 1st of Jan/Apr/Jul/Oct | `pipeline:audit-social-videos` | Quarterly retention nudge (>1 year old files) |

The pipeline queue processes jobs one at a time by default. On SiteGround shared hosting, you'll want a cron entry every 5 min:

```
*/5 * * * * cd /path/to/fynla && php artisan queue:work --queue=pipeline --stop-when-empty --max-time=250
```

---

## Cost cap

Unified across all AI vendors (Anthropic today, others tomorrow):

| Cap | Value | Env |
|---|---|---|
| Per request | £0.30 | `PIPELINE_COST_PER_REQUEST_GBP` |
| Per day | £1.00 | `PIPELINE_COST_PER_DAY_GBP` |

Whisper is local (free). The only Stage 3 spend is:
- **Highlight selection** (Anthropic Opus) on videos > 75 sec. Roughly £0.02–£0.10 per video depending on length.

---

## Storage layout

```
storage/app/social/
  source/
    {slug}.mp4          ← the landscape source, downloaded from Drive
    {slug}.json         ← Whisper transcript (segment-level timestamps)
    {slug}.srt          ← Whisper subtitle file
    {slug}.txt|.vtt|.tsv ← Whisper other output formats
  video/
    {slug}/
      clip-1.mp4        ← generated 9:16 clip with captions burned in
      clip-1.srt        ← captions used for the clip (kept for auditing)
      clip-2.mp4        ← etc.
```

Files are kept forever. Quarterly audit email flags anything older than `PIPELINE_VIDEO_AUDIT_DAYS` (default 365).

---

## Rollback / disable

Same as Stage 2: `PIPELINE_ENABLED=false` in `.env` + `config:clear`. The scheduler stops dispatching new jobs. Existing pipeline_articles rows are untouched.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| "Could not find a 'Videos' subfolder" | Create the folder in Google Drive under Marketing Automation, then re-run |
| Whisper says `whisper: command not found` | `pip install openai-whisper` — re-verify with `whisper --help` |
| First video job takes >5 min | Whisper is downloading the `small` model (~500 MB) on first use — subsequent runs are fast |
| Captions are burned but text is offset / clipped | Adjust `PIPELINE_CAPTION_MARGIN_V` (default 140) or restyle in `VideoCropService::CAPTION_STYLE` |
| `RuntimeException: Pipeline daily cost cap reached` | Expected at £1/day — waits until midnight |
| Signed clip link returns 403 | 30-day expiry hit — re-run detect-new-videos on the article to regenerate the sheet row |
