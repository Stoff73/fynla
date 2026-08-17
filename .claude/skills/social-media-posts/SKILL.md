---
name: social-media-posts
description: Compose, tag, schedule, and report on Fynla social media posts (Instagram, Facebook, TikTok Reels; YouTube Shorts to come). Enforces the six Marketing Pipeline post rules — tracked links, campaign overrides, 2–5 relevant hashtags, playful brand tone, GA-informed scheduling, weekly report. Trigger whenever you're composing a caption, generating hashtags, building a post link, deciding a schedule slot, or writing the weekly performance report. Do not use for other marketing surfaces (transactional email, insight articles, in-app copy) — those have their own tone rules.
---

# Social Media Posts — Fynla Marketing Pipeline

This skill governs any code, prompt, or content that produces a
Fynla social media post. It applies to Instagram Reels, Facebook Reels,
and TikTok initially; YouTube Shorts will be added later.

The pipeline currently is: `pipeline_articles` (Stages 1–3) → generated
9:16 clip with captions → **this skill's territory: post composition +
scheduling + measurement** → posting via Buffer (or a chosen third-party
API) → GA-tracked landing pages.

---

## The six non-negotiable rules

### 1. Every post links back with a tracked URL

Never post a plain fynla.org URL. Use `UtmLinkBuilder` which produces:

```
https://fynla.org/insights/{slug}?utm_source={platform}&utm_medium=reel_9x16&utm_campaign={campaign_or_slug}&utm_content=clip-{n}
```

- `utm_source`: `instagram`, `facebook`, or `tiktok`
- `utm_medium`: `reel_9x16` (or the actual format if we later add other shapes)
- `utm_campaign`: article slug by default; overridden if a campaign is attached
- `utm_content`: which clip variant (`clip-1`, `clip-2`, ...) so we can compare highlights

**Never** hand-write UTM strings; always route through `UtmLinkBuilder`.

### 2. Approval-time destination override

The default destination is the source InsightArticle. At approval time,
the reviewer can choose one of:

- **Default** — the article the video was made from
- **Custom URL** — a specific Fynla page (calculator, feature, pricing, etc.)
- **Campaign landing page** — attached via `pipeline_campaigns` (name, landing_url, active_from, active_to)

If a campaign is selected, `utm_campaign` becomes the campaign slug, not
the article slug. `utm_source` and `utm_medium` still reflect the actual
post surface + format.

Any change to the destination must be recorded on the `pipeline_posts`
row (destination_url_final, campaign_id_final) so we can compare
proposed vs actual in the weekly report.

### 3. Hashtags: 2–5, relevant, currently searched

Every post has **2 to 5 hashtags** — never zero, never more than five.

`HashtagPicker` chooses them by prompting Claude with:
- Article title + summary
- Target platform (hashtag culture varies)
- A do-not-use list (banned/generic tags: #finance, #money, #uk — too generic; #fyp, #foryou — spammy)

**Choose based on common relevant searches** — not vanity tags. When
unsure, prefer specific over broad (`#ISAallowance` over `#saving`).

If you're adding an integration to a keyword tool (Ahrefs / SEMrush /
Google Trends), route through `HashtagPicker` and pass the volume data
as extra context to Claude — never bypass the picker and hard-code tags.

### 4. Playful, credible brand tone

Post captions share the tone the video was written in — the
`BrandVoicePrompt` (`app/Services/Pipeline/Prompts/BrandVoicePrompt.php`)
is the single source of truth for Fynla voice across scripts, captions,
and hashtags.

Rules the tone enforces (mirror in any post-composer prompt you write):

- **Light-hearted but credible.** Finance is serious; the delivery isn't.
- **UK English.** ISA, HMRC, quid, whilst, optimise. Never 401(k), IRS,
  Social Security, or US-first phrasing.
- **Speak to one person** — "you", "your". Never "everyone", "you all".
- **No personalised financial advice.** Everything is general education.
  Never name specific funds/providers unless the source article does.
- **No emojis in the first 40 characters** (thumbnail preview truncation
  looks unprofessional). One or two later in the caption is fine.
- **No hashtag stuffing.** Hashtags are their own line, after the CTA,
  not sprinkled through the copy.
- **No "click the link in bio"** — Instagram/TikTok culture, but we
  always ship a tracked URL that the platform handles per its own rules
  (Facebook is a real link; Instagram/TikTok users see it in bio via
  UTM cascade in later stages).

### 5. Schedule at optimum times with a test-and-learn loop

Two layers:

**Static best-practice defaults** (`config/pipeline.php → social.best_times`):
- Instagram Reels: Tue/Thu 11:00–13:00 GMT, Sat 09:00–10:30 GMT
- Facebook Reels: Wed/Fri 12:00–14:00 GMT
- TikTok: Tue/Thu 06:00, Wed/Fri 07:00, Fri 19:00–21:00 GMT

