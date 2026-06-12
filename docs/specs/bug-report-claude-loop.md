# Spec: Mobile Bug Report → GitHub Issue → Autonomous Claude Fix Loop

**Status:** DRAFT — awaiting CSJ approval
**Branch:** `claude/bug-report-github-loop-7x3h1n`
**Author:** Claude (spec), CSJ (owner)
**Date:** 2026-06-09

---

## 1. Goal

A user reports a bug from the mobile (`/m`) app. The report:

1. Is captured with **auto-diagnostics** (route, app version, device, OS) + **recent console/error logs** + the user's **description, category and severity**.
2. Is written to the GitHub repo `Stoff73/fynla` as a **GitHub Issue** prefixed with **`@claude`**.
3. Triggers Anthropic's **native Claude Code GitHub Action** (event-driven, runs in GitHub Actions — no polling, no `/loop` session needed).
4. The Action runs a **well-defined skill chain** (systematic-debugging → fix → test → browser-verify) and resolves the bug end-to-end.

> **`/loop` note:** The user originally asked for a `/loop`. Per the clarification round we chose the **native `@claude` GitHub Action** instead, which is event-driven and strictly better than a self-hosted polling loop (no idle cost, no always-on machine, official support). The `/loop` mechanism is therefore **not** part of this spec. If a backstop sweep is ever wanted, it can be added later as a scheduled `workflow_dispatch`.

---

## 2. Decisions locked in (clarification round)

