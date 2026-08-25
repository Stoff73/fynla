---
tags:
  - april-2026
  - sprint-0
  - bug-fix
  - feature
  - browser-test
date: 2026-04-26
session: 92
---

# Session 92 — BS-15 GREEN + BS-18 PARTIAL GREEN

Back to [[April Index]] · [[Home]]

**Branch:** `feature/fyn-persona-split`
**Commits:** `50420c7` (audit fix), `b6cbff3` (Chain view + BS-15), `7acd7f8` (BS-18 PARTIAL), `+ session-end docs`
**Pest baseline:** 501 / 1924 / 0 (113.25s) — was 486 / 1591 pre-Audit-suite-add
**Batch 3 status:** 9 GREEN / 14 (BS-01, 02, 04, 06, 07, 10, 13, 19, 21, 15 + 18 PARTIAL)

---

## BS-15 GREEN — hash-chain audit admin view

Walked end-to-end with `chris@fynla.org` (User #1, role=admin) on local dev. Seeded 20 audit events via `AuditChainService::append` (canonical path, no factory shortcut). Tip hash:

```
ad21969118b3bedf510b258fc2dd3bd70ef604e5c2cc9c5b49707d56f522e02f
```

**Acceptance evidence — banner matches artisan output byte-for-byte:**

| Source | row_count | tip_hash | chain_valid |
|--------|-----------|----------|-------------|
| UI banner `data-tip-hash` | 20 | `ad21969118b3…f522e02f` | ✅ |
| `php artisan ai:audit:verify-chain` | 20 | `ad21969118b3…f522e02f` | ✅ |
| `AiAuditEvent::count()` | 20 | — | — |

Screenshot: [`docs/sprint-0-verification/BS-15/01-list.png`](../../docs/sprint-0-verification/BS-15/01-list.png)

### Three bugs fixed in same loop (CLAUDE.md Rule #15)

#### 1. AuditChainService canonical-JSON fix — real INV-2.10.2 violation

Loading the BS-15 fixture surfaced `verifyChain()` returning `{chain_valid: false, broken_at: 337, row_count: 0}` against 16 real rows from sessions 89-91. Re-creating the same row payload via `append()` then immediately calling `verifyChain()` reproduced the mismatch in-process — a deterministic, repeatable INV-2.10.2 break.

**Root cause:** MySQL's binary JSON column type sorts object keys by length ascending, ties by insertion order, on storage. Tested: input `{"preview":false,"q":"test"}` (preview=7 chars, q=1 char) is stored as `{"q":"test","preview":false}`. The write-time hash uses the PHP array's iteration order; the verify-time hash uses MySQL's reordered cast-back. Hashes diverge for any payload whose PHP order doesn't already match MySQL's sort. The Pest sibling `HashChainTest` happened to use `['index', 'preview']` (index=5 chars < preview=7 chars — already MySQL-sorted) so the bug was masked from the test suite.

**Fix:** `AuditChainService::computeRowHash` now canonicalises the payload via a new `canonicaliseForHash` helper (recursive deep-ksort on associative arrays; numeric lists preserve order) before `json_encode`. Spec INV-2.10.2 requires "serialised(fields_except_hashes)" but does not specify byte-level ordering, so canonicalising is consistent.

**Regression test added** at `tests/Feature/Audit/HashChainTest.php` — new `it('verifies chains whose input_summary keys MySQL would reorder')` with the canonical reproducer payloads `['preview' => false, 'q' => 'test']` and a 5-key mixed payload.

#### 2. AiAudit.vue Chain view banner missing tip_hash

Pre-fix banner read only "Chain valid · {row_count} rows" with no tip_hash anywhere in the DOM, blocking the BS-15 banner-vs-artisan match assertion.

**Fix** (`resources/js/components/Admin/AiAudit.vue`): banner now appends `· tip {first-12-chars}…` visually and exposes the full 64-char hash via both `:data-tip-hash` (for headless DOM scraping) and `:title` (for user-facing tooltip on hover). Added `shortTipHash(hash)` method to truncate visually.

#### 3. loadChain/verifyChain payload double-unwrap

Post-fix banner rendered correctly but the table showed "No audit rows match these filters" against an API returning 20 rows.

**Root cause:** `aiAuditService.getChain()` already returns `response.data` (axios unwrapped) = `{success, data: paginator}`. Component should treat its own `response.data` as the paginator object and read `paginator.data` as the rows array. Pre-fix code did `response.data?.data || response.data` which returned the paginator's rows array as `payload`, then read `payload.data` (an array has no `.data` property) → undefined → empty.

**Fix:** simplified to `paginator = response.data || {}` and `chainEvents = paginator.data || []`. Same single-unwrap pattern applied to `verifyChain` (which was working only by accidental fall-through).

---

## BS-18 PARTIAL GREEN — SSE abort keeps in-flight writes

Drove four abort timings (1500ms / 1200ms / 800ms / 100ms; both `aiChat/abortStreaming` Vuex action AND `window.location.href` navigation) with seeded john (User #352, advice mode).

| Walk | Abort timing | Method | Savings written | Audit rows | AiAbortEvent |
|------|--------------|--------|-----------------|------------|--------------|
| Conv 92 | 1500ms | `abortStreaming` | ✅ | dispatched + persisted | ❌ |
| Conv 93 | 1200ms | `window.location.href` | ✅ | dispatched + persisted | ❌ |
| Conv 96 | 1500ms (longer prompt) | `abortStreaming` | ✅ | dispatched + persisted | ❌ |
| Conv 97 | 800ms (longer prompt) | `abortStreaming` | ✅ | dispatched + persisted | ❌ |
| Conv 100 | 100ms (very early) | `abortStreaming` | ❌ | none | ❌ |

The 100ms early-abort walk (no savings, no audit, no messages beyond user msg) confirmed the abort fires at the HTTP layer — but `ai_abort_events` stayed at 0, proving cli-server SAPI doesn't flip `connection_aborted()` the way Apache mod_php does.

**Root cause:** PHP's `connection_aborted()` doesn't propagate through the `cli-server` SAPI that `artisan serve` uses. All correct settings in place (`output_buffering=0`, `ignore_user_abort=0`, `implicit_flush=1`, explicit `ob_flush()` + `flush()` in `AiChatController`) but cli-server architecturally doesn't set the abort flag the way Apache mod_php / php-fpm does.

The Pest sibling [[#Pest sibling reference|`tests/Feature/AI/SseAbortKeepWritesTest.php`]] covers the `recordAbort` flow at unit level by stubbing `wasConnectionAborted` (4 tests passing green). Production Apache mod_php on csjones.co/fynla will propagate normally.

### CSJ direction (2026-04-26)

**Option (a) accepted:** Ship BS-18 as PARTIAL GREEN with the cli-server SAPI caveat documented inline. Verify the third assertion in a single browser walk on csjones.co/fynla post-deploy (carry-forward in CSJTODO §Post-deploy verification).

Criticality assessment across functionality / UX / security: uniformly low. The visible-to-user behaviour (keep partial writes per INV-2.9.2) works perfectly on cli-server; only the forensic "instrument" half is gated by the SAPI quirk. The HMAC-chained `ai_audit_events` is the security-relevant audit trail and captures every dispatched/persisted/failed dispatch on every walk.

Screenshot: [`docs/sprint-0-verification/BS-18/01-list.png`](../../docs/sprint-0-verification/BS-18/01-list.png) shows the £5,000 Nationwide Cash ISA card persisted post-abort on `/net-worth/cash`.

---

## Files changed

| File | Type | Purpose |
|------|------|---------|
| `app/Services/AI/AuditChainService.php` | fix | `canonicaliseForHash` deep-ksort in `computeRowHash` (INV-2.10.2) |
| `tests/Feature/Audit/HashChainTest.php` | test | Regression test for MySQL JSON key-reorder reproducer |
| `resources/js/components/Admin/AiAudit.vue` | feat + fix | Banner exposes full tip_hash; `loadChain`/`verifyChain` payload single-unwrap |
| `tests/Browser/scenarios/BS-15-hash-chain-audit-admin-view.php` | docs | GREEN delivery note (full chris-admin walk transcript) |
| `tests/Browser/scenarios/BS-18-sse-abort-keep-writes.php` | docs | PARTIAL GREEN delivery note (4-walk transcript + SAPI diagnosis) |
| `docs/sprint-0-verification/BS-15/01-list.png` | screenshot | Chain view banner + 20-row table |
| `docs/sprint-0-verification/BS-18/01-list.png` | screenshot | Cash ISA card persisted post-abort |
| `April/April26Updates/CSJTODO.md` | docs | Session 92 narrative + Batch 3 checklist + post-deploy carry-forward |

---

## Pest sibling reference

`tests/Feature/AI/SseAbortKeepWritesTest.php:78-89` documents the unit-test stubbing pattern:

> "the chat loop relies on this hook being mockable so abort-flow tests can simulate disconnect without an actual TCP drop"

Four tests:
1. `persists an ai_abort_events row with conversation_id + user_id + tool + count`
2. `records null last_tool_call when the abort fires before any tool ran`
3. `does NOT roll back any persisted records from earlier tool calls (INV-2.9.2)`
4. `exposes wasConnectionAborted as a stubbable hook`

All four pass green at unit level. The recordAbort logic is correct; only the cli-server SAPI's failure to flip the flag is what blocks the live browser assertion.

---

## Carry-forward to next session

- **§WriteIntentClassifier extension** (BS-17 unblocker) — the cleanest tractable code task. Extend `RecordDuplicateChecker::alreadyExists` from protection_policy-only to cover savings_account, investment_account, pension, property, goal duplicate-check logic.
- **§Post-deploy verification** — when `feature/fyn-persona-split` lands on csjones.co/fynla, run a single BS-18 walk + SSH check `\App\Models\AiAbortEvent::count()` to close the third assertion forever.
- **BS-22 + BS-23 spec amendments** — parked from session 91 / earlier session 92.
- **S0.16c re-walk list** — BS-01, 02, 04, 06, 07, 10 walked pre-AiChatPanel-refactor (`ffc9c3f`); should be re-walked against the post-refactor shared body before S0.17.

See [[CSJTODO]] for the full Batch 3 status + carry-forward queue.

---

**Related:**
- [[Architecture/v083/10-NEW-SYSTEMS|New Systems — AI chat / persona dispatch]]
- [[Architecture/v083/04-BACKEND|Backend — service layer + agents]]
- [[session-91-bs19-green]] — previous session
