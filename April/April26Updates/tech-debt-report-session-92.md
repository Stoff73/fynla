---
tags:
  - april-2026
  - tech-debt
  - sprint-0
date: 2026-04-26
session: 92
---

# Tech Debt Report — Session 92

Back to [[session-92-bs15-green-bs18-partial]] · [[April Index]]

**Files changed in session:** 5 substantive (+ 2 screenshots, + 1 update note, + CSJTODO)
**Issues found:** 0 critical · 0 warnings · 0 advisories
**Status:** Clean

---

## Files audited

| File | Lines changed | Audit notes |
|------|---------------|-------------|
| `app/Services/AI/AuditChainService.php` | +37 / -1 | New `canonicaliseForHash` private static helper (recursive deep-ksort). Class-scoped, no duplication. Docblock updated to explain MySQL JSON quirk + canonicalisation rationale. |
| `tests/Feature/Audit/HashChainTest.php` | +41 | One new `it()` block matching project Pest convention. Uses `User::factory()->create()` and `AiAuditEvent::query()->delete()` to set up clean state. No mocking. |
| `resources/js/components/Admin/AiAudit.vue` | +20 / -3 | Banner template adds `:data-tip-hash` + `:title` attributes. New `shortTipHash(hash)` method (1 line). `loadChain` + `verifyChain` payload extraction simplified to single-unwrap. All design-system compliant — `bg-spring-100 text-spring-700` for valid banner, `bg-raspberry-100 text-raspberry-700` for broken state, no hardcoded hex, no banned colors, no icons added. |
| `tests/Browser/scenarios/BS-15-hash-chain-audit-admin-view.php` | docblock rewrite | Pure documentation — full session 92 GREEN delivery note. |
| `tests/Browser/scenarios/BS-18-sse-abort-keep-writes.php` | docblock rewrite | Pure documentation — PARTIAL GREEN delivery note + cli-server SAPI diagnosis + post-deploy verification step. |

---

## Convention compliance

- **Strict types declared** — `AuditChainService.php` has `declare(strict_types=1);` at top (unchanged).
- **PSR-12** — `canonicaliseForHash` uses 4-space indent, opening brace on same line for control structures, blank line between methods.
- **Type hints** — `private static function canonicaliseForHash(mixed $value): mixed` — fully typed.
- **No hardcoded tax values** — N/A (audit chain code, not tax-related).
- **No banned acronyms** — N/A (pure code, no user-facing strings).
- **Design system v1.2.0 compliance** — AiAudit.vue uses only Tailwind palette tokens (`spring-*`, `raspberry-*`, `light-gray`, `horizon-*`, `savannah-*`, `violet-*`, `neutral-*`). No hardcoded hex. No icons on Chain view (functional only — banned per Rule #14 on this surface).
- **Currency formatting** — N/A.
- **No `migrate:fresh`** — N/A.
- **No emojis in code/strings** — confirmed.
- **Multi-word Vue component name** — `AiAudit.vue` (unchanged, already compliant).
- **British spelling in user-facing text** — banner reads "Chain valid" / "Chain broken" (no American/British distinction in these specific strings).

---

## Duplication check

- **`canonicaliseForHash`** — checked for similar deep-ksort helpers elsewhere in `app/Services/`. None found. New code with no analogue, justified scope (private static, single call site).
- **`shortTipHash`** — single-method helper for visual truncation. No analogous helper elsewhere; could be hoisted to a utility if a second tip-hash display surface emerges, but for now scope-local is correct.
- **HashChainTest regression test** — uses the same `beforeEach` + `User::factory()->create()` pattern as the existing two HashChainTest cases. Consistent.

---

## Comment hygiene

- **AuditChainService class docblock** — extended with the MySQL JSON quirk explanation. The explanation is non-obvious (would surprise a reader: "why is there a deep-ksort step if the spec just says serialise?") and tied to a specific past incident, so the comment passes the "WHY is non-obvious" test in CLAUDE.md "Default to writing no comments".
- **`canonicaliseForHash` docblock** — single-paragraph explainer, scoped to the method's intent. Pass.
- **No new TODO/FIXME/HACK markers** added (verified via grep).
- **No reference to current task / fix in code comments** — checked. The docblocks reference INV-2.10.2 (the spec) and the canonicalisation rationale, not "session 92" or "BS-15".

---

## Test coverage

- **HashChainTest** baseline before session 92: `it('appends a chain of 100 events that verify clean')`, `it('starts the chain from the canonical zero hash')`, `it('returns row_count 0 and a zero tip for an empty table')`. 3 cases, 304 assertions.
- **HashChainTest** after session 92: + `it('verifies chains whose input_summary keys MySQL would reorder')` covering `['preview' => false, 'q' => 'test']` (the canonical reproducer) and a 5-key mixed payload. 4 cases, 306 assertions.
- **Regression discipline:** the new test would fail without the `canonicaliseForHash` fix — verified by mentally running through the bug pre-fix (the `['preview', 'q']` payload would store as `[q, preview]` in MySQL, the verify-time recompute would JSON-encode `[q, preview]`, hash mismatch, `chain_valid: false`).

---

## Architectural soundness

- **Spec compliance:** INV-2.10.2 specifies `row_hash = sha256(prev_hash || serialised(fields_except_hashes) || signed_at)`. The "serialised" predicate doesn't pin a specific byte ordering, so canonicalising via deep-ksort is consistent with the spec letter and its intent (deterministic re-derivation).
- **Backwards compatibility:** the canonical-JSON change invalidates ALL pre-fix audit rows. Local DB was empty pre-fix (broken chain wiped + reseeded via 20 fresh `append()` calls). csjones.co/fynla and fynla.org have NOT received Sprint 0 changes yet (per CSJTODO §Deploy Status), so no migration is needed — the first deploy will start a fresh chain with the canonical-JSON fix from row #1.
- **Two-Fyn architecture:** AiAudit.vue admin view is observer-only (read-only) and unchanged in this respect. No write-path changes touched the Two-Fyn boundary.

---

## Recommendations

None. Session 92's work is clean. The next session can pick up the WriteIntentClassifier extension (BS-17 unblocker) without carrying any debt forward.
