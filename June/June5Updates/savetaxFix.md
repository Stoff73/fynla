# savetax (and all campaign deep-links) unreachable on mobile — Delta, Fix & Tasks

**Date:** 2026-06-05
**Severity:** High — every paid/organic campaign deep-link collapses to the generic homepage on phones, destroying campaign context (the entire purpose of a campaign landing URL).
**Environments affected:** All (localhost, csjones.co/fynla, fynla.org) — same redirect + host code on each.

---

## 1. The delta (what is broken vs what was assumed)

| | Assumed / documented intent | Actual runtime behaviour (verified) |
|---|---|---|
| Phone hits `/savetax` | Loads the savetax funnel inside `/m` (`homepage → /savetax → register`) | `302 → /m`; the **path is discarded** |
| `/m` host | Frames the page the user asked for | Hardcoded to frame **`url('/')`** (homepage), never the campaign page |
| Reaching savetax by navigation | Click-through from homepage | **No link to `/savetax`** exists on the homepage / public funnel |
| **Net effect on phones** | savetax campaign reachable | **savetax is unreachable — neither by deep-link nor by navigation** |

The campaign pages themselves **are** responsive and render correctly at 390px width — they are simply walled off from phones by the redirect.

### Affected campaign routes (all `meta.public`, all redirect to `/m`)
| URL | Route name |
|-----|-----------|
| `/savetax` | `CampaignSaveTax` |
| `/biggerpension` | `CampaignBiggerPension` |
| `/paymortgage` | `CampaignPayMortgage` |
| `/managedebt` | `CampaignManageDebt` |
| `/wealth` | `CampaignWealth` |

> The server-rendered savetax funnel variants (`/savetax/v2`, `/savetax/plan`, `/savetax/plan/v2…v4`) are equally affected — they all match the phone redirect and lose their path.

---

## 2. Evidence

```
# Phone UA (iPhone) direct hits — Accept: text/html
GET /savetax        → HTTP 302  Location: /m
GET /               → HTTP 302  Location: /m
GET /biggerpension  → HTTP 302  Location: /m

# /m host iframe target
resources/views/mobile-host.blade.php:24
  <iframe src="{{ url('/') }}" ...>     # hardcoded homepage, not the requested path

# Redirect has no path preservation
app/Http/Middleware/RedirectPhoneToMobile.php:45
  return redirect('/m');                 # no intended(), no path passthrough

# No homepage → savetax link
grep 'savetax' in LandingPage.vue / components/Public / PublicLayout.vue → (no matches)
```

---

## 3. Root cause

Two independent gaps combine:

1. **`RedirectPhoneToMobile::handle()`** (`app/Http/Middleware/RedirectPhoneToMobile.php:45`) funnels **all** phone GET HTML navigations to a single `/m` entry with a hardcoded `redirect('/m')` — the originally requested path (`/savetax`) is thrown away.
2. **`mobile-host.blade.php:24`** always frames `url('/')` (homepage). Even if the path survived the redirect, the host ignores it.

Campaign deep-link entry was never wired through the `/m` host, and no homepage→campaign link exists as a fallback. So the campaign URL — whose only job is to be the deep-link target of an ad — never shows campaign content on mobile.

---

## 4. The fix

Two viable approaches. **Option A is the minimal correct fix for the campaign-funnel purpose and is recommended.**

### Option A — exclude campaign prefixes from the phone redirect (recommended)
Let phones load the (already responsive) campaign pages directly, bypassing `/m`.

- Add the campaign prefixes to `RedirectPhoneToMobile::EXCLUDED_PREFIXES`:
  `savetax`, `savetax/*`, `biggerpension`, `paymortgage`, `managedebt`, `wealth`.
- Phones hitting `/savetax` then render the responsive campaign page directly.
- **Trade-off:** campaign pages render outside the `/m` device-frame host. Acceptable — they are full responsive pages, and the funnel they lead into (`register` → authed app) will still hand off to `/m/app` via the existing router bridge once the user authenticates.
- **Decision needed:** confirm the post-funnel handoff (register → `/m/app`) still works when entry was *not* via `/m`. The router guard bridges `auth_token` → `m_scaffold_token` only when `self !== top` (i.e. inside the `/m` iframe). If the campaign page is NOT framed, that bridge won't fire — see Task 6.

### Option B — preserve the path through `/m`
Keep the device-frame host, but honour the requested URL.

