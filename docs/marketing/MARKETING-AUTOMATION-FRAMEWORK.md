# Marketing Automation Framework

A reusable blueprint for an automated content-to-social pipeline: a human writes
one document, and the system turns it into a published article, a video script,
cut social clips, scheduled posts, and a performance report — with approval
gates at the points where a mistake would be public.

This document is the **handover artefact**. It is written so a new agent or
engineer can rebuild the framework for **any website**, not just the one it grew
up on. Implementation-specific values are isolated in [§7 Porting to a new
site](#7-porting-to-a-new-site) — everything else is intended to be general.

> **Status of this document.** It describes the framework as built for Fynla
> (Laravel 10 + Vue 3 + MySQL). Where a decision was Fynla-specific it is
> labelled. Where something was tried and dropped it is recorded in
> [§4 Retired and alternative technologies](#4-retired-and-alternative-technologies),
> because a different site may want exactly the thing we removed.

**Keep this updated.** See [§11 Maintaining this document](#11-maintaining-this-document).

---

## Contents

1. [Workflow map](#1-workflow-map)
2. [Stage detail](#2-stage-detail)
3. [Technologies and integrations (current)](#3-technologies-and-integrations-current)
4. [Retired and alternative technologies](#4-retired-and-alternative-technologies)
5. [Scheduling, timelines and rules](#5-scheduling-timelines-and-rules)
6. [Approval gates and emails](#6-approval-gates-and-emails)
7. [Porting to a new site](#7-porting-to-a-new-site)
8. [Challenges and solutions](#8-challenges-and-solutions)
9. [Things to watch out for](#9-things-to-watch-out-for)
10. [Rules — what not to do without permission](#10-rules--what-not-to-do-without-permission)
11. [Maintaining this document](#11-maintaining-this-document)

---

## 1. Workflow map

The spine is: **one human-authored document in cloud storage, and everything
else follows.**

```
                    ┌────────────────────────────┐
                    │  Cloud storage folder      │
                    │  Marketing Automation/     │
                    │    Articles/   (.docx)     │  <- human writes here
                    │    Scripts/    (.docx)     │  <- system writes here
                    │    Videos/     (.mp4)      │  <- human records here
                    └────────────────────────────┘
                                  │
         push notification (webhook) + daily polling fallback
                                  │
 ┌────────────────────────────────▼────────────────────────────────┐
 │ STAGE 5  Article ingest    parse .docx into structured blocks    │
 │                            creates a DRAFT article in the CMS    │
 └────────────────────────────────┬────────────────────────────────┘
                                  │
 ┌────────────────────────────────▼────────────────────────────────┐
 │ STAGE 1  Detect            published article not yet in the      │
 │                            pipeline, create a pipeline record    │
 └────────────────────────────────┬────────────────────────────────┘
                                  │
 ┌────────────────────────────────▼────────────────────────────────┐
 │ STAGE 2  Script            LLM writes a ~60s video script,       │
 │                            saved to Scripts/ as {YYYYMMDD}-{slug}│
 │                            EMAIL: "Script ready" (notify only)   │
 └────────────────────────────────┬────────────────────────────────┘
                                  │
                  human records a video, drops it in Videos/
                                  │
 ┌────────────────────────────────▼────────────────────────────────┐
 │ STAGE 3  Video processing                                        │
 │          transcribe, LLM picks highlights, second LLM pass       │
 │          validates each snippet, FFmpeg cuts and crops to 9:16   │
 │          >30s source: 2-3 short snippets; <=30s: ships whole     │
 └────────────────────────────────┬────────────────────────────────┘
                                  │
 ┌────────────────────────────────▼────────────────────────────────┐
 │ STAGE 3.5  CLIP APPROVAL GATE                                    │
 │          EMAIL with one-click magic links, plus admin queue      │
 │          reject, then regenerate from written feedback           │
 │          auto-approves N minutes before the scheduled slot       │
 └────────────────────────────────┬────────────────────────────────┘
                                  │
 ┌────────────────────────────────▼────────────────────────────────┐
 │ STAGE 4  Compose           caption, hashtags, tracked link       │
 │          slot chosen from analytics plus existing bookings       │
 │          PUBLISH GUARD: never schedules for a non-live article   │
 │          EMAIL: "Posts ready for approval"                       │
 └────────────────────────────────┬────────────────────────────────┘
                                  │
 ┌────────────────────────────────▼────────────────────────────────┐
 │ SCHEDULING     hand off to the social scheduler                  │
 │ MEASUREMENT    24h / 48h metrics collected back                  │
 │                EMAIL: weekly performance report                  │
 │                weekly recalculation of optimal posting times     │
 └──────────────────────────────────────────────────────────────────┘
```

**Design principle worth carrying over:** the human's only obligations are
*write the document* and *record the video*. Everything between is automated,
and every irreversible step (publishing, posting) sits behind a gate.

---

## 2. Stage detail

| Stage | Trigger | Input | Output | Gate |
|---|---|---|---|---|
| **5 — Article ingest** | Storage webhook / daily poll | `.docx` or native cloud doc | Draft article in CMS, structured blocks | Human publishes |
| **1 — Detect** | Daily cron | Published article not yet in pipeline | Pipeline record + job dispatch | None |
| **2 — Script** | Stage 1 | Article body | ~60s script saved to storage | None (notify only) |
| **3 — Video** | Daily cron finds new video | `.mp4` in Videos/ | Transcript, snippets, 9:16 clips | None |
| **3.5 — Clip approval** | Stage 3 completes | Clips | Approved / rejected clips | **Yes — human** |
| **4 — Compose** | Clip approval | Approved clips + article | Captions, hashtags, tracked links, slots | **Yes — human + publish guard** |
| **Schedule** | Hourly cron | Approved posts | Posts booked with the scheduler | None |
| **Measure** | 24h / 48h after post | Post IDs | Engagement metrics | None |
| **Report** | Weekly cron | Metrics + analytics | Emailed report, recalculated optimal times | None |

### Behavioural rules baked into the stages

- **Crawls only ever fill in what is missing.** They never re-create or
  regenerate something that already exists. Duplicates cost real money — every
  regeneration is an LLM call — and require manual cleanup.
- **One story means one record.** Deduplication is on the source file identifier
  first, then on the URL slug. If the slug is taken, the file is **skipped and
  logged**, never imported under a suffixed slug.
- **The source document is the source of truth.** Re-importing an edited
  document overwrites the CMS copy deliberately. Version history is the safety
  net, not a merge prompt.
- **Rejection excludes, it does not block.** Rejecting one clip must not stop
  the other approved clips, or the article, from moving forward.
- **Cost is capped** per request and per day; the pipeline stops rather than
  overspending.

---

## 3. Technologies and integrations (current)

### External services

| Service | Role | Notes for a new site |
|---|---|---|
| **Google Drive API v3** | Source of truth; file list, download, export, rename; **push-notification channels** for near-real-time detection | Channels expire — re-register on a daily cron. Any storage with a change webhook works (Dropbox, S3 events, SharePoint) |
| **Google Docs export** | Native cloud docs converted to `.docx` before parsing | Only needed if authors use Google Docs |
| **Google Sheets API** | Run tracker: status and signed download links | Optional; a database table does the same job less visibly |
| **Google Analytics Data API v4** (`runReport`) | Engagement data driving optimal posting times and the weekly report | Swap for whatever analytics the site uses (Plausible, Fathom, Matomo, Adobe) |
| **Google OAuth 2.0** | One authorisation **per environment**; refresh token stored in that environment's database | See §9 — this catches everyone out |
| **LLM API** (Anthropic Messages / xAI Grok) | Script generation, highlight selection, captions, hashtags, snippet validation | Provider-swappable; see §4 |
| **Buffer GraphQL API v2** | Scheduling to Instagram, Facebook, TikTok | Swap for **Metricool**, Later, Hootsuite, Sprout Social, Publer, or direct platform APIs — see §4 |
| **Stock image API** (Pexels) | Cover image when an article has none | Unsplash / Pixabay are drop-in equivalents |
| **SMTP** | Approval and notification emails | Any transactional provider |

### Local binaries — host dependencies that do NOT arrive with a deploy

| Binary | Role |
|---|---|
| **FFmpeg** | Cut, centre-crop landscape to 9:16 (1080x1920), `libx264` + `aac`, optional caption burn-in |
| **ffprobe** | Duration and stream probing |
| **Whisper** (`openai-whisper`, Python) | Transcription, run as a subprocess |

### Application stack (Fynla-specific — substitute freely)

Laravel 10 / PHP 8 (queued jobs, scheduled commands, observers, Eloquent),
MySQL 8, PHPWord for `.docx` parsing, Guzzle, Vue 3 admin screens, and
Pest + Pint + ESLint + Playwright for quality.

**The framework is not tied to this stack.** The portable parts are the stage
graph, the gates, the scheduling rules and the guardrails.

---

## 4. Retired and alternative technologies

Recorded because a different website may want precisely what was removed here.
Removal was usually about *fit*, not quality.

### Fully built, then retired

| Technology | What it did | Why it went | When to use it on a new site |
|---|---|---|---|
| **HeyGen** (`app/Services/HeyGenService.php`, still in the tree) | AI talking-head video generation from the script — avatar plus voice, round-robin across custom Photo Avatars, avatar groups and specific avatar IDs, each pairable with a voice | Moved to **real human-recorded video** for trust reasons in regulated financial content | **Strong fit** where no presenter is available, where volume is high, or where the brand has no face. Removes the only remaining human bottleneck in the chain |
| **Article scraper** (`ArticleScraperService`) | Fetched an article by URL and extracted the body — plain HTTP first, **Playwright fallback** for JS-rendered single-page apps, several extraction strategies, failing loud under 200 characters | Superseded by authoring in cloud storage; no scraping needed when you own the source | **Essential** if the new site syndicates or repurposes third-party or legacy content rather than authoring fresh |
| **Python image renderer** (`ImageRendererService` + `scripts/render_template.py`) | Rendered three image types — square, story, video thumbnail — from templates, with topic-aware variant selection | Static images de-prioritised in favour of video-first | Useful for text and quote-card channels (LinkedIn, X, Pinterest) where video underperforms |
| **Multi-aspect clip cutting** (`FFmpegService`) | Cut the generated video into 3-5 clips at LLM-identified timestamps, each rendered in **9:16, 1:1 and 16:9** | Simplified to 9:16 only for short-form platforms | Restore the 1:1 and 16:9 renders if targeting feed posts or long-form video |
| **`pipeline:process` monolith** | Ran stages 1.5 to 5 for a single article in one command, with `--skip-video` for fast iteration | Replaced by per-stage queued jobs — retryable, observable, independently gated | The monolith is genuinely easier to develop against; consider keeping it as a dev-only path |

### Provider swaps

- **LLM provider.** Originally Anthropic Claude; a migration plan to **xAI Grok**
  exists (`docs/grok-migration-plan.md`) and the running configuration currently
  reports `grok-4.3`. The client class, config block and cost tracking are all
  still named "Anthropic". **Lesson: name the abstraction after the role
  (`ScriptModelClient`), never the vendor.**
- **Buffer** replaced its own legacy REST v1 endpoint with GraphQL v2 mid-build,
  and rejects new Personal Keys on the old endpoint.

### Scheduler options — evaluated or available

The social scheduler is the most swappable component in the framework. Only the
client class talks to it; the composition, slot-selection and approval logic sit
above it and do not care which scheduler is underneath.

| Option | Status here | Notes |
|---|---|---|
| **Buffer** | **In use** — GraphQL API v2 | Moved off its own REST v1 mid-build; new Personal Keys are rejected on the old endpoint |
| **Metricool** | **Trialled outside the codebase** — an account is held, but it was never integrated in code | A ready alternative for a new site, and the account already exists. Stronger native analytics than Buffer, which could also replace part of the analytics feed used for slot selection |
| **Later**, **Hootsuite**, **Sprout Social**, **Publer** | Not evaluated | Conventional alternatives, all with scheduling APIs |
| **Direct platform APIs** | Not used | Removes a subscription and a dependency, but means owning each platform's app review, token refresh and rate limits separately — which is why a scheduler was chosen here |

---

## 5. Scheduling, timelines and rules

### Cron cadence

| When | Task | Why that time |
|---|---|---|
| 05:00 daily | Re-register the storage change webhook | Channels expire; renew before the working day |
| 06:45 daily | Detect new article documents | Drafts are ready before marketing opens the CMS |
| 07:00 daily | Detect published articles for the pipeline | Runs after ingest |
| 07:30 daily | Detect new videos | 30 minutes after article detect, so newly scripted rows have landed |
| Hourly | Schedule approved posts | Keeps the booking queue flowing |
| N minutes before a slot | Auto-approve still-pending clips | Prevents a missed approval from silently killing a post |
| Weekly (Mon 06:00) | Recalculate optimal posting times | Fresh analytics before the week's posts are booked |
| Weekly | Performance report email | Human review loop |
| Quarterly | Audit old source videos | Storage and retention hygiene |

**Ordering matters more than the exact clock times.** Each stage's detector must
run *after* the stage that feeds it has had a chance to complete.

### Snippet rules — video length policy

1. Source video **longer than 30 seconds** is cut into **2-3 snippets of about
   15 seconds** (bounded 12-18), each scheduled at a **different time**.
2. Snippets must be **catchy and standalone**. The tool cuts them, then a
   **second LLM pass validates** each one is coherent on its own and either
   adjusts the in and out points or drops it.
3. Source video **30 seconds or shorter** is scheduled directly, uncut.
4. **Every video links back to its article.**

### Slot selection rules

- Candidate slots come from **analytics engagement data** — which hours actually
  perform — combined with platform norms.
- The scheduler **walks a cursor** so multiple snippets from one video land in
  **distinct** slots.
- The search **starts from the last booked slot on that platform**, so two posts
  never collide on the same platform at the same minute.
- Article publish dates get a **recommended date and time** surfaced to the
  approver, who can override it.

### Cost controls

Per-request and per-day spend caps. The pipeline **stops** rather than
overspending. Costs are recorded per article at sub-penny precision — store
monetary values as `decimal`, never `float`, and pick a scale that does not
round a real cost to zero.

---

## 6. Approval gates and emails

Two gates, both human, both with a safety valve.

### Gate 1 — Clip approval, before anything is composed

- The email lists each clip with **one-click magic links** to approve or reject.
- An **admin queue** offers the same actions with more context.
- **Rejection requires feedback**, and that feedback is fed into a regeneration
  of that clip.
- **Rejection excludes only that clip.** Other approved clips, and the article,
  continue. This was an explicit correction during the build.
- Anything still pending **auto-approves** shortly before its scheduled slot.

### Gate 2 — Post approval, before anything is public

- The email lists composed captions, hashtags and tracked links.
- A **publish guard** independently refuses to schedule a post whose article is
  not live. A post linking to a 404 is worse than a late post.

### Email inventory

| Email | Purpose | Gate? |
|---|---|---|
| Script ready | A script was generated and saved | No — notification |
| Clips ready for review | Clips produced | No — notification |
| Clips awaiting approval | Approve or reject, with magic links | **Yes** |
| Posts approval ready | Composed posts awaiting sign-off | **Yes** |
| Video audit reminder | Old source videos need review | No |
| Weekly performance report | Metrics and recommendations | No |

**Email build notes** — hard-won, see §8: build every email from a shared layout
plus reusable module partials, never ad-hoc inline markup, and test in **Outlook
desktop** specifically.

---

## 7. Porting to a new site

Work through this list; everything else should transfer unchanged.

### Must change

| Item | Where |
|---|---|
| Cloud storage folder IDs (Articles / Scripts / Videos) | Config |
| OAuth client ID, secret, and **redirect URI per environment** | Config plus provider console |
| Analytics property ID | Config |
| Social scheduler token, and one profile ID per platform | Config |
| LLM API key, model name, cost-per-token table | Config |
| Notification recipient address | Config |
| Brand voice prompt, tone rules, hashtag pool | Prompt classes |
| Site URL, tracked-link parameters | Config |
| Email brand colours, logo asset, footer links | Email modules |
| Publishing gate duration (staging to production dwell time) | Config |

### Should review

- **Snippet length policy.** 15 seconds suits short-form video; a B2B site may
  want 30-60.
- **Platform set.** Instagram, Facebook and TikTok here; LinkedIn, X or
  long-form video elsewhere.
- **Whether video is needed at all.** For a text-first site, stop after Stage 2
  and schedule article links with generated images — see the retired image
  renderer in §4.
- **Whether authoring lives in cloud storage.** Any storage with a change
  webhook works.
- **Whether you need a talking head.** If no presenter is available, HeyGen (§4)
  removes the only human bottleneck in the whole chain.

### Can stay as-is

The stage graph, the gate positions, the dedupe rules, the cost caps, the
scheduling-collision logic, and the guardrails in §10.

---

## 8. Challenges and solutions

Real problems hit during the build, and what fixed them. These are the expensive
lessons — read this section before rebuilding.

| Challenge | Root cause | Solution |
|---|---|---|
| **The same story published twice** | Dedupe was on file ID only. A new file whose slug collided got a random suffix appended, creating a second article *by design* | Dedupe on file ID **and** slug, across **all** content types. On collision, **skip and log** — never suffix |
| **Duplicate scripts, real LLM spend** | The crawl regenerated things that already existed | Crawls search only for what is **missing** |
| **Every hyperlink silently stripped from imported articles** | The parser tested for a method the library's link class does not have, so the branch could never run. No error — links became plain text | Key on the class, not a guessed method. **Verify parser behaviour against real files, never against its own documentation** |
| **Every bulleted list flattened into loose paragraphs** | Real documents produce a different element class than expected, and that class *extends* the one being matched — so list items were swallowed by the paragraph branch | Match the specific class **before** its parent. Read the numbering definition from the document package to tell bullets from numbers |
| **Video processing timed out** | Probe timeout tuned for a local file, not a fresh large download | Raise timeouts for anything sitting behind a network fetch |
| **Approval magic links returned a server error** | Heavy composition ran inline in the web request | Dispatch composition **after the response** |
| **All snippets scheduled into the same slot** | Slot search restarted from the same point each time | Walk a cursor, and start from the last booked slot per platform |
| **A single failure killed the whole run** | Highlight selection was fatal | Make enrichment steps non-fatal — the full clip always ships |
| **Emails looked broken in Outlook** | CSS-styled `<a>` buttons (`display:inline-block` plus padding) collapse in Outlook's Word rendering engine; an image sized only in CSS rendered at its native 1760x795 | Table-cell buttons with `bgcolor` and cell padding; explicit `width` and `height` attributes on every image |
| **Import overwrote hand-edited CMS copy with no undo** | Version history skipped writes with no logged-in user — and the crawl runs from a console command with no user | Automated writes opt in to versioning under a named source |
| **Costs rounded to zero** | Monetary values cast as float, or to 2 decimal places, when real costs are sub-penny | `decimal` with enough scale |

---

## 9. Things to watch out for

- **OAuth is per environment.** The refresh token lives in each environment's
  own database, not in config. Local having a dead token tells you nothing about
  staging. Every environment needs its own one-time browser consent, and every
  environment's redirect URI must be registered with the provider.
- **Local binaries do not ship with a deploy.** FFmpeg and Whisper must be
  installed on each host. The pipeline fails at the video stage without them,
  often opaquely.
- **Webhook channels expire.** Without daily re-registration you silently fall
  back to polling and wonder why detection got slow.
- **The feature flag defaults to off.** Deploying the code starts nothing. That
  is correct, but say so explicitly or someone will assume it is broken.
- **Migrations are the invisible deploy step.** Schema changes do not announce
  themselves in a feature demo. List them explicitly in every handover.
- **Test the parser against genuine files.** Documents produced by the same
  library you parse with are *not* representative. A round-trip through your own
  writer can hide real bugs and invent false ones — a hand-built fixture is
  worth the effort for anything format-critical.
- **Never trust a docblock.** Three separate features in this build were
  documented as working and had never worked in production.
- **Outlook desktop is a different rendering engine.** If email matters, test
  there specifically, not just in a webmail client.
- **A scheduler's API can change under you.** Buffer moved REST to GraphQL and
  rejected new keys on the old endpoint.

---

## 10. Rules — what not to do without permission

These exist because breaking them costs money, trust, or public embarrassment.

### Never without explicit human approval

1. **Never post publicly.** Composition and scheduling are automated; the
   transition to *public* is gated. Keep a dry-run mode that mocks the final
   call, and keep it on by default outside production.
2. **Never publish an article to production** without the staging dwell time
   elapsing and an authorised publisher approving.
3. **Never send a real email to a real recipient list** from a non-production
   environment.
4. **Never regenerate paid content** — LLM calls, video renders — automatically
   on a re-run. Detect what exists and skip it.
5. **Never delete source material**: original videos, documents, or version
   history. Retire and archive instead.
6. **Never swap brand assets** (logos, imagery, colours) without approval, even
   when the current one renders poorly. Report it and ask.
7. **Never change which model or provider is used** without approval. It changes
   both cost and output quality.
8. **Never widen the schedule** — more posts, more platforms, higher frequency —
   without approval. Volume is a brand decision.

### Never at all

9. **Never write credentials into a file.** Not into an environment file, not
   config, not a fixture, even when asked directly. Hand the value back to a
   human to place.
10. **Never bypass the approval gates in code**, even "temporarily".
11. **Never let one item's failure block the rest.** Exclude the failure,
    continue the batch, and report clearly.
12. **Never report success you have not verified.** If a step could not be
    tested, say so plainly.

### Operational

13. Work on a branch; never commit directly to the shared integration or
    production branch.
14. Do not open a pull request without being asked.
15. Reseed after any migration or destructive database operation.
16. Never run a destructive database refresh on a shared environment.

---

## 11. Maintaining this document

**This is a living document.** It is the Marketing Automation Framework — the
single place a new agent or engineer is pointed at.

### When to update it

Update this document **in the same change** that does any of the following:

- Adds or removes a **channel**: a new social platform, a newsletter, a podcast feed
- Adds or removes an **integration**, or swaps a provider
- Changes a **schedule**, a **cadence**, or a **slot-selection rule**
- Adds, moves or removes an **approval gate** or an **email**
- Changes a **cost cap**, a **retention policy**, or a **snippet rule**
- Produces a **new hard-won lesson** — add it to §8 with the root cause, not
  just the fix
- **Retires a technology** — move it to §4 with why, and when a different site
  might still want it. Never delete the entry

### How to update it

1. Edit the relevant section. Do not append a new section for something that
   belongs in an existing one.
2. Add a line to the changelog below.
3. Keep §4 growing. Retired technology is the highest-value section for reuse,
   because it is the part a new site cannot rediscover on its own.
4. Keep §10 authoritative. If a rule is relaxed, record who relaxed it and when.

### Changelog

| Date | Change | By |
|---|---|---|
| 2026-08-22 | Initial version. Captured the workflow map, current and retired technologies, scheduling rules, approval gates, challenges and guardrails. | Handover from the Fynla marketing pipeline build |
| 2026-08-22 | Scheduler options promoted to their own table in §4 and named in the §3 swap column. Metricool corrected: an account is held and it was trialled outside the codebase, not merely considered. | CSJ |
