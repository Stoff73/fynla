---
id: W-0493
title: Content Security Policy blocks every bespoke insight image
mission: M-0002-persona-fidelity
owner: build-lead
status: closed_invalid
severity: low
surfaces: [web, m]
source: found during W-0001 browser verification, 2026-08-25
prior_art_checked: 2026-08-25
prior_art_outcome: none
---

## Closed invalid 2026-08-25 — not a defect, and my own doing

**There is no Content Security Policy problem. The images are not blocked in any
real use. The failure was an artefact of how I invoked the tests, and I raised it
before establishing that.**

## The original report

Console errors on `/m` and in the E2E smoke run:

    Loading the image 'http://localhost:8000/storage/insights/bespoke/how-much-to-retire-uk.jpg'
    violates the following Content Security Policy directive: "img-src 'self' data: blob: ..."

Filed as a CSP misconfiguration needing `/storage/**` added to `img-src`.

## Why that was wrong

`img-src` already contains `'self'`, and `'self'` means the **document's** origin.

- `config/filesystems.php:42` sets the public disk URL to `env('APP_URL').'/storage'`,
  so image URLs are **absolute**, built from `APP_URL`.
- The local `.env` has `APP_URL=http://localhost:8000`.
- `playwright.config.js:3` defaults `baseURL` to `http://127.0.0.1:8000`.

Serving a page from `127.0.0.1:8000` while its images are absolute to
`localhost:8000` makes them cross-origin, and `'self'` correctly refuses them.
Same host, same port, different origin as far as the browser is concerned.

Proven by loading the same page from both origins:

| Page origin | Image origin | Same origin | Images loaded | Console errors |
|---|---|---|---|---|
| `http://localhost:8000/insights` | `localhost:8000` | yes | **8 of 8**, `naturalWidth: 800` | **0** |
| `http://127.0.0.1:8000/insights` | `localhost:8000` | no | **0 of 8** | **8** |

## And CI was never affected

`scripts/e2e/serve.sh:49` starts the E2E server with
`APP_URL=http://127.0.0.1:8000`, matching the `PLAYWRIGHT_BASE_URL` that
`quality.yml:218` and `nightly.yml:70` set. **When the suite runs as designed the
origins agree and nothing is blocked.**

The mismatch appeared only because I ran the tests with
`PLAYWRIGHT_REUSE_SERVER=1` against my own dev server, which carries the `.env`
`APP_URL` of `localhost:8000`, while Playwright aimed at `127.0.0.1:8000`. That
shortcut was mine, taken because the sanctioned path needs an `*_e2e` database
this machine's MySQL account cannot create.

Confirmed by aligning the origin: with `PLAYWRIGHT_BASE_URL=http://localhost:8000`
the mobile smoke tests report **zero** runtime errors, where they had reported 5
and 7.

## Nothing changed

No code was modified for this item.

## What to take from it

If you run E2E against a reused dev server, set `PLAYWRIGHT_BASE_URL` to match
`APP_URL` or the CSP will refuse same-host assets on the other spelling of
localhost. That is a note for whoever next takes the shortcut, not a defect.

The real lesson is upstream: this is the third item in one session raised from
symptoms without first reading the mechanism — with W-0492 and W-0494. See the
gap noted on those two.