- `RedirectPhoneToMobile`: redirect to `/m?to=<original-path>` (whitelist `to` to campaign/public paths only — open-redirect guard).
- `mobile-host.blade.php`: set iframe `src` to the validated `to` param, defaulting to `url('/')`.
- Keeps everything inside the `/m` frame and preserves the existing auth-bridge seam.
- **Trade-off:** more moving parts; needs an open-redirect allowlist on `to`.

---

## 5. Task list

> Rule #14 applies: verify in the browser with a **phone user-agent** (not desktop) before calling any task done. The original mis-diagnosis happened precisely because the redirect is UA-gated and a desktop UA hides it.

### If Option A (recommended)
- [ ] **T1.** Add `savetax`, `savetax/*`, `biggerpension`, `paymortgage`, `managedebt`, `wealth` to `EXCLUDED_PREFIXES` in `app/Http/Middleware/RedirectPhoneToMobile.php`.
- [ ] **T2.** Phone-UA `curl` each campaign URL → expect **HTTP 200** (not 302). Cover `/savetax`, `/savetax/plan`, and one of the others.
- [ ] **T3.** Browser test at 390px with an iPhone UA: load `/savetax`, complete the 4-step questionnaire, reach `register`.
- [ ] **T4.** Confirm `?full=1` escape hatch + `m_full_site` cookie still behave (no regression to the phone→`/m` redirect for non-campaign paths like `/`).
- [ ] **T5.** Confirm non-excluded phone navigations (`/`, `/dashboard`) still redirect to `/m` (no over-exclusion).
- [ ] **T6.** Verify the register → authed → `/m/app` handoff when the user entered via the standalone campaign page (NOT framed). If the `self !== top` auth-bridge in `resources/js/router/index.js` no longer fires, decide: (a) also bridge on a query flag/cookie, or (b) have the campaign register flow set `m_scaffold_token` directly.

### If Option B
- [ ] **T1.** `RedirectPhoneToMobile`: redirect to `/m?to=<path>` with a strict allowlist (campaign + public prefixes only).
- [ ] **T2.** `mobile-host.blade.php`: read validated `to`, set iframe `src` accordingly; default `url('/')`.
- [ ] **T3.** Open-redirect test: `/m?to=//evil.com` and `/m?to=/admin` must fall back to homepage.
- [ ] **T4.** Phone-UA browser test: `/savetax` → `302 /m?to=/savetax` → iframe shows savetax → complete funnel → register.
- [ ] **T5.** Confirm in-frame funnel navigation (homepage → savetax → register) still skips the redirect (Sec-Fetch-Dest iframe rule intact).
- [ ] **T6.** Same post-funnel `/m/app` handoff check as Option A T6.

### Shared follow-ups (either option)
- [ ] **T7.** Decide whether the homepage should *also* link to `/savetax` (independent of the redirect fix) so the funnel is discoverable, not only ad-deep-linked.
- [ ] **T8.** Update the companion `m-pathway-connection-delta.md` (§1.1 currently states public/campaign surfaces are "NOT CONNECTED by design — funnel is the responsive pages"; once fixed, savetax becomes genuinely reachable on mobile and that note should change).
- [ ] **T9.** Update the `reference_mobile_phone_entry_responsive.md` memory — it currently asserts `/m` "iframes the REAL responsive funnel (homepage → savetax → register)"; that is the intent, not the verified behaviour for campaign deep-links until this fix lands.

---

## 6. Key files

| File | Role |
|------|------|
| `app/Http/Middleware/RedirectPhoneToMobile.php` | Phone→`/m` redirect (`:45` hardcoded, `EXCLUDED_PREFIXES` `:24–33`) |
| `resources/views/mobile-host.blade.php` | `/m` device-frame host (`:24` hardcoded iframe `src`) |
| `routes/web.php` | `/m`, `/m/landing`, `/m/app` (`~:652`); `/savetax*` funnel (`~:586–642`) |
| `resources/js/router/index.js` | `self !== top` auth-bridge (`~:1463–1479`) — relevant to post-funnel `/m/app` handoff |
| `resources/js/views/Public/SaveTaxCampaignPage.vue` | the savetax Vue surface (responsive, renders fine at 390px) |

---

*Diagnosis verified 2026-06-05 with iPhone user-agent against localhost:8000. Companion docs: `surfaces-and-api-map.md`, `m-pathway-connection-delta.md`.*
