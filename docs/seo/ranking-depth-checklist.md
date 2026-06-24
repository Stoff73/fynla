# SEO Ranking-Depth Checklist (Fynla)

Context: GSC shows many non-brand consumer queries (e.g. `financial planning
software`, `financial planning tools`, `financial planner tool`) getting
impressions but **0 clicks** — i.e. ranking on page 2+. Brand (`fynla`) ranks #1
and clicks fine. Decision (task 2): **focus consumer, treat adviser/B2B queries
as vanity impressions** for now.

Already shipped on `202606-fynla-seo`:
- Fixed garbled (mojibake) titles/metas across 24 public pages.
- Homepage + `/features` titles aligned to the consumer category terms.
- `/public/` duplicate-URL 301, `/version` dropped from sitemap, `/help` linked.

This checklist is the slower lever: depth, internal links, and authority to move
from page 2 → page 1.

---

## 1. On-page content depth (the target commercial pages)

- [ ] **Homepage** — add a short, crawlable body section using the phrase
      "financial planning software" naturally (intro paragraph or a "What is
      Fynla" block). The hero image carries no text for Google.
- [ ] **`/features`** — ensure the page has substantive copy around "financial
      planning tools **and software**", not just a feature grid. Add an intro
      paragraph and per-feature descriptions (already partly there).
- [ ] **Create/strengthen a dedicated landing page** per head term if intent
      differs, e.g. `/financial-planning-software` — only if it won't cannibalise
      the homepage; otherwise optimise the homepage and skip.
- [ ] Each money page answers the searcher's question in the first 100 words
      (what it is, who it's for, what it does) — Google rewards intent match.
- [ ] No thin pages competing for the same term (avoid keyword cannibalisation
      between homepage and `/features`).

## 2. Topical authority via clusters (Fynla's real strength)

- [ ] Build **hub-and-spoke clusters** around each money theme:
  - Pensions: `/learn/*pension*`, `/insights/*retire*`, `/calculators` (pension),
    `/features/pension-tracker`, `/features/when-can-i-retire`.
  - ISAs: `/learn/what-is-an-isa`, `/insights/*isa*`, `/learn/tax/isa-allowance`.
  - IHT/estate: `/learn/what-is-inheritance-tax`, `/features/iht-planning`,
    `/learn/tax/iht-thresholds`.
- [ ] Each spoke links **up** to its hub and **sideways** to 2-3 sibling spokes
      with descriptive anchor text (not "click here").
- [ ] Each hub links **down** to all its spokes.
- [ ] Publish on a steady cadence (1-2 `/insights` or `/learn` pieces/month) so
      Google sees the site as actively maintained and authoritative.

## 3. Internal linking (fast, high-leverage)

- [ ] From high-traffic pages (homepage, `/insights` articles, `/learn` hub) add
      **contextual links** to the commercial pages (`/features`, `/pricing`,
      `/calculators`) with keyword-rich anchors.
- [ ] Add a body link from the homepage to `/features` using anchor text
      "financial planning tools".
- [ ] Ensure every sitemap URL is reachable within 3 clicks of the homepage.
- [ ] Audit orphan pages (the `/help` footer link was one — now fixed); confirm
      no other public page is link-isolated.
- [ ] Use breadcrumbs on `/learn/*`, `/insights/*`, `/compare/*` (GSC
      "Enhancements → Breadcrumbs" is already tracked) for crawl + SERP.

## 4. Technical / indexation hygiene

- [ ] After deploy: in GSC **Validate Fix** for "Page with redirect" and
      "Duplicate, Google chose different canonical" (those URLs now 200).
- [ ] Confirm the `/public/` 301 is live (curl `http://fynla.org/` → should end
      at `https://fynla.org/`, not `/public/`); ideally set the SiteGround
      document root to the `public/` folder (permanent fix).
- [ ] Request Indexing (URL Inspection) for the valuable "Discovered – not
      indexed" pages: `/learn`, `/features`, `/security`, `/faq`.
- [ ] Keep titles ≤60 chars and descriptions ≤155 chars on every page; unique
      per page (no duplicates).
- [ ] Add/verify JSON-LD: `Organization` + `WebSite` (with `SearchAction`) on the
      homepage; `Article` on `/insights/*` and `/learn/*`; `FAQPage` on `/faq`.
- [ ] Core Web Vitals: the homepage LCP image is preloaded — re-check
      PageSpeed/CrUX once traffic grows; fix any red lab metrics on key pages.

## 5. Off-page / authority (the real mover for head terms)

- [ ] Digital PR / outreach: pitch the `/insights` data pieces (e.g. "How much
      to retire in the UK", PLSA standards) to UK personal-finance press for
      backlinks.
- [ ] Get listed in relevant UK fintech / personal-finance directories and
      comparison sites.
- [ ] Earn links to the `/calculators` (free tools attract natural links).
- [ ] Encourage branded mentions → branded search is already healthy; convert
      that into linked citations.

## 6. Measurement (close the loop)

- [ ] Track the target queries' **average position** in GSC monthly (not just
      clicks) — position moving 15 → 8 is progress even before clicks rise.
- [ ] Watch **CTR** on the pages whose titles were fixed (mojibake + category
      alignment) — expect CTR lift at the same position.
- [ ] Re-pull the GSC "top queries" in 4-6 weeks; promote any query that gains
      impressions into its own optimised page/section.

---

### Adviser decision (task 2) — parked
Not chasing `financial adviser tools` / `compare financial adviser platforms`
etc. Keep `/advisors` as the lightweight "recommend Fynla to your clients"
referral page; revisit only if a genuine adviser product is built.
