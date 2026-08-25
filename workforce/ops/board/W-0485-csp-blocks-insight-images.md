---
id: W-0485
title: Content Security Policy blocks every bespoke insight image
mission: M-0002-persona-fidelity
owner: build-lead
status: queued
severity: medium
surfaces: [web, m]
source: found during W-0001 browser verification, 2026-08-25
prior_art_checked: 2026-08-25
prior_art_outcome: none
---

## Intent

The browser refuses to load insight article images because their path is not in the
`img-src` directive:

    Refused to load http://localhost:8000/storage/insights/bespoke/how-much-to-retire-uk.jpg
    because it does not appear in the img-src directive of the Content Security Policy.
    ... stocks-shares-isa.jpg
    ... isa-guide-uk.jpg

Three on the mobile login route alone, and seven console errors on
mobile-chromium. Every bespoke insight image is affected, so the insights surface
renders without its artwork.

## Acceptance

1. `img-src` covers the application's own `/storage/**` image paths without
   widening the policy beyond what is needed — self-hosted media only, no blanket
   wildcard.
2. Insight images render on web and `/m`.
3. `npm run test:e2e:smoke` mobile projects report zero runtime errors.
4. Checked against the production configuration too. CSP is environment-specific
   and a local-only fix would be a false pass.
