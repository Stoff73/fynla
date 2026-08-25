---
id: W-0279
title: The risk profile has no /m counterpart, while the risk level it produces is shown on /m
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: product-lead
status: queued
severity: low
surfaces: [m]
created: 2026-08-22T22:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0271, W-0272, W-0273]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Recorded while checking Rule 19 coverage for W-0271 / W-0272 / W-0273.

`resources/mobile/router.js` has **no** risk route. There is no `/m` equivalent of
`/risk-profile`, `/risk-profile/levels` or `/risk-profile/factor/:factor`.

The output of that engine does reach `/m`:
`resources/mobile/views/modules/Investment.vue:104` renders
`riskProfile.risk_level` as the attitude to risk shown against the portfolio. So a
mobile user is shown the **conclusion** with no route to the nine factors behind it,
no way to see which figure produced it, and no way to correct a factor that is wrong.

This is why the three fixes above needed **no** `/m` code change — there is nothing on
`/m` to change — and it is a Rule 19 gap in the product rather than in those items.
Flagged, not skipped.

## Acceptance

`/m` gets an explanation that the detailed view is available on the web app, with a log in redirect button for the user, that when clicked takes them to the log in for the web app. This also needs to be the same for the iOS mobile app.
