# Findings — seeded 2026-05-19 (SP3 mobile-iframe scaffold)

## Resolved gotchas
- **Auth is Bearer-token, not session-cookie.** `/api/auth/login` returns `requires_verification` at TOP LEVEL with `data:{challenge_token,email}` nested; `/api/auth/verify-code` → `data.access_token`. The original spec/plan wrongly described cookie auth and nested `requires_verification` — corrected in plan commit `664c9c6b` (caught in Task 5 code review; would have broken login entirely).
- **`SecurityHeaders` sets `X-Frame-Options: DENY` globally** with no `frame-ancestors`. Task 3 scopes `SAMEORIGIN` + `frame-ancestors 'self'` to `/m` and `/m/app*` only — the spec's confirmed HIGH risk, real.
- **Legacy `resources/js/mobile/` was NOT isolated** (plan assumed it was). Real cross-refs: `app.js` (native bootstrap import), `AppLayout.vue` (`OfflineBanner` on every authed web page), `auth.js:143` (mobileDashboard/clearCache dispatch), `api.js`/`preview.js` (native-guarded `/m/*` pushes). Resolved via CSJ-approved Task 8 scope expansion + Task 8b.
- **`m_full_site` cookie must be in `EncryptCookies::$except`** to be readable as plaintext for the pin check; tests use `assertPlainCookie` not `assertCookie`.

## Open / to verify next session
- **Pest baseline: 60 failed vs documented ~15.** Task 8b argued this is pre-existing DB-contamination/test-ordering (8b changed only 2 frontend JS files never loaded by Pest; isolated run of a "failing" class passed). Logically sound but NOT independently re-verified — stash/isolation-check before Task 9. Task 8's 15-failure set WAS proven pre-existing via stash-compare (root cause `app.ai_audit_hmac_key` not configured, `AuditChainService.php:53` — local env gap).

## Process note
- Subagent-driven-development: controller must NOT commit on the branch while a background implementer runs (`git add -A` in its commit step). One git-race occurred (plan-doc commit collided with Task 5 amend) — recovered cleanly; thereafter controller doc-commits held to between-task gaps.
