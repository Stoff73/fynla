# Tech Debt Report — Session 2026-04-28 (news-subscribe-fix)

**Files analysed:** 28 (since `fa6d6c6..HEAD`)
**Issues found:** 9
**Severity breakdown:** 0 critical, 4 warnings, 5 suggestions

Read-only audit. Per-group findings already addressed (namespace move, composite index, queueing, hex `#f5f0eb`/`#e74c6f` swaps, favicon, rate-limit-before-validate) are intentionally excluded — only newly-surfaced cross-cutting issues are listed below.

## Critical Issues

None — no security vulnerabilities, broken contracts, or convention violations that would block a merge.

## Warnings

### 1. Hardcoded hex in newsletter post-action views (CSS Governance, Rule #12)
- **Files:** `resources/views/newsletter/confirmed.blade.php:9-13`, `resources/views/newsletter/unsubscribed.blade.php:9-13`
- **Category:** Convention violation
- **What's wrong:** Each view has 5 hardcoded hex literals (`#F7F6F4`, `#1F2A44`, `#ffffff`, `#555`, `#E83E6D`) inside a `<style>` block. CLAUDE.md Rule #12 (CSS Governance) bans hardcoded hex in style blocks: "use Tailwind `@apply` directives… For dynamic chart colours, import from `designSystem.js`." The neutral grey `#555` also has no palette equivalent — closest legitimate token is `neutral-500` (`#5F6B85`).
- **Suggested fix:** Either (a) convert to Tailwind utility classes on the elements (these are simple post-action landing pages, no `<style>` block needed), or (b) at minimum replace `#555` with the `#5F6B85` palette value since `#555` is off-palette.

### 2. Admin CSV export not wrapped in `throttle:export`
- **File:** `app/Http/Controllers/Api/Admin/NewsSubscriberController.php:65-92` (route at `routes/api.php:1135`)
- **Category:** Convention violation / mild security
- **What's wrong:** The export route has no throttle. The existing convention for export endpoints (`routes/api.php:140` GDPR export, plus `app/Http/CLAUDE.md` "`throttle:export` (3/hour) on exports") uses a dedicated throttle. The export streams every subscriber row including IP addresses — abuse-worthy if an admin token leaks.
- **Suggested fix:** Append `->middleware('throttle:export')` to the export route, e.g. `Route::get('news-subscribers/export', [...])->middleware('throttle:export');`.