| Question | Decision |
|----------|----------|
| Trigger | **Native `@claude` GitHub Action** (event-driven) |
| Autonomy | **8b — true zero-touch:** auto-merge `claude-auto` bug PRs to `dev`, bypassing CODEOWNERS via a scoped bot token / protection exception (see §8) |
| Capture | Auto-diagnostics + console/error logs + description/category/severity (**no screenshot** — no Capacitor plugin installed) |
| Placement | **Persistent floating button** on every mobile screen |
| Email | **Keep both** — email `chris@fynla.org` AND create the issue (email is the fallback if issue creation fails) |
| Preview users | **Allowed** to file bugs → must add `/bug-report` to `PreviewWriteInterceptor::EXCLUDED_ROUTES` (CLAUDE.md Rule #8) |
| Web parity | **Yes** — web `BugReportModal` also gets category/severity and creates issues |

---

## 3. Current state (what already exists — reuse, don't rebuild)

| Piece | File | Reuse plan |
|-------|------|-----------|
| Web bug-report modal | `resources/js/components/BugReportModal.vue` | Reference only — mobile gets its own sheet |
| Web bug-report service | `resources/js/services/bugReportService.js` | Reference only |
| Console capture ring buffer (100 entries, errors + rejections) | `resources/js/services/consoleCapture.js` | **Import directly** into mobile bundle (framework-agnostic module) |
| Backend controller | `app/Http/Controllers/Api/BugReportController.php` → `POST /api/bug-report` | **Extend** to create a GitHub issue (currently emails only) |
| Mail | `app/Mail/BugReportMail.php` | Keep — email stays as a fallback/notification |
| Rate limit | `throttle:bug-reports` on the route | Reuse |
| Mobile SPA shell | `resources/mobile/App.vue` (sits outside `<router-view>`) | **Add floating button + sheet here** |
| Mobile API client | `resources/mobile/api.js` (`apiPost` w/ Bearer token) | Reuse |
| Mobile auth token | `resources/mobile/store.js` (`store.token`) | Reuse |

**Not present (build new):** any GitHub API integration in the backend; `.github/workflows/claude.yml`; issue template; labels; mobile bug-report surface.

> **Mobile tree clarification:** the live `/m` app is `resources/mobile/` (served by `routes/web.php:643` → `mobile-host` view; inner SPA at `/m/app`). The `resources/js/mobile/` path referenced in `resources/js/CLAUDE.md` is **empty/stale** — ignore it.

---

## 4. Architecture / data flow

```
Mobile user taps floating "Report a problem" button (resources/mobile/App.vue)
   │
   ▼
BugReportSheet.vue  (description + category + severity; diagnostics gathered silently)
   │  apiPost('/api/bug-report', payload, store.token)
   ▼
BugReportController@store  (Laravel)
   ├─ validate + sanitize (existing)
   ├─ Mail::queue(BugReportMail)               ← existing, kept
   └─ GithubIssueService::createBugIssue(...)  ← NEW
          │  Guzzle POST https://api.github.com/repos/Stoff73/fynla/issues
          │  title:  "@claude [bug][<severity>] <short>"
          │  body:   structured markdown (see §6)
          │  labels: ["bug","from-mobile","claude-auto"]
          ▼
   GitHub Issue created
          │  (issue.opened / issue labelled "claude-auto")
          ▼
.github/workflows/claude.yml  (Claude Code GitHub Action)
          │  runs skill chain (§7): systematic-debugging → fix → pest → playwright
          ▼
   Opens PR → dev, posts root-cause comment on the issue, (auto-merge — see §8)
```

---

## 5. Component breakdown & files to touch

### A. Mobile UI — `resources/mobile/`
- **`App.vue`** — add a persistent floating button (bottom-right, respects safe-area insets) + the sheet. Button hidden on `/login` and `/verify` (no auth token yet).
- **`views/BugReportSheet.vue`** (NEW) — bottom-sheet modal:
  - `description` (textarea, required, max 5000)
  - `category` (select: Protection, Savings, Investment, Retirement, Estate, Goals, Coordination, General)
  - `severity` (select: Low, Medium, High, Critical)
  - Submit → gather diagnostics → `apiPost`. Success/error toast. Respects Rule #16 (no decorative icons; the button is functional — it is the sole control, label "Report a problem").
- **`diagnostics.js`** (NEW, small) — gathers `{ route, app_version, device_model, os_version, platform }` via `@capacitor/device` (`Device.getInfo()`), current `router.currentRoute`, and `import.meta.env.VITE_APP_VERSION` (or a constant). Falls back gracefully on web.
- **Console capture wiring** — import `resources/js/services/consoleCapture.js` into `resources/mobile/main.js` and call `consoleCapture.startCapture()` at boot, so `getLogs()` has content at report time.

### B. Backend — extend bug report
- **`app/Services/Integrations/GithubIssueService.php`** (NEW) — Guzzle client:
  - `createBugIssue(array $data): ?array` returns `['number'=>..,'html_url'=>..]` or `null` on failure.
  - Reads token + repo from config (§9). **Never throws into the request path** — failure is logged and the email path still succeeds.
- **`BugReportController@store`** — accept new optional fields (`category`, `severity`, `app_version`, `device_model`, `os_version`, `platform`, `route`); after queueing mail, call `GithubIssueService::createBugIssue()`; include `issue_url` in the JSON response when created.
- **`config/services.php`** — add `github` block (`token`, `repo`, `default_labels`, `enabled`).
- **`.env` templates** (`deploy/*/.env.production`) — document `GITHUB_BUG_ISSUE_TOKEN`, `GITHUB_BUG_ISSUE_REPO=Stoff73/fynla`, `GITHUB_BUG_ISSUE_ENABLED`. **Real token lives only on the server `.env`, never in the repo.**
- **`bugReportService.js` (web)** — optionally pass the new fields too (web has no device/category today; can default). Out of scope unless you want web parity — flag.

### C. GitHub config — `.github/`
- **`workflows/claude.yml`** (NEW) — Claude Code Action, triggered on `issues: [opened, labeled]` filtered to the `claude-auto` label (and/or `@claude` in the body). Needs repo secret `CLAUDE_CODE_OAUTH_TOKEN` (Max-subscription OAuth token from `claude setup-token`). Permissions: `contents: write`, `pull-requests: write`, `issues: write`.
- **`ISSUE_TEMPLATE/bug_from_mobile.md`** (NEW, optional) — documents the structured format for humans filing manually.
- **Labels** (created once via API/UI): `bug`, `from-mobile`, `claude-auto`.

### D. Skills the Action invokes — §7

---

## 6. Issue body contract (what Claude receives)

```markdown
@claude please investigate, fix, test and verify this bug.

**Severity:** High
**Category:** Retirement
**Reported route:** /m/app/module/retirement

### What went wrong (user)
<description>

### Diagnostics
- App version: 1.0.3 (build 142)
- Platform: ios
- Device: iPhone15,2
- OS: iOS 18.4
- User: #1234 (preview: false)
- Client time: 2026-06-09T10:12:33Z

### Recent console / error logs
```
<last N entries from consoleCapture.getLogs(), capped>
```
```

Title format: `@claude [bug][High] <first ~80 chars of description>`

---

## 7. Skill chain for the autonomous fix (well-defined)

The Action's prompt (in `claude.yml`) instructs the run to, in order:

1. **`systematic-debugging`** — four-phase root-cause investigation with file:line evidence. No jumping to fixes.
2. Implement the root-cause fix (scope discipline — only what the bug needs).
3. **`./vendor/bin/pest`** — run the relevant test suite; add a regression test for the bug.
4. **Browser verification** — Playwright per the CLAUDE.md "Browser Testing Rules": actually interact, don't just snapshot.
5. **`tech-debt-session`** — audit the changed files.
6. Open a PR → `dev`, post a root-cause + fix summary comment on the issue, link the PR.

The prompt explicitly bans: completion reports before tests pass, partial-success claims, and decorative icons / emoji (Rule #16) in any code or message.

---

## 8. Merge policy — DECIDED: 8b (zero-touch) + manual GitHub setup required

**Chosen:** the Action fixes, tests, opens a PR→`dev` labelled `claude-auto`, and **auto-merges without human review**, bypassing CODEOWNERS **only** for that label.

This overrides the documented rule (`CODEOWNERS: * @Stoff73`; `CLAUDE.md`: "only @Stoff73 can merge `dev`"). Recorded here as a deliberate, owner-approved exception. ⚠️ **Risk accepted:** unreviewed AI fixes land on `dev` (which mirrors `csjones.co/fynla` staging). Mitigations baked in: full Pest + Playwright must pass before merge; scope strictly limited to `claude-auto`-labelled, `from-mobile`-originating bug PRs; nothing reaches `main`/production without the normal `dev → main` release PR you control.

**Manual setup only CSJ can do (GitHub repo admin — I cannot do these from code):**

1. **Branch protection on `dev`:** add a bypass for the bot identity, OR enable "Allow specified actors to bypass required pull requests" scoped to the GitHub App / bot account the Action runs as. Without this, auto-merge will stall on CODEOWNERS.
2. **Bot token:** a GitHub App installation token (preferred) or a fine-grained PAT with `contents: write` + `pull-requests: write` that is in the CODEOWNERS bypass list. Stored as repo secret (e.g. `CLAUDE_BOT_TOKEN`). The default `GITHUB_TOKEN` **cannot** bypass CODEOWNERS.
3. **`CLAUDE_CODE_OAUTH_TOKEN`** repo secret for the Action — minted from the Claude Pro/Max subscription via `claude setup-token` (no separate Anthropic API key needed). Runs draw down the same Max rate-limit pool as interactive Claude Code usage, so `--max-turns` is kept low (10); the token expires periodically and is rotated by re-running `setup-token`. (Swap to `ANTHROPIC_API_KEY` + `anthropic_api_key:` in the workflow to bill against the metered API instead.)
4. **`GITHUB_BUG_ISSUE_TOKEN`** on each server `.env` (Issues: write on `Stoff73/fynla`).

The workflow (`claude.yml`) will be written to enable auto-merge, but it is inert until steps 1–2 are done. Until then, behaviour degrades safely to 8a (PR sits awaiting your approval).

---

## 9. Configuration

```php
// config/services.php
'github' => [
    'token'   => env('GITHUB_BUG_ISSUE_TOKEN'),
    'repo'    => env('GITHUB_BUG_ISSUE_REPO', 'Stoff73/fynla'),
    'enabled' => env('GITHUB_BUG_ISSUE_ENABLED', false),
    'labels'  => ['bug', 'from-mobile', 'claude-auto'],
],
```

- Fine-grained PAT or GitHub App token with **Issues: write** on `Stoff73/fynla` only.
- `CLAUDE_CODE_OAUTH_TOKEN` as a **repo secret** for the Action (from `claude setup-token`; uses the Max subscription, not a metered API key).
- Default `enabled=false` so nothing fires until the token is provisioned.

---

## 10. Security & abuse considerations

- **Untrusted content into an issue:** the description + console logs are attacker-controllable (any logged-in user). The Action will act on them autonomously → treat issue text as untrusted (the Action prompt must not follow instructions embedded in the bug body; only treat it as data to debug). Mitigation: prompt hardening + the body is clearly fenced as "report data".
- **Rate limiting:** reuse `throttle:bug-reports` (5/hr/user) so a user can't flood the repo with issues.
- **Preview users:** `PreviewWriteInterceptor` will block `POST /bug-report` for preview personas unless excluded. Decide whether preview users may file bugs (probably **no** — keep them blocked).
- **Sanitisation:** existing `strip_tags()` stays; GitHub markdown is rendered but issues are not executable.
- **Token scope:** least-privilege (Issues: write only). Never in repo.
- **PII:** console logs may contain user data — the repo is private; acceptable, but cap log size (existing 2048 char cap) and note it.

---

## 11. Implementation phases

1. **Phase 1 — Backend issue creation.** `GithubIssueService`, config, controller extension, validation. Unit-test the service (mock Guzzle). *No UI yet.*
2. **Phase 2 — Mobile UI.** Floating button + `BugReportSheet.vue` + `diagnostics.js` + console-capture wiring in `resources/mobile/`.
3. **Phase 3 — GitHub automation.** `claude.yml` workflow, issue template, labels, secrets doc. Resolve §8 first.
4. **Phase 4 — End-to-end test.** File a real bug from `/m` on staging → issue appears → Action runs → PR opens. Verify with Playwright per browser-test rules.

---

## 12. Open questions — RESOLVED

1. ~~§8 governance~~ → **8b** (zero-touch, scoped bypass). Needs manual GitHub admin setup (§8 steps 1–4).
2. ~~Web parity~~ → **Yes**, web modal gets category/severity + creates issues.
3. ~~Preview users~~ → **Allowed**; add `/bug-report` to `PreviewWriteInterceptor::EXCLUDED_ROUTES`.
4. ~~Email fallback~~ → **Keep both** (email + issue).
5. **App version source** — only remaining minor unknown. Plan: add `VITE_APP_VERSION` to `deploy/mobile/build-ios.sh` and the web build scripts; mobile `diagnostics.js` reads `import.meta.env.VITE_APP_VERSION` with a sensible fallback. (No CSJ decision needed — will implement this way unless told otherwise.)
```
