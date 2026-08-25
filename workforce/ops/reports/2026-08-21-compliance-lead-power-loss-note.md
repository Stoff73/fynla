# compliance-lead — power-loss note, 2026-08-21

**Written under imminent power loss. Not tidied.**

## Nothing is in progress. All four pieces are complete and on disk.

The stop order assumed W-0100 was mid-analysis. **It is not — it was finished and filed
before the order arrived.** Do not restart it.

| Work | File | State |
|---|---|---|
| W-0019/W-0024 perimeter delta | `workforce/ops/reports/2026-08-21-W-0019-perimeter-delta.md` | **Complete**, incl. §4a exposure-closed update |
| W-0050 consent validity ruling | `workforce/ops/reports/2026-08-21-W-0050-consent-validity-ruling.md` | **Complete**, incl. UPDATE block + §6 draft copy |
| Regime map proposal | `workforce/ops/reports/2026-08-21-perimeter-regime-map-proposal.md` | **Complete** |
| **W-0100 perimeter half (acceptance 5)** | `workforce/ops/reports/2026-08-21-W-0100-lpa-perimeter-review.md` | **COMPLETE — do not redo** |
| Copy handoff to design-lead | `workforce/ops/handoffs/W-0050/compliance-to-design-2026-08-21.md` | **Complete** |

All four board items carry working notes linking their reports (W-0019, W-0050, W-0100).
All events are appended to `workforce/ops/log/2026-08.jsonl`. **Nothing unsaved.**

## The four things that must not be lost

1. **W-0050 draft copy is SHIP-GATED.** It tells users declining stops affiliate tracking.
   False today — `CaptureAwcCookie` (`app/Http/Kernel.php:106`) is global, `AWIN_ENABLED` is
   **true on production**, cookie is `HttpOnly` so the browser cannot clear it. Order:
   gate Awin server-side → remove wall (CSJ) → then copy. Shipping early swaps one false
   justification for a worse one.
2. **W-0100: a green "Compliant" badge is live on production on a legal instrument**
   (`LpaComplianceService.php:49`, `LpaComplianceChecklist.vue:88,97`; `1a3d17e99`,
   2026-03-16). **It is an overclaim even if the generator is perfect** — independent of
   build-lead's audit, and far cheaper to fix.
3. **Trunk recommendation I would take first of the four:** extend perimeter §7.3 to bind the
   PRODUCT, not just the agent. Today the trunk forbids an agent from saying "compliant"
   while the application says it in green. Gap is scope, not content.
4. **Two stale-law traps** — check commencement before citing: PECR reg 6 substituted
   2026-02-05 (DUAA 2025, exemptions now Sch A1); MCA 2005 Sch 1 has pending Powers of
   Attorney Act 2023 amendments **not in force as at 2026-08-20**.

## Open, needing CSJ

Trunk: §1 regime map · §7.3 product scope · legal-services clause · Consumer Duty trade
procedure · paid-capability withdrawal. Decisions: GA data since 2026-04-07 · the cookie
wall itself · Premium no-partner class notified before release · real-user LPA count
(both branches pre-stated in the W-0100 report).

**Standing:** none of my output is an approval. I have not ruled the cookie consent invalid,
and I have not determined that generating or assessing an LPA is permissible.
