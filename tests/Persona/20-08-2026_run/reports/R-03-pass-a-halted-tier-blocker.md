# R-03 — Pass A halted: free-tier provisioning blocker

**Run:** `peak_earners`, Pass A · **Environment:** local `http://localhost:8000`
**Halted:** 2026-08-20 23:05 · **Written:** 2026-08-21 08:12 (backfilled)
**Status:** Pass A **incomplete — blocked on a decision, not on effort**

---

## Done

Established, with evidence, why the remainder of the persona cannot be entered.

Both test accounts resolve to `tier=free` (`TierResolver`). Only two tiers exist:
`free` and `premium`.

`tier_configurations.count_caps`:

```
free:    {"goal":2,"mortgage":10,"property":1,"investment":2,
          "life_event":1,"pension_account":2,"savings_account":2}
premium: all null
```

Against the persona:

| Entity | Free cap | `peak_earners.md` needs | Shortfall |
|---|---|---|---|
| property | 1 | 3 | 2 |
| savings_account | 2 | 6 | 4 |
| investment | 2 | 4 | 2 |
| pension_account | 2 | 5 | 3 |
| goal | 2 | 6 | 4 |
| life_event | 1 | 10 | 9 |
| mortgage | 10 | 3 | none |

Hit live: adding the second property raised **"You've reached your Free limit — Your
Free plan includes up to 1 properties."**

Premium-gated routes, confirmed by navigation (each redirects to `/teaser`):
`/estate` · `/estate/will-builder` · `/trusts` · `/estate/power-of-attorney` ·
`/valuable-info?section=letter` · `/holistic-plan`.

Not gated: `/protection` · `/net-worth/*` · `/goals` · `/tax-strategy`.

Evidence: `pass-a-web/08-web-estate-premium-gate-blocks-wills-trusts.jpg`

---

## Not done, and why

Roughly a quarter of the persona contract is unreachable as provisioned — see R-01's
"Persona records NOT entered" table for the itemised list. In particular the two
checks the dispatch called out as priority-1 and priority-4:

- **Manchester tenants-in-common** (David 40% → £118,000; Mike Barrett's 60% owned by
  no household account) — blocked by the property cap.
- **IHT liability** — blocked by the estate premium gate.

I did **not** grant a tier. Flipping `tier` on a user row is the "never edit `.env` or
patch a DB row to make a check pass" line from my own definition, and a real payment
flow is out of bounds. This is the acceptable exit (b): a decision the plan does not
answer, reached after exhausting the persona file, the dispatch, `CLAUDE.md`, and the
vault — none of which mention subscription tier.

---

## Assumptions

- That the persona is *intended* to run at premium, since it describes a £1.5m–£2m
  household with three properties, four investment accounts and a trust — a shape the
  free tier is designed to exclude. Stated as an assumption because nothing in the
  persona file or the dispatch says so.
- That the seeded `preview_peak_earners` being `tier=free` is **not** evidence of the
  intended tier, because preview users bypass gating via `is_preview_user`.

---

## Needs

**Decision required from team-lead / CSJ before Pass A can continue:**

1. Should Pass A run on **premium** accounts?
2. If yes, what is the sanctioned provisioning route — an artisan command, an admin
   screen, or CSJ doing it directly? I will not do it unilaterally.
3. If no — if the persona is meant to be enterable on free — then the caps themselves
   are the defect and that is a product decision, not an engineering one.

Secondary, non-blocking: should the Defined Benefit form gaps (no Normal Retirement
Age, no Spouse Pension %, no CPI/RPI enum, no career-average scheme type) be one
W-item or folded into an existing one?

---

## Noticed

The blocker is genuinely informative, not just an obstacle: a free-tier user who
enters a Defined Benefit pension first lands in the W-0010 dead-end, cannot record
expenditure at all (W-0011), and sees Savings and Investment render **LOCKED** on `/m`
as a direct consequence. The free-tier journey has real holes in it independent of
this persona run, and three of the eleven items raised (W-0010, W-0011, W-0013) are
things a real free-tier customer would hit on day one.

---

## State left behind

- Test users **left in place** as instructed: David id 16, Sarah id 17. No teardown.
- No PRs, no merges, no deploys. csjones and prod untouched.
- 11 work items on the board, W-0006 … W-0016, all routed to `build-lead`.
- Consolidated handoff at
  `workforce/ops/handoffs/persona-peak_earners-2026-08-20/persona-tester-to-build-lead-2026-08-20.md`
