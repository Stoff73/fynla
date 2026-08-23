---
id: W-0393
title: A specific gift renders as an amount followed by "to" and nothing — the legacy has no legatee on screen
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0029-cycle4-wills-and-estate-figures.md
owner: build-lead
reviewers: [quality-lead]
status: handoff
severity: medium
surfaces: [web]
created: 2026-08-23T00:40:00Z
claimed: 2026-08-23T00:50:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-23
prior_art_found: [W-0023, W-0046]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, cycle 4, D-14. Both accounts.

**Surface:** desktop web, `/estate` → Will Planning tab, "Specific Gifts".

### Actual

`Specific Gifts: £10,000 to` — and then nothing, on David's will and Sarah's.
The bequest itself renders correctly further down the page, so only the summary
was failing.

### Root cause — not the one the dispatch expected

`WillPlanning.vue:136` read **`gift.recipient`**. No write path in the
application has ever produced that key. The will builder, the mirror generator
and `WillDocumentService::syncBequests()` all store the name under
**`beneficiary_name`**. The key resolved to `undefined`, which Vue renders as an
empty string, so the sentence ran off the end.

Verified against the stored documents:

```
doc 5: [{"type":"cash","amount":10000,...,"beneficiary_name":"Cancer Research UK"}]
doc 6: [{"type":"cash","amount":10000,...,"beneficiary_name":"British Heart Foundation"}]
```

**The dispatch's hypothesis — that `beneficiary_type = individual` was the cause
— is not this defect.** That is a real and separate storage defect, raised and
fixed as **W-0394**; it has no bearing on whether a name renders.

### `/m` was never affected

`resources/mobile/views/modules/EstateBequests.vue:26` already reads
`beneficiary_name`. The web screen was the outlier. No `/m` file changed and no
rebuild of `public/m-build/` is needed for this item.

## Fix

The template reads `gift.beneficiary_name`, falling back to
`a beneficiary you have not named yet` — a legacy with no legatee must read as
unfinished, not as finished-and-blank.

## Acceptance

- [x] Every specific gift names its recipient.
- [x] A gift with no beneficiary says so rather than trailing off.
- [x] `/m` parity checked — already correct, unchanged.
- [x] Mutation-tested: restoring `gift.recipient` turns exactly the 3 gift cases
      red and leaves all 5 estate-figure cases green.
- [x] **Rendered page read.** `£10,000 to Cancer Research UK` on David's,
      `£10,000 to British Heart Foundation` on Sarah's. Evidence below.


### Browser verification — 2026-08-23, localhost:8000, Playwright

**Tab established as nobody** on arrival (both token stores empty) — checked
rather than assumed, and it was the state team-lead warned about. Logged in
through the real form on each account and confirmed identity with
`GET /api/auth/user` before reading anything: **id 16 David Jones**, then
**id 17 Sarah Jones**. `estate_analysis_16` / `_17` cleared by hand before each
read (W-0381).

Read verbatim off `/estate/will-builder`:

| | David (16) | Sarah (17) |
|---|---|---|
| Spouse line | `100% of your own estate to your spouse (£989,500)` | `100% of your own estate to your spouse (£739,280)` |
| Executors | Sarah Jones · Barclays Wealth | **David Jones** · Barclays Wealth |
| Specific Gifts | `£10,000 to Cancer Research UK` | `£10,000 to British Heart Foundation` |
| Residuary | Sarah Jones — 100% | David Jones — 100% |

The two estate figures **differ**, each is its owner's, and **neither £1,728,780
nor £1,716,780 appears anywhere on either page**. Nobody is their own executor.
Every gift names its recipient.

Screenshots:
`tests/Persona/20-08-2026_run/pass-a-web/150-web-david-will-own-estate-989500-executor-sarah-gift-named-W-0391.png`
`tests/Persona/20-08-2026_run/pass-a-web/151-web-sarah-will-own-estate-739280-executor-david-gift-named-W-0391-W-0393-W-0395.png`

## Working notes

- 2026-08-23 build-lead: fixed. Tests in
  `resources/js/components/__tests__/Estate/WillPlanning.spec.js`. Not
  self-certified — handed to quality-lead.