These are the industry-benchmark starting points from Sprout Social /
Hootsuite averages (as of 2026). Ship posts to these slots by default.

**Weekly Google Analytics recalculator** (`WeeklyOptimalTimeRecalculator`):
Runs every Monday. Pulls GA4 data for the last 8 weeks of pipeline-tagged
sessions (via `utm_source`), computes per-platform peak engagement
windows, adjusts the config-cached "best times" table. If GA data is
sparse (<20 sessions/platform), fall back to static defaults.

**Test-and-learn variants**:
Every post is composed as two variants (A + B). A is optimised for
"informed novice" (default Fynla audience: 30–55, financially curious
but not confident). B is optimised for a hypothesis under test that
week (younger / older / higher-income / pre-retirement — cycled by the
scheduler). The scheduler posts A to one platform and B to another so
audiences don't see both. Weekly report analyses the variant delta.

Never post the same caption to the same platform twice — post variants
are always fresh.

### 6. Weekly performance report

Every Monday 09:00, `pipeline:weekly-social-report` runs. It emails
marketing@fynla.org a report containing:

- **Volume** — posts per platform, total impressions, total reach
- **Engagement** — likes, comments, shares, saves per platform
- **Traffic** — GA sessions per platform, conversion events (if
  configured), assisted conversions
- **A/B verdict** — which variant won this week per platform + why
  (LLM-summarised)
- **What to boost** — top 1-3 posts by weighted engagement rate,
  with a suggested budget (£10-£50 per post based on organic reach)
- **Insight** — one-paragraph LLM-generated "what worked this week"

Data sources:
- Buffer/Publer/Meta Graph/TikTok API for platform metrics
- Google Analytics Data API v1 for on-site sessions
- `pipeline_posts` table for post metadata + variant assignments

The report is sent even if nothing was posted that week (with a
"No posts this week — here's why" summary) so silence is visible.

---

## How code should use this skill

When writing code that touches any of the six areas above:

1. **Composing a caption** → use `PostComposer` service. Never inline a
   caption prompt somewhere else.
2. **Choosing hashtags** → use `HashtagPicker` service. Never write
   hashtags directly in a caption prompt.
3. **Building a URL** → use `UtmLinkBuilder`. Never string-concat UTM
   parameters.
4. **Scheduling a post** → use `PostScheduler`. It reads the config
   best-times and the GA-recalculated overrides.
5. **Approval UI** → the destination override must offer article /
   custom URL / campaign as the three choices. Never allow a raw text
   field with no validation.
6. **Weekly report** → single command, single mailable, one place to
   change the format.

---

## Files this skill governs (Stage 4 — to be built)

```
app/Console/Commands/Pipeline/
  ScheduleReadyPosts.php
  WeeklySocialReport.php
  WeeklyOptimalTimeRecalculator.php

app/Http/Controllers/Api/Admin/
  PipelinePostsController.php      (approval queue CRUD)
  PipelineCampaignsController.php  (campaign CRUD)

app/Services/Pipeline/Social/
  BufferClient.php                 (or MetaClient/TikTokClient if direct)
  UtmLinkBuilder.php
  HashtagPicker.php
  PostComposer.php
  PostVariantGenerator.php
  PostScheduler.php
  GoogleAnalyticsReporter.php

app/Models/Pipeline/
  PipelinePost.php
  PipelineCampaign.php

app/Mail/Pipeline/
  WeeklyPerformanceReportMail.php

resources/js/views/Admin/Pipeline/
  PostApprovalQueue.vue

database/migrations/
  create_pipeline_posts_table.php
  create_pipeline_campaigns_table.php
```

Any post-related file outside `app/Services/Pipeline/Social/` is a code
smell — flag it during review.

---

## Failure modes to watch for

- **Bypassing the picker/builder services** — someone writes a caption
  prompt inline instead of using `PostComposer`. Result: tone drift,
  ungoverned hashtags, wrong UTMs. Fail the PR.
- **Hard-coded hashtags** — `#Fynla #savings #money` copy-pasted.
  Fail — must go through `HashtagPicker`.
- **Static schedule that ignores GA** — remember rule 5 has TWO layers
  (static defaults AND GA-informed override). Don't ship only one.
- **Silent Buffer failures** — third-party APIs fail; log to the
  `pipeline` channel and mark the post `failed`, don't silently retry
  forever.
- **No approval enforcement** — any code path that dispatches a post
  without an approved variant is a bug. All posting goes through the
  approval queue, no exceptions.
- **Weekly report shipping empty data** — if GA / platform metrics are
  unavailable, the report should still send with "data unavailable for
  {source}" rather than fail silently.

---

## When NOT to use this skill

- Composing transactional email (welcome, receipts, etc.) — different
  tone, no hashtags.
- Composing in-app microcopy — different tone (informative, not
  playful).
- Composing the Insight article body — that's authored, not generated.
- Any content that includes personalised financial advice — the pipeline
  is marketing only; advice content goes through a different, regulated
  path.
