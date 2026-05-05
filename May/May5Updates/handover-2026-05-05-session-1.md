---
type: handover
mode: end-of-day
date: 2026-05-05
session: 1
branch: feature/csj/cms-insights-deploy-note
previous_session: 2026-05-04 session 2 (context-clear)
---

# Handover — 2026-05-05, Session 1

## Where we left off

End of 2026-05-04 (session 75). The last block of work was the CMS-into-/insights refactor: doc-imported articles now publish through the existing `/insights` SPA pipeline (PublicLayout chrome, hub listing, structured HTML rendering) instead of the standalone Blade page they were on. PR [#240](https://github.com/Stoff73/fynla/pull/240) merged into `dev`, deployed to `csjones.co/fynla`, end-to-end browser-verified. PR [#241](https://github.com/Stoff73/fynla/pull/241) is open with the session deploy note (`May/May4Updates/deployInsightsCMSIntegration.md`).

## What shipped today (2026-05-04)

- `feat(cms): Document Articles CMS — drag-drop .docx import + publish` (#240, squash) — combines session 73's CMSFix bundle + session 75's `/insights` integration into a single squashed commit on `dev` (`3afb33c`)
- `docs(deploy): record session 75 csjones deploy of CMS-into-/insights` (`e1402ab`) — session deploy note on `feature/csj/cms-insights-deploy-note`
- Live deploy to `https://csjones.co/fynla`: 9 PHP files rsynced, 2 legacy files deleted server-side (`PublicDocumentArticleController.php`, `resources/views/articles/show.blade.php`), `public/build/` rotated with old-chunk merge, autoload regenerated, caches cleared, optimize cached
- Pre-existing dev-server gap fixed in passing: pushed `AgentInternalController.php` + `AgentTokenAuth.php` (referenced from `routes/api.php` but never deployed in session 74's sparse rsync). `php artisan route:list` now runs cleanly on csjones

## What's in flight (NOT done)

- **PR #241 needs review/merge** (`feature/csj/cms-insights-deploy-note → dev`) — opened but not yet merged. CSJ is sole codeowner; can self-approve or merge with `--admin`.
- **`dev → main` release PR** still pending. `origin/dev` is **44 commits ahead of `origin/main`** including the news/RSS/lifecycle bundle from PR #238 + the entire CMSFix work + deploy notes. Production deploy from `main` will need:
  - 3 new migrations (per CSJTODO session 74)
  - The `SanitizeInput` middleware change from this session
  - The same 9 PHP files + `public/build/` from `./deploy/fynla-org/build.sh` (NOT the csjones script)
  - Verify `AgentInternalController.php` + `AgentTokenAuth.php` exist on `fynla.org` — same deploy gap may exist there if production was deployed from a similarly-sparse list
- **Verify `/admin/insights` still works** — carry-over from session 73, never browser-verified after the CMSFix bundle landed. Should be a quick check next session.
- **Drive malicious-fixture path on dev** — `sample-with-malicious-html.docx` should publish with `<script>` and event handlers stripped; only Pest feature tests cover this currently.

## Deploy status

- **dev (csjones.co/fynla):** GREEN. CMS-into-/insights refactor live, end-to-end verified at `https://csjones.co/fynla/insights/rich-sample-title` (top nav + footer + structured body content + appears in `/insights` hub).
- **production (fynla.org):** STALE. `dev → main` release PR not yet opened. See `May/May4Updates/deployInsightsCMSIntegration.md` "Outstanding for production deploy" section for the full prep checklist.

## Tech debt found this session

From `tech-debt-report.md` — 0 critical, 2 warnings, 3 suggestions:

- **W1** — `InsightArticlePage.vue:81` uses `v-html` for `body_html`; XSS-safe only because every write path runs HTMLPurifier. Future writers (seeders, raw SQL, an admin route that skips the form request) become XSS vectors. Belt-and-braces option: model mutator on `DocumentArticle::setHtmlBodyAttribute` re-running `HTMLBodySanitiser`.
- **W2** — `SanitizeInput.php` exempts `html` and `html_body` from strip_tags. Documented in the file's block comment, but globally scoped — any future endpoint with these field names bypasses middleware-level sanitisation. Long-term, prefer route-prefix scoping.
- **S1** — `DocumentArticleAsInsight*Resource` naming inconsistent with module-resource pattern. Self-documenting, defer.
- **S2** — `InsightController::index()` hand-rolls `{data, meta}` shape. Could use Laravel collection's `additional()`.
- **S3** — `InsightSeoService::metaTagsForDocument` + `jsonLdForDocument` mirror native versions (~50 LOC duplication). Refactor to a shared `ArticleSeoSubject` interface if a third source appears.

## Known issues / blockers

- **csjones.co/fynla has uncommitted server-side WIP** — 61+ files in `app/` exist on the server but not in `origin/dev` (eval / tax-strategy work: `app/ValueObjects/CaptureContext.php`, `app/Listeners/Eval/EvalTraceListener.php`, `app/Console/Commands/Eval*.php`, `app/Http/Controllers/Api/{TaxStrategy,EvalAuth}Controller.php`, etc.). Future deploys to csjones MUST run rsync without `--delete` and ASK before bulk-syncing. See `May/May4Updates/deployInsightsCMSIntegration.md` "What was NOT touched (deliberate)" for the full list.
- **Pre-existing 403s** on `/fynla/storage/insights/bespoke/*.jpg` (8 hero images for native bespoke insights — not deployed to dev's storage). Visible as console errors on `/fynla/insights` hub. Out of scope for the CMS work.
- **Tiptap v3 is ESM-named-imports-only** — flagged as a memory candidate in session 74's CSJTODO, still un-saved. If it bites again, save.

## Rules reinforced this session

- `feedback_dev_server_is_separate.md` proved correct — csjones server runs uncommitted WIP that's not in `origin/dev`. Future deploys must check before `--delete` rsync. No memory update needed; the rule was right.
- `feedback_loop_until_correct.md` invoked — CSJ pointed at the broken `/articles/{slug}` page, said "make this work", and the loop diagnose → fix → re-verify → re-fix (SanitizeInput mid-loop) was the right approach. Two distinct defects (routing/layout + middleware-stripping HTML) found and fixed in one continuous loop without giving up.

## Next session should

1. **First**: `git checkout dev && git pull` then `php artisan db:seed` (Pest test runs always wipe local data — the chris user vanishes mid-session).
2. **Decide on PR #241** — merge into `dev` (admin override, since CSJ is sole codeowner) or close if you want to keep deploy notes out of `dev`. Quick.
3. **Open `dev → main` release PR** if production should ship next. Read `May/May4Updates/deployInsightsCMSIntegration.md` "Outstanding for production deploy" first — there's a non-trivial prep list (3 migrations, build script choice, AgentInternalController gap on `fynla.org`).
4. **Verify `/admin/insights` still works** — carry-over from session 73 + 74. Browser-test it.
5. If touching the import pipeline again, consider implementing **W1** (model mutator re-running `HTMLBodySanitiser` on `html_body` assignment) — small change, defence-in-depth.

## Context hints

- **Active branch type:** mixed (deploy doc on `feature/csj/cms-insights-deploy-note`, but `dev` has the substantive CMS work)
- **Behind origin/main by:** 0 commits (current branch is 44 ahead of `origin/main` per `git rev-list --left-right --count`)
- **Uncommitted:** none (working tree clean — only untracked scratch dirs `campaigns/`, `fyn/`, `personas/`, `prompts/`, `tools/` and the unrelated `May/May1Updates/deployFynFix.md`, all flagged but deliberately not committed)
- **Last commit:** `e1402ab` — `docs(deploy): record session 75 csjones deploy of CMS-into-/insights`
- **PRs open:** [#241](https://github.com/Stoff73/fynla/pull/241) (`feature/csj/cms-insights-deploy-note → dev`)