### 3. Hardcoded `#f9a8c0` accent in newsletter email blades
- **Files:** `resources/views/emails/newsletter/confirm-subscription.blade.php:12`, `welcome.blade.php:12`
- **Category:** Convention violation (Rule #12)
- **What's wrong:** Both newsletter mail blades use inline `style="color:#f9a8c0;"` for the heading accent span. `#f9a8c0` is not in the v1.2.0 palette — `light-pink-100` is `#FDE8EE`, `raspberry-200`/`-300` are different again. Inline hex in template strings can't be enforced by the design system.
- **Suggested fix:** Replace with the canonical token used elsewhere in the email layout, or — if a soft-pink accent is genuinely needed — add a documented constant in `emails/layouts/master.blade.php`. Borderline acceptable due to email-client constraints, but flag-worthy.

### 4. Admin Panel sidebar has no entry pointing to `/admin/news-subscribers`
- **File:** `resources/js/views/Admin/AdminPanel.vue:127-169` (sidebarItems definition)
- **Category:** Inconsistency — discoverability
- **What's wrong:** `resources/js/router/index.js:1107-1112` registers the route, but the AdminPanel sidebar (which uses `path:` to router-push, like the Insights CMS entry on line 168) has no item for News subscribers. Admins can only reach the page by typing the URL.
- **Suggested fix:** Add `{ id: 'news-subscribers', label: 'News subscribers', shortLabel: 'News', path: '/admin/news-subscribers' }` to `sidebarItems` near the Insights CMS entry.

## Suggestions

### 5. Rate limiter increments before validation
- **File:** `app/Http/Controllers/Api/Public/NewsSubscriberController.php:26-40`
- **Category:** Security / UX (low confidence)
- **What's wrong:** Per-group review noted "rate-limit-after-validate (fixed → moved before)". The current order does check `tooManyAttempts` first (correct), but `RateLimiter::hit()` runs *before* `$request->validate()`. A user who mistypes their email burns one of their 3 attempts. Industry convention is to validate first so 422s don't count toward the throttle. Note: the route also has `throttle:5,1` from `routes/api.php:171`, providing a coarser outer limit.
- **Suggested fix:** Validate first, then `tooManyAttempts` + `hit`. Low priority — current behaviour is defensible (attacker can't bypass throttle by sending invalid emails) but mildly user-hostile.

### 6. User-enumeration via `already_registered` response
- **File:** `app/Http/Controllers/Api/Public/NewsSubscriberController.php:44-50`
- **Category:** Security (low confidence, low severity)
- **What's wrong:** The endpoint returns `status: already_registered` if the email matches a Fynla user record. This is a soft user-enumeration oracle: an unauthenticated attacker can probe whether any given email has a Fynla account. The 3-attempts-per-IP rate limit blunts but doesn't eliminate enumeration (botnet/proxy rotation defeats it). Fynla's auth endpoints (`/api/auth/login`) deliberately return generic responses for the same reason.
- **Suggested fix:** Accept the trade-off (current UX is helpful: tells users to sign in instead of subscribing twice) but document the decision in a code comment, OR collapse `already_registered` and `pending_confirmation` into a single response that always says "check your inbox" and skips the email send for existing users. Flagging for awareness.

### 7. `export()` ignores active filters from the index page
- **File:** `app/Http/Controllers/Api/Admin/NewsSubscriberController.php:65-92`
- **Category:** Inconsistency / minor UX
- **What's wrong:** The admin `index` endpoint supports `status` and `search` filters. The export endpoint exports every subscriber, ignoring whatever filters the admin currently has applied in the UI. Admins clicking "Export CSV" while filtered to "pending" expect to get only pending rows.
- **Suggested fix:** Accept the same `status` and `search` query params in `export()` and apply them to the `chunk()` query (mirror the index logic, or extract a shared private `applyFilters(Builder $query, array $validated)` method).

### 8. Admin filters not persisted in URL
- **File:** `resources/js/views/Admin/NewsSubscribersPage.vue:131-135, 142-145`
- **Category:** Minor UX
- **What's wrong:** Page state (`status`, `search`, `page`) lives only in component data — refreshing the page or sharing the URL resets all filters. Not a functional bug.
- **Suggested fix:** Optional. If admin filter persistence is desired, sync `status`/`search`/`page` through `$route.query` with `router.replace`. Otherwise leave as-is — admin tooling rarely needs URL state.

### 9. Migration silently no-ops if table exists
- **File:** `database/migrations/2026_04_28_120000_create_news_subscribers_table.php:13-15`
- **Category:** Defensive coding (low confidence)
- **What's wrong:** The `if (Schema::hasTable('news_subscribers')) { return; }` guard matches `database/CLAUDE.md` safety-checks pattern, but it silently no-ops if a partially-created table exists from a manual SQL run — meaning the composite index on `[unsubscribed_at, confirmed_at]` could be missing without the migrator noticing.
- **Suggested fix:** Acceptable as-is per the documented pattern. Optional one-line comment: `// Idempotency guard — assumes the column set is unchanged from the initial create.` Flagging only because re-running migrations against a partially-created table won't add the index.

---

## Cross-cutting checks (all pass)

- **Status string contract:** `NewsSubscribeBanner.vue` consumes the exact strings the backend returns (`pending_confirmation`, `already_registered`, `already_confirmed`, plus client-only `idle`/`error`; `rate_limited` returns 429 and is handled in the catch block).
- **Namespace consistency:** All `NewsSubscriber` references resolve to `App\Models\News\NewsSubscriber`. No leftover `App\Models\NewsSubscriber` references in app/, database/, or tests/.
- **Strict types:** All new PHP files declare `strict_types=1`.
- **URL building:** Mailables use `url('/...')` consistently — no mix of `route()` or hardcoded URLs.
- **Queueing:** `Mail::to(...)->queue(...)` used in both controllers — no synchronous `send()`.
- **Banned colours:** None of `amber-*`, `orange-*`, `gray-[0-9]`, `primary-*`, `secondary-*` in the new Vue files.
- **Debug code:** No `TODO`/`FIXME`/`HACK`/`console.log`/`dd()`/`var_dump()` left in production code.
- **Vue conventions:** Multi-word component names (`NewsSubscribeBanner`, `NewsSubscribersPage`); `:key` present on every `v-for`; no `v-if` + `v-for` on the same element.
- **Auth defense in depth:** Admin controller has both route-level (`routes/api.php:1133`) and constructor-level (`NewsSubscriberController.php:16`) `permission:admin.access` — matches the `InsightArticleController` convention.
- **Test coverage:** Public endpoint (8 tests: happy path, dupe, unsubscribe revival, rate limit, validation, case-normalisation, already-confirmed, already-registered), admin endpoint (5 tests: authz × 3, list, filter, search, CSV), action controller (6 tests: confirm/unsubscribe both paths plus idempotency × 2 plus 404 × 2), mail render (3 tests: links + from-address). Proportional and includes idempotency checks.
- **Pest.php opt-in:** `tests/Pest.php:35-36` correctly registers `Tests\TestCase` (no `RefreshDatabase`) for `Unit/Mail` since those tests use `factory()->make()`.

---
*Generated by tech-debt-session skill, 2026-04-28*
